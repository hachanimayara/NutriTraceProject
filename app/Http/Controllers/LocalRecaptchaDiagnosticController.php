<?php

namespace App\Http\Controllers;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LocalRecaptchaDiagnosticController extends Controller
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

        $secret = (string) config('services.google_recaptcha.secret', '');
        $token = 'nutritrace-local-diagnostic-invalid-token';
        $payload = [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ];
        $records = @dns_get_record('www.google.com', DNS_A | DNS_AAAA);
        $dnsAddresses = array_values(array_unique(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            is_array($records) ? $records : []
        ))));

        $nativeCurl = $this->verifyWithNativeCurl($payload);
        $laravelHttp = $this->verifyWithLaravelHttp($payload);
        $proxyVariables = ['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'NO_PROXY', 'http_proxy', 'https_proxy', 'all_proxy', 'no_proxy'];
        $visibleProxyVariables = array_values(array_filter($proxyVariables, static function (string $name): bool {
            return getenv($name) !== false || array_key_exists($name, $_SERVER);
        }));

        return response()->json([
            'application' => [
                'base_path' => base_path(),
                'environment' => app()->environment(),
                'request_host' => $request->getHost(),
                'request_port' => $request->getPort(),
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'php_ini' => php_ini_loaded_file() ?: null,
                'pid' => getmypid(),
            ],
            'captcha_configuration' => [
                'enabled' => (bool) config('services.google_recaptcha.enabled'),
                'site_key_present' => (bool) config('services.google_recaptcha.site_key'),
                'secret_present' => $secret !== '',
            ],
            'transport_configuration' => [
                'curl_version' => curl_version()['version'] ?? null,
                'curl_ssl_version' => curl_version()['ssl_version'] ?? null,
                'curl_cainfo' => ini_get('curl.cainfo') ?: null,
                'openssl_cafile' => ini_get('openssl.cafile') ?: null,
                'tls_verification' => true,
                'proxy_variables_visible' => $visibleProxyVariables,
                'laravel_method' => 'POST',
                'laravel_url' => self::VERIFY_URL,
                'laravel_content_type' => 'application/x-www-form-urlencoded',
                'laravel_timeout_seconds' => 5,
                'laravel_connect_timeout' => 'Guzzle default (not overridden)',
            ],
            'dns' => [
                'hostname' => 'www.google.com',
                'addresses' => $dnsAddresses,
            ],
            'native_php_curl' => $nativeCurl,
            'laravel_http' => $laravelHttp,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function verifyWithNativeCurl(array $payload): array
    {
        $handle = curl_init(self::VERIFY_URL);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($handle);
        $curlCode = curl_errno($handle);
        $info = curl_getinfo($handle);
        $json = is_string($body) ? json_decode($body, true) : null;
        curl_close($handle);

        return [
            'exception_class' => null,
            'curl_code' => $curlCode,
            'http_status' => (int) ($info['http_code'] ?? 0) ?: null,
            'primary_ip' => $this->validIp($info['primary_ip'] ?? null),
            'dns_lookup_ms' => $this->milliseconds($info['namelookup_time'] ?? null),
            'connect_ms' => $this->milliseconds($info['connect_time'] ?? null),
            'google_success' => is_array($json) ? ($json['success'] ?? null) : null,
            'google_error_codes' => $this->errorCodes($json),
        ];
    }

    private function verifyWithLaravelHttp(array $payload): array
    {
        $handlerStats = [];

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->withOptions([
                    'on_stats' => static function (TransferStats $stats) use (&$handlerStats): void {
                        $handlerStats = $stats->getHandlerStats();
                    },
                ])
                ->post(self::VERIFY_URL, $payload);

            return [
                'exception_class' => null,
                'curl_code' => 0,
                'http_status' => $response->status(),
                'primary_ip' => $this->validIp($handlerStats['primary_ip'] ?? null),
                'dns_lookup_ms' => $this->milliseconds($handlerStats['namelookup_time'] ?? null),
                'connect_ms' => $this->milliseconds($handlerStats['connect_time'] ?? null),
                'google_success' => $response->json('success'),
                'google_error_codes' => $this->errorCodes($response->json()),
            ];
        } catch (ConnectionException $exception) {
            preg_match('/cURL error\s+(\d+)/i', $exception->getMessage(), $match);

            return [
                'exception_class' => get_class($exception),
                'curl_code' => isset($match[1]) ? (int) $match[1] : null,
                'http_status' => null,
                'primary_ip' => $this->validIp($handlerStats['primary_ip'] ?? null),
                'dns_lookup_ms' => $this->milliseconds($handlerStats['namelookup_time'] ?? null),
                'connect_ms' => $this->milliseconds($handlerStats['connect_time'] ?? null),
                'google_success' => null,
                'google_error_codes' => [],
            ];
        } catch (\Throwable $exception) {
            return [
                'exception_class' => get_class($exception),
                'curl_code' => null,
                'http_status' => null,
                'primary_ip' => null,
                'dns_lookup_ms' => null,
                'connect_ms' => null,
                'google_success' => null,
                'google_error_codes' => [],
            ];
        }
    }

    private function errorCodes(mixed $json): array
    {
        return array_values(array_filter(
            is_array($json) ? (array) ($json['error-codes'] ?? []) : [],
            static fn ($code): bool => is_string($code) && $code !== ''
        ));
    }

    private function validIp(mixed $value): ?string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function milliseconds(mixed $seconds): ?float
    {
        return is_numeric($seconds) ? round((float) $seconds * 1000, 2) : null;
    }
}

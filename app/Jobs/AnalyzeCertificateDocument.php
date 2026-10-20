<?php

namespace App\Jobs;

use App\Models\DocumentAnalysis;
use App\Services\CertificateDocumentAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class AnalyzeCertificateDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [20, 60];

    public function __construct(public int $analysisId) {}

    public function handle(CertificateDocumentAnalysisService $service): void
    {
        $analysis = DocumentAnalysis::find($this->analysisId);
        if (! $analysis || $analysis->status !== 'processing') {
            return;
        }
        if (! $analysis->external_processing_consented_at) {
            $analysis->update(['status' => 'failed', 'failure_code' => 'consent_required']);

            return;
        }

        try {
            $result = $service->analyze($analysis);
            $analysis->update([
                'status' => 'analyzed', 'extracted_data' => ['fields' => $result['fields'], 'confidence' => $result['confidence']],
                'summary' => $result['summary'], 'anomalies' => $result['anomalies'],
                'ai_model' => config('services.gemini.model'), 'failure_code' => null,
            ]);
        } catch (RuntimeException $exception) {
            if (preg_match('/^provider_rate_limit:(\d+)$/', $exception->getMessage(), $match)) {
                if ($this->attempts() < $this->tries) {
                    $this->release((int) $match[1]);

                    return;
                }

                $analysis->update(['status' => 'failed', 'failure_code' => 'provider_rate_limit']);

                return;
            }
            $code = in_array($exception->getMessage(), ['not_configured', 'consent_required', 'provider_auth', 'provider_quota', 'provider_unavailable', 'provider_request_invalid', 'provider_model_unavailable', 'provider_file_too_large', 'provider_blocked', 'invalid_response'], true)
                ? $exception->getMessage()
                : 'invalid_response';
            if ($code === 'provider_unavailable' && $this->attempts() < $this->tries) {
                throw $exception;
            }
            $analysis->update(['status' => 'failed', 'failure_code' => $code]);
        } catch (\Throwable) {
            $analysis->update(['status' => 'failed', 'failure_code' => 'invalid_response']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DocumentAnalysis::whereKey($this->analysisId)->where('status', 'processing')
            ->update(['status' => 'failed', 'failure_code' => 'provider_unavailable']);
    }
}

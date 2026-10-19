<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class DiagnoseRecaptchaServer extends Command
{
    protected $signature = 'recaptcha:diagnose-server {--port=8001 : Port used by the local Laravel server}';

    protected $description = 'Compare PHP cURL and Laravel HTTP from the running local web server';

    public function handle(): int
    {
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            $this->error('The port must be an integer between 1 and 65535.');

            return self::INVALID;
        }

        try {
            $response = Http::timeout(15)->get("http://127.0.0.1:{$port}/_local/diagnostics/recaptcha");
        } catch (Throwable $exception) {
            $this->error('Could not reach the local Laravel diagnostic endpoint. Start php artisan serve on the selected port first.');
            $this->line('Exception class: '.get_class($exception));

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('The local server rejected the diagnostic request with HTTP '.$response->status().'.');

            return self::FAILURE;
        }

        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

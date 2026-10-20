<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeCertificateDocument;
use App\Models\Certification;
use App\Models\DocumentAnalysis;
use App\Models\User;
use App\Services\CertificateDocumentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    }

    private function analysis(User $user, string $mime = 'image/png'): DocumentAnalysis
    {
        Storage::fake('local');
        Storage::disk('local')->put('document-analyses/certificate.pdf', '%PDF-1.4 demo');

        return DocumentAnalysis::create(['uploaded_by' => $user->id, 'original_filename' => 'certificate.pdf', 'storage_path' => 'document-analyses/certificate.pdf', 'mime_type' => $mime, 'status' => 'pending', 'external_processing_consented_at' => now()]);
    }

    private function validPayload(array $replace = []): array
    {
        $fields = array_fill_keys(['product_name', 'producer_name', 'lot_number', 'certificate_number', 'certificate_type', 'issuer', 'issued_at', 'expires_at', 'geographic_origin', 'other_information'], null);
        $confidence = array_fill_keys(array_keys($fields), null);
        $fields['certificate_number'] = 'CERT-EXAMPLE';
        $fields['certificate_type'] = 'Biologique';
        $fields['issuer'] = 'Organisme exemple';
        $fields['issued_at'] = '2025-01-01';
        $fields['expires_at'] = '2025-01-10';

        return array_replace_recursive(['fields' => $fields, 'confidence' => $confidence, 'summary' => 'Certificat de démonstration.', 'suggested_anomalies' => []], $replace);
    }

    private function responseFor(array $payload): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]]]];
    }

    public function test_admin_can_import_private_document_and_other_roles_cannot_access_module(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.document-analyses.index'))->assertOk()->assertSee('IA et reconnaissance de documents')->assertSee('GEMINI_API_KEY')->assertSee('GEMINI_DOCUMENT_ANALYSIS_ENABLED=true');
        $this->actingAs(User::factory()->create(['role' => 'producteur', 'status' => 'approved']))
            ->get(route('admin.document-analyses.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.document-analyses.store'), ['document' => UploadedFile::fake()->createWithContent('secret.pdf', '%PDF-1.4 NutriTrace')])
            ->assertRedirect();
        $record = DocumentAnalysis::firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->assertStringStartsWith('document-analyses/', $record->storage_path);
        Storage::disk('local')->assertExists($record->storage_path);
        $this->get(route('admin.document-analyses.file', $record))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->get(route('admin.document-analyses.file', $record))->assertForbidden();
    }

    public function test_upload_rejects_forbidden_files_and_oversized_files(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.document-analyses.store'), ['document' => UploadedFile::fake()->create('script.php', 2, 'text/plain')])->assertSessionHasErrors('document');
        $this->actingAs($admin)->post(route('admin.document-analyses.store'), ['document' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('document');
        $this->assertSame(0, DocumentAnalysis::count());
    }

    public function test_admin_can_delete_document_and_its_private_file_after_confirmation(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $record = $this->analysis($admin);
        $filePath = $record->storage_path;

        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->delete(route('admin.document-analyses.destroy', $record))->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.document-analyses.destroy', $record))
            ->assertRedirect(route('admin.document-analyses.index'))->assertSessionHas('status');
        $this->assertDatabaseMissing('document_analyses', ['id' => $record->id]);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_explicit_consent_dispatches_job_and_missing_provider_never_fakes_results(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $record = $this->analysis($admin);
        $record->update(['external_processing_consented_at' => null]);
        config(['services.gemini.document_analysis_enabled' => false, 'services.gemini.api_key' => null]);
        $this->actingAs($admin)->post(route('admin.document-analyses.analyze', $record), ['external_processing_consent' => '1'])
            ->assertSessionHasErrors('analysis');
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertNull($record->fresh()->summary);

        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Queue::fake();
        $this->post(route('admin.document-analyses.analyze', $record), [])->assertSessionHasErrors('external_processing_consent');
        $this->post(route('admin.document-analyses.analyze', $record), ['external_processing_consent' => '1'])->assertRedirect();
        $this->assertSame('processing', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->external_processing_consented_at);
        Queue::assertPushed(AnalyzeCertificateDocument::class);
        $this->post(route('admin.document-analyses.analyze', $record), ['external_processing_consent' => '1'])
            ->assertRedirect(route('admin.document-analyses.show', $record))->assertSessionHas('status');
        Queue::assertPushed(AnalyzeCertificateDocument::class, 1);
    }

    public function test_service_parses_valid_api_json_and_flags_missing_and_expired_fields(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $record = $this->analysis($admin);
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-2.5-flash']);
        $payload = $this->validPayload(['fields' => ['certificate_number' => null, 'issuer' => null]]);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response($this->responseFor($payload), 200)]);

        $result = app(CertificateDocumentAnalysisService::class)->analyze($record);
        $this->assertNull($result['fields']['certificate_number']);
        $this->assertContains('missing_field', array_column($result['anomalies'], 'type'));
        $this->assertContains('expired', array_column($result['anomalies'], 'type'));
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
            && $request->hasHeader('x-goog-api-key') && $request->data()['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_invalid_response_and_provider_errors_are_safe_and_do_not_expose_body(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['output' => []], 200)]);
        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected invalid provider response to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('invalid_response', $exception->getMessage());
        }
    }

    public function test_gemini_request_uses_the_configured_model_and_structured_output(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-2.5-flash']);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response($this->responseFor($this->validPayload()), 200)]);

        app(CertificateDocumentAnalysisService::class)->analyze($record);

        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request->data()['generationConfig']['responseJsonSchema']['properties']['summary']['type'] === 'string');
    }

    public function test_invalid_configured_model_is_rejected_without_request(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'https://attacker.test/model']);
        Http::fake();

        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected unsafe model name to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider_model_unavailable', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_service_refuses_to_send_document_without_explicit_consent(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        $record->update(['external_processing_consented_at' => null]);
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake();

        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected consent to be required.');
        } catch (RuntimeException $exception) {
            $this->assertSame('consent_required', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_gemini_request_auth_not_found_and_server_errors_map_to_safe_codes(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);

        $cases = [400 => 'provider_request_invalid', 401 => 'provider_auth', 403 => 'provider_auth', 404 => 'provider_model_unavailable', 422 => 'provider_request_invalid', 500 => 'provider_unavailable', 503 => 'provider_unavailable'];
        $responses = Http::sequence();
        foreach (array_keys($cases) as $status) {
            $responses->push(['error' => ['message' => 'private detail']], $status);
        }
        Http::fake(['https://generativelanguage.googleapis.com/*' => $responses]);

        foreach ($cases as $status => $expected) {
            try {
                app(CertificateDocumentAnalysisService::class)->analyze($record);
                $this->fail("Expected HTTP {$status} to fail.");
            } catch (RuntimeException $exception) {
                $this->assertSame($expected, $exception->getMessage());
                $this->assertStringNotContainsString('private detail', $exception->getMessage());
            }
        }
    }

    public function test_empty_candidate_response_is_rejected(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200)]);

        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected empty response to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('invalid_response', $exception->getMessage());
        }
    }

    public function test_job_marks_provider_failure_terminal_and_failed_page_stops_polling(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        $record->update(['status' => 'processing', 'external_processing_consented_at' => now(), 'summary' => 'Ancien résumé conservé.']);
        $service = \Mockery::mock(CertificateDocumentAnalysisService::class);
        $service->shouldReceive('analyze')->once()->andThrow(new RuntimeException('provider_auth'));

        (new AnalyzeCertificateDocument($record->id))->handle($service);
        $this->assertSame('failed', $record->fresh()->status);
        $this->assertSame('provider_auth', $record->fresh()->failure_code);
        $this->assertSame('Ancien résumé conservé.', $record->fresh()->summary);

        $this->actingAs($record->uploader)->get(route('admin.document-analyses.show', $record))
            ->assertOk()->assertSee('La clé Gemini est invalide')->assertSee('Résumé d’une analyse précédente')->assertSee('Ancien résumé conservé.')
            ->assertDontSee('data-analysis-poll')->assertDontSee('location.reload');
    }

    public function test_image_upload_is_sent_as_a_base64_vision_input(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin(), 'image/png');
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response($this->responseFor($this->validPayload()), 200)]);
        $result = app(CertificateDocumentAnalysisService::class)->analyze($record);
        $this->assertSame('CERT-EXAMPLE', $result['fields']['certificate_number']);
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
    }

    public function test_pdf_is_sent_to_gemini_as_inline_document_input(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin(), 'application/pdf');
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-token']);
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response($this->responseFor($this->validPayload()), 200)]);

        app(CertificateDocumentAnalysisService::class)->analyze($record);
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
            && $request->data()['contents'][0]['parts'][1]['inline_data']['mime_type'] === 'application/pdf');
    }

    public function test_quota_response_is_mapped_to_a_safe_error_code(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['error' => ['message' => 'sensitive provider details', 'status' => 'RESOURCE_EXHAUSTED']], 429)]);
        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected quota response to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider_quota', $exception->getMessage());
            $this->assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }

    public function test_payment_required_response_is_mapped_to_provider_quota(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-token']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['error' => 'payment required'], 402)]);

        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected a payment-required response.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider_quota', $exception->getMessage());
        }
    }

    public function test_network_timeout_is_mapped_to_a_safe_error_code(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => fn () => throw new ConnectionException('network details')]);
        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected network error to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider_unavailable', $exception->getMessage());
            $this->assertStringNotContainsString('network details', $exception->getMessage());
        }
    }

    public function test_temporary_rate_limit_respects_retry_after_without_confusing_it_with_billing_quota(): void
    {
        Storage::fake('local');
        $record = $this->analysis($this->admin());
        config(['services.gemini.document_analysis_enabled' => true, 'services.gemini.api_key' => 'test-key']);
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'type' => 'rate_limit_error']], 429, ['Retry-After' => '17'])]);

        try {
            app(CertificateDocumentAnalysisService::class)->analyze($record);
            $this->fail('Expected a temporary rate-limit response.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider_rate_limit:17', $exception->getMessage());
        }
    }

    public function test_admin_review_does_not_change_official_certification(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $certification = Certification::create(['created_by' => $producer->id, 'title' => 'Bio', 'scheme' => 'Bio', 'certificate_number' => 'OFFICIAL-1', 'issuer' => 'Issuer', 'status' => 'pending']);
        $record = $this->analysis($admin);
        $record->update(['status' => 'analyzed', 'extracted_data' => ['fields' => ['certificate_number' => 'CHANGED'], 'confidence' => []], 'summary' => 'Résumé', 'anomalies' => []]);

        $this->actingAs($admin)->put(route('admin.document-analyses.review', $record), ['decision' => 'approved', 'fields' => ['certificate_number' => 'CORRECTED'], 'comment' => 'Vérifié'])
            ->assertRedirect();
        $this->assertSame('approved', $record->fresh()->status);
        $this->assertSame('CORRECTED', $record->fresh()->extracted_data['fields']['certificate_number']);
        $this->assertSame('pending', $certification->fresh()->status);
        $this->assertSame('OFFICIAL-1', $certification->fresh()->certificate_number);

        $second = $this->analysis($admin);
        $second->update(['status' => 'analyzed', 'extracted_data' => ['fields' => [], 'confidence' => []], 'summary' => 'Résumé', 'anomalies' => []]);
        $this->actingAs($admin)->put(route('admin.document-analyses.review', $second), ['decision' => 'rejected', 'comment' => 'Document illisible'])->assertRedirect();
        $this->assertSame('rejected', $second->fresh()->status);
    }
}

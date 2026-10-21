<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\CorrectiveAction;
use App\Models\EnvironmentalIndicator;
use App\Models\Farm;
use App\Models\Lot;
use App\Models\Product;
use App\Models\TraceabilityAlert;
use App\Models\User;
use App\Services\TraceabilityAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplianceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'approved']);
    }

    private function alert(User $producer, string $status = 'open'): TraceabilityAlert
    {
        return TraceabilityAlert::create([
            'created_by' => null,
            'subject_type' => Certification::class,
            'subject_id' => Certification::create(['created_by' => $producer->id, 'title' => 'Bio', 'scheme' => 'BIO', 'certificate_number' => 'CERT-'.Str::upper(Str::random(8)), 'issuer' => 'Organisme', 'status' => 'pending'])->id,
            'code' => 'test_compliance', 'severity' => 'warning', 'title' => 'Dossier à examiner', 'explanation' => 'Vérifier la preuve fournie.', 'evidence' => ['field' => 'status'], 'status' => $status,
        ]);
    }

    public function test_only_admins_can_access_the_compliance_module_and_certification_filters_use_real_records(): void
    {
        $admin = $this->account('admin');
        $producer = $this->account('producteur');
        Certification::create(['created_by' => $producer->id, 'title' => 'Certification Bio', 'scheme' => 'BIO', 'certificate_number' => 'CERT-FILTER-1', 'issuer' => 'Organisme', 'status' => 'pending']);

        $this->actingAs($producer)->get(route('admin.compliance.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.compliance.dashboard'))->assertOk()->assertSee('Contrôle de conformité');
        $this->get(route('admin.compliance.certifications', ['q' => 'CERT-FILTER']))->assertOk()->assertSee('CERT-FILTER-1');
        $this->get(route('admin.compliance.certifications', ['q' => 'inconnu']))->assertOk()->assertSee('Aucune certification ne correspond');
    }

    public function test_analysis_uses_configured_rules_and_keeps_evidence_and_real_traceability_links(): void
    {
        $admin = $this->account('admin');
        $producer = $this->account('producteur');
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme Test', 'reference' => 'FARM-COMP-1', 'location' => 'Nabeul', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Agrume', 'reference' => 'PROD-COMP-1', 'category' => 'Fruits', 'unit' => 'kg', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'LOT-COMP-1', 'public_id' => (string) Str::uuid(), 'quantity' => 12, 'unit' => 'kg', 'status' => 'active']);
        $certificate = Certification::create(['created_by' => $producer->id, 'farm_id' => $farm->id, 'title' => 'Certification BIO', 'scheme' => 'BIO', 'certificate_number' => 'CERT-COMP-1', 'issuer' => 'Organisme', 'expires_at' => today()->subDay(), 'status' => 'valid']);
        EnvironmentalIndicator::create(['recorded_by' => $producer->id, 'product_id' => $product->id, 'lot_id' => $lot->id, 'stage' => 'production', 'metric_code' => 'water_liters', 'name' => 'Eau', 'value' => 3, 'unit' => 'gallons', 'method' => 'Mesure']);
        config(['compliance.required_certification_schemes' => ['BIO'], 'compliance.required_certifications_by_category' => ['Fruits' => ['BIO']], 'compliance.required_indicator_codes_by_stage' => ['production' => ['co2e']], 'compliance.indicator_rules' => ['water_liters' => ['units' => ['L'], 'min' => 0, 'max' => 1]]]);

        $result = app(TraceabilityAnalysisService::class)->analyze();
        $this->assertGreaterThanOrEqual(3, $result['checks_triggered']);
        $alert = TraceabilityAlert::where('code', 'certification_expired')->where('subject_id', $certificate->id)->firstOrFail();
        $this->assertSame('critical', $alert->severity, json_encode($alert->evidence));
        $this->assertSame(['LOT-COMP-1'], $alert->evidence['affected_active_lots']);
        $this->assertNotEmpty($alert->priority_reason);

        $this->actingAs($admin)->get(route('admin.compliance.alerts.show', $alert))->assertOk()->assertSee('Chaîne de traçabilité réellement liée')->assertSee('LOT-COMP-1')->assertSee('Agrume');
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'certification_document_missing', 'subject_id' => $certificate->id]);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'required_indicator_missing', 'subject_id' => $product->id]);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'indicator_unit_invalid']);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'indicator_out_of_range']);
    }

    public function test_admin_decisions_corrective_actions_evidence_and_history_are_required_and_persisted(): void
    {
        Storage::fake('local');
        $admin = $this->account('admin');
        $producer = $this->account('producteur');
        $alert = $this->alert($producer);
        $this->actingAs($admin);

        $this->patch(route('admin.compliance.alerts.decision', $alert), ['status' => 'dismissed', 'decision' => ''])->assertSessionHasErrors('decision');
        $this->patch(route('admin.compliance.alerts.decision', $alert), ['status' => 'reviewing', 'decision' => 'Documents consultés et date contrôlée.'])->assertRedirect();
        $this->assertDatabaseHas('compliance_events', ['alert_id' => $alert->id, 'event' => 'alert.decision', 'to_status' => 'reviewing', 'actor_id' => $admin->id]);

        $this->post(route('admin.compliance.alerts.actions.store', $alert), ['description' => 'Demander une copie lisible du certificat.', 'responsible_id' => $producer->id, 'due_at' => now()->addDay()->format('Y-m-d\TH:i'), 'comment' => 'Pièce actuelle illisible.'])->assertRedirect();
        $action = CorrectiveAction::where('alert_id', $alert->id)->firstOrFail();
        $this->assertSame('in_progress', $alert->fresh()->status);
        $this->patch(route('admin.compliance.actions.update', $action), ['status' => 'completed', 'comment' => 'Document vérifié et accepté.', 'evidence' => UploadedFile::fake()->create('preuve.pdf', 40, 'application/pdf')])->assertRedirect();
        $action->refresh();
        $this->assertSame('completed', $action->status);
        $this->assertNotNull($action->evidence_path);
        Storage::disk('local')->assertExists($action->evidence_path);
        $this->get(route('admin.compliance.actions.evidence', $action))->assertOk();
        $this->patch(route('admin.compliance.alerts.decision', $alert), ['status' => 'resolved', 'decision' => 'Preuve contrôlée, action corrective terminée.'])->assertRedirect();
        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertDatabaseHas('compliance_events', ['alert_id' => $alert->id, 'event' => 'action.updated', 'to_status' => 'completed']);
    }

    public function test_alerts_can_only_be_closed_after_corrective_actions_are_completed(): void
    {
        $admin = $this->account('admin');
        $producer = $this->account('producteur');
        $alert = $this->alert($producer);
        $action = $alert->correctiveActions()->create(['description' => 'Action en attente.', 'created_by' => $admin->id, 'status' => 'todo']);

        $this->actingAs($admin)->patch(route('admin.compliance.alerts.decision', $alert), ['status' => 'resolved', 'decision' => 'Je souhaite clôturer le dossier.'])->assertSessionHasErrors('status');
        $this->assertSame('open', $alert->fresh()->status);
        $this->assertSame('todo', $action->fresh()->status);
    }

    public function test_certification_registration_review_and_renewal_are_audited(): void
    {
        $admin = $this->account('admin');
        $producer = $this->account('producteur');
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme Historique', 'reference' => 'FARM-HIST-1', 'location' => 'Sousse', 'country' => 'Tunisie']);
        $this->actingAs($admin)->post(route('workspace.resources.store', 'certifications'), ['title' => 'Bio', 'scheme' => 'BIO', 'certificate_number' => 'CERT-HIST-1', 'issuer' => 'Organisme', 'farm_id' => $farm->id, 'status' => 'pending', 'expires_at' => today()->addMonths(6)->toDateString()])->assertRedirect();
        $certification = Certification::where('certificate_number', 'CERT-HIST-1')->firstOrFail();
        $this->assertDatabaseHas('compliance_events', ['certification_id' => $certification->id, 'event' => 'certification.registered', 'to_status' => 'pending']);

        $this->put(route('workspace.resources.update', ['certifications', $certification]), ['title' => 'Bio', 'scheme' => 'BIO', 'certificate_number' => 'CERT-HIST-1', 'issuer' => 'Organisme', 'farm_id' => $farm->id, 'status' => 'valid', 'expires_at' => today()->addYear()->toDateString(), 'notes' => 'Vérifiée par comparaison au document.'])->assertRedirect();
        $this->assertDatabaseHas('compliance_events', ['certification_id' => $certification->id, 'event' => 'certification.renewed', 'from_status' => 'pending', 'to_status' => 'valid']);
        $this->get(route('workspace.resources.show', ['certifications', $certification]))->assertOk()->assertSee('Historique des contrôles et décisions')->assertSee('Vérifiée par comparaison au document.');
    }
}

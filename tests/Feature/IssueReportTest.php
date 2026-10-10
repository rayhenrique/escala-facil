<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\SupportReportForm;
use App\Models\IssueReport;
use App\Models\User;
use App\Support\ApplicationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IssueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_exposes_the_report_form_to_authenticated_users(): void
    {
        $this->get(route('help'))->assertRedirect(route('login'));

        foreach ([UserRole::Manager, UserRole::Professional] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'last_seen_version' => app(ApplicationVersion::class)->current(),
            ]);

            $this->actingAs($user)
                ->get(route('help'))
                ->assertOk()
                ->assertSee('Relatar problema', false)
                ->assertSeeLivewire(SupportReportForm::class);
        }
    }

    public function test_professional_can_submit_a_report_and_receives_a_protocol(): void
    {
        Storage::fake('local');
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $component = Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', 'trocas')
            ->set('description', 'Ao aprovar a troca a tela ficou em branco e precisei recarregar.')
            ->set('expectedBehavior', 'Esperava ver a mensagem de confirmação e o comprovante da troca.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Relato enviado com sucesso.');

        $report = IssueReport::query()->sole();

        $this->assertMatchesRegularExpression('/^SUP-\d{8}-[A-Z0-9]{4,8}$/', $report->protocol);
        $this->assertSame($professional->id, $report->user_id);
        $this->assertSame('trocas', $report->module);
        $this->assertNull($report->attachment_path);
        $component->assertSee($report->protocol);
    }

    public function test_report_attachment_is_stored_privately_with_size_and_type_limits(): void
    {
        Storage::fake('local');
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', 'calendario')
            ->set('description', 'O dia com troca aprovada não mostra o destaque laranja no calendário.')
            ->set('expectedBehavior', 'Esperava ver o selo de troca no dia correspondente.')
            ->set('attachment', UploadedFile::fake()->create('evidencia.png', 200, 'image/png'))
            ->call('submit')
            ->assertHasNoErrors();

        $report = IssueReport::query()->sole();

        $this->assertNotNull($report->attachment_path);
        $this->assertStringStartsWith('issue-attachments/', $report->attachment_path);
        Storage::disk('local')->assertExists($report->attachment_path);
    }

    public function test_validation_rejects_invalid_reports_without_persisting(): void
    {
        Storage::fake('local');
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', '')
            ->set('description', 'curto')
            ->set('expectedBehavior', '')
            ->call('submit')
            ->assertHasErrors(['module', 'description', 'expectedBehavior']);

        Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', 'modulo-inexistente')
            ->set('description', 'Descrição longa o suficiente para passar da validação mínima.')
            ->set('expectedBehavior', 'Comportamento esperado também longo o suficiente para validar.')
            ->call('submit')
            ->assertHasErrors(['module']);

        Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', 'trocas')
            ->set('description', 'Descrição longa o suficiente para passar da validação mínima.')
            ->set('expectedBehavior', 'Comportamento esperado também longo o suficiente para validar.')
            ->set('attachment', UploadedFile::fake()->create('nota.txt', 10, 'text/plain'))
            ->call('submit')
            ->assertHasErrors(['attachment']);

        Livewire::actingAs($professional)
            ->test(SupportReportForm::class)
            ->set('module', 'trocas')
            ->set('description', 'Descrição longa o suficiente para passar da validação mínima.')
            ->set('expectedBehavior', 'Comportamento esperado também longo o suficiente para validar.')
            ->set('attachment', UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf'))
            ->call('submit')
            ->assertHasErrors(['attachment']);

        $this->assertDatabaseCount('issue_reports', 0);
    }

    public function test_reports_list_and_attachments_are_restricted_to_managers(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $professional = User::factory()->create(['role' => UserRole::Professional]);
        $report = IssueReport::factory()->create(['user_id' => $professional->id]);

        $this->get(route('manager.issue-reports'))->assertRedirect(route('login'));

        $this->actingAs($professional)->get(route('manager.issue-reports'))->assertForbidden();
        $this->actingAs($professional)
            ->get(route('manager.issue-reports.attachment', $report))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('manager.issue-reports'))
            ->assertOk()
            ->assertSee($report->protocol, false)
            ->assertSee($report->moduleLabel(), false)
            ->assertSee($professional->name, false);
    }

    public function test_manager_can_download_a_report_attachment(): void
    {
        Storage::fake('local');
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $path = UploadedFile::fake()->create('evidencia.pdf', 120, 'application/pdf')->store('issue-attachments', 'local');
        $report = IssueReport::factory()->create([
            'user_id' => $professional->id,
            'attachment_path' => $path,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.issue-reports.attachment', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_are_preserved_when_the_reporter_is_removed(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);
        $report = IssueReport::factory()->create(['user_id' => $professional->id]);

        $professional->delete();

        $this->assertDatabaseHas('issue_reports', ['id' => $report->id, 'user_id' => null]);
    }
}

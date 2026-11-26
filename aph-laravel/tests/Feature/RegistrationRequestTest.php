<?php

namespace Tests\Feature;

use App\Http\Controllers\RegistrationController;
use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationRequestTest extends TestCase
{
    use RefreshDatabase;

    private int $admin;

    private array $form = ['first_name' => 'Ana', 'last_name' => 'Pérez', 'username' => 'ana.perez', 'email' => 'ana@azuay.cruzroja.org.ec', 'province' => 'azuay'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['institution.invitations_enabled' => true]);
        DB::table('usuarios')->insert(['provincia' => 'azuay', 'clave' => 'unused']);
        $this->admin = DB::table('accounts')->insertGetId(['username' => 'admin', 'name' => 'Admin', 'email' => 'admin@cruzroja.org.ec', 'role' => 'superadmin', 'password' => 'x', 'active' => true, 'legacy_access' => true]);
        DB::table('accounts')->insert(['username' => 'inactivo', 'name' => 'Admin inactivo', 'email' => 'inactivo@cruzroja.org.ec', 'role' => 'superadmin', 'password' => 'x', 'active' => false, 'legacy_access' => true]);
    }

    private function signIn(int $id): void
    {
        $user = Auth::getProvider()->retrieveById((string) $id);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    private function messages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    public function test_login_offers_registration_and_power_bi_only_when_configured(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('register').'"', $html);
        $this->assertStringNotContainsString('Power BI', $html);

        config(['institution.powerbi_url' => 'https://app.powerbi.com/view?r=demo']);
        $this->get('/login')->assertSee('href="https://app.powerbi.com/view?r=demo" target="_blank" rel="noopener noreferrer"', false);
        config(['institution.powerbi_url' => 'javascript:alert(1)']);
        $this->get('/login')->assertDontSee('Power BI');
    }

    public function test_request_is_stored_and_mailed_to_active_superadmins(): void
    {
        $this->get('/register')->assertOk()->assertSee('Selecciona tu provincia');
        $this->post('/register', $this->form)->assertRedirect(route('login'))->assertSessionHas('notice', RegistrationController::SENT_NOTICE);

        $this->assertDatabaseHas('registration_requests', ['username' => 'ana.perez', 'province' => 'azuay', 'status' => 'pending']);
        $this->assertCount(1, $this->messages());
        $message = $this->messages()->first()->getOriginalMessage();
        $this->assertSame(['admin@cruzroja.org.ec'], array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        $this->assertStringContainsString('Provincia: Azuay', $message->getTextBody());
        $this->assertStringContainsString('/users#solicitudes', $message->getTextBody());
    }

    public function test_existing_accounts_get_the_same_answer_without_a_new_request(): void
    {
        $this->post('/register', $this->form);
        $this->post('/register', ['email' => 'otra@azuay.cruzroja.org.ec'] + $this->form)->assertSessionHas('notice', RegistrationController::SENT_NOTICE);
        $this->post('/register', ['username' => 'admin', 'email' => 'nueva@azuay.cruzroja.org.ec'] + $this->form)->assertSessionHas('notice', RegistrationController::SENT_NOTICE);
        $this->assertDatabaseCount('registration_requests', 1);
        $this->assertCount(1, $this->messages());
    }

    public function test_email_must_belong_to_the_chosen_province(): void
    {
        $this->post('/register', ['email' => 'ana@pichincha.cruzroja.org.ec'] + $this->form)->assertRedirect('/register')->assertSessionHasErrors('email');
        $this->post('/register', ['email' => 'ana@gmail.com'] + $this->form)->assertSessionHasErrors('email');
        $this->post('/register', ['province' => 'narnia'] + $this->form)->assertSessionHasErrors('province');
        $this->assertDatabaseCount('registration_requests', 0);
        $this->post('/register', ['email' => 'ana@cruzroja.org.ec'] + $this->form)->assertSessionHasNoErrors();
    }

    public function test_superadmin_approves_and_the_applicant_receives_the_activation(): void
    {
        $this->post('/register', $this->form);
        $id = DB::table('registration_requests')->value('id');
        $this->signIn($this->admin);
        $this->get('/users')->assertSee('Solicitudes de registro: 1')->assertSee('Aprobar a Ana');

        $this->post('/users/requests/'.$id.'/approve')->assertRedirect(route('users.index'))->assertSessionHas('notice');
        $account = DB::table('accounts')->where('username', 'ana.perez')->first();
        $this->assertSame(['Ana Pérez', 'technician', 'azuay', 0], [$account->name, $account->role, $account->province, (int) $account->active]);
        $this->assertDatabaseHas('registration_requests', ['id' => $id, 'status' => 'approved', 'reviewed_by' => $this->admin]);
        $this->assertDatabaseHas('account_invitations', ['account_id' => $account->id]);
        $this->assertSame('ana@azuay.cruzroja.org.ec', $this->messages()->last()->getOriginalMessage()->getTo()[0]->getAddress());

        $this->post('/users/requests/'.$id.'/approve')->assertSessionHasErrors('registration');
        $this->assertDatabaseCount('accounts', 3);
        $this->get('/users')->assertDontSee('Solicitudes de registro');
    }

    public function test_rejection_creates_nothing_and_technicians_cannot_review(): void
    {
        $this->post('/register', $this->form);
        $id = DB::table('registration_requests')->value('id');
        $operator = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'tec', 'nombre' => 'Técnico', 'perfil' => 'p', 'clave' => 'x']);
        $technician = DB::table('accounts')->insertGetId(['username' => 'tec', 'name' => 'Técnico', 'province' => 'azuay', 'operator_id' => $operator, 'password' => 'x', 'active' => true, 'legacy_access' => true]);
        $this->signIn($technician);
        $this->post('/users/requests/'.$id.'/approve')->assertForbidden();

        $this->signIn($this->admin);
        $this->post('/users/requests/'.$id.'/reject')->assertSessionHas('notice');
        $this->assertDatabaseHas('registration_requests', ['id' => $id, 'status' => 'rejected']);
        $this->assertDatabaseMissing('accounts', ['username' => 'ana.perez']);
    }
}

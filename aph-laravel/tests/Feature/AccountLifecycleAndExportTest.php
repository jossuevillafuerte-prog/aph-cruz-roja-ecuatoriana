<?php

namespace Tests\Feature;

use App\Security\Security;
use App\Services\AccountInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class AccountLifecycleAndExportTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Xq7!Rtabab';

    private int $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(str_repeat('A', 35).':0')]);
        foreach (['azuay', 'pichincha'] as $province) {
            DB::table('usuarios')->insert(['provincia' => $province, 'clave' => 'unused']);
        }
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        $operator = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'tecnico', 'nombre' => 'Técnico', 'perfil' => 'p', 'clave' => $hash, 'activo' => 0]);
        $this->technician = DB::table('accounts')->insertGetId(['username' => 'tecnico', 'name' => 'Técnico', 'email' => 'tecnico@azuay.cruzroja.org.ec', 'province' => 'azuay', 'operator_id' => $operator, 'password' => $hash, 'active' => false, 'legacy_access' => false]);
    }

    private function signIn(int $id): void
    {
        $user = Auth::getProvider()->retrieveById((string) $id);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    private function activateTechnician(): void
    {
        DB::table('accounts')->where('id', $this->technician)->update(['active' => true, 'legacy_access' => true]);
        $this->signIn($this->technician);
    }

    private function invitation(string $code = '123456', int $minutes = 10): string
    {
        $ticket = bin2hex(random_bytes(32));
        DB::table('account_invitations')->updateOrInsert(['account_id' => $this->technician], ['ticket_hash' => hash('sha256', $ticket), 'code_hash' => AccountInvitations::hash($code), 'attempts' => 0, 'expires_at' => now()->addMinutes($minutes), 'sent_at' => now()]);

        return '/activate/'.$ticket;
    }

    /**
     * @return array{code: string, password: string, password_confirmation: string}
     */
    private function activationInput(string $code = '123456', string $password = self::PASSWORD): array
    {
        return ['code' => $code, 'password' => $password, 'password_confirmation' => $password];
    }

    private function assertStillInactive(): void
    {
        $this->assertDatabaseHas('accounts', ['id' => $this->technician, 'active' => 0, 'email_verified_at' => null]);
    }

    public function test_activation_enables_the_account_only_once(): void
    {
        $url = $this->invitation();
        DB::table('sessions')->insert(['id' => 'previa', 'user_id' => (string) $this->technician, 'payload' => 'x', 'last_activity' => time()]);
        $this->get($url)->assertOk()->assertSee('Activar cuenta');
        $this->post($url, $this->activationInput())->assertRedirect('/login');
        $account = DB::table('accounts')->find($this->technician);
        $this->assertSame([1, 0], [$account->active, $account->legacy_access]);
        $this->assertNotNull($account->email_verified_at);
        $this->assertTrue(password_verify(self::PASSWORD, $account->password));
        $this->assertDatabaseHas('operadores', ['id' => $account->operator_id, 'activo' => 1, 'clave' => $account->password]);
        $this->assertDatabaseCount('account_invitations', 0);
        $this->assertDatabaseMissing('sessions', ['id' => 'previa']);
        $this->assertDatabaseHas('security_events', ['event' => 'account_activated', 'identity' => (string) $this->technician]);
        $this->post($url, $this->activationInput())->assertSessionHasErrors('code');
        $this->post('/login', ['usuario' => 'tecnico', 'password' => self::PASSWORD])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_activation_rejects_wrong_expired_weak_and_suspended_cases(): void
    {
        $url = $this->invitation('123456', -1);
        $this->post($url, $this->activationInput())->assertSessionHasErrors('code');
        $url = $this->invitation();
        $this->post($url, $this->activationInput('123456', 'debil123'))->assertSessionHasErrors('password');
        $this->post($url, ['password_confirmation' => 'Otra@1234'] + $this->activationInput())->assertSessionHasErrors('password');
        $this->assertStillInactive();
        $url = $this->invitation();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($url, $this->activationInput('000000'))->assertSessionHasErrors('code');
        }
        $this->assertStillInactive();
    }

    public function test_five_wrong_codes_block_even_the_correct_one(): void
    {
        $url = $this->invitation();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($url, $this->activationInput('000000'))->assertSessionHasErrors('code');
        }
        $this->post($url, $this->activationInput())->assertSessionHasErrors('code');
        $this->assertStillInactive();
    }

    public function test_suspended_account_cannot_activate(): void
    {
        $url = $this->invitation();
        DB::table('accounts')->where('id', $this->technician)->update(['disabled_at' => now()]);
        $this->post($url, $this->activationInput())->assertSessionHasErrors('code');
        $this->assertStillInactive();
        $this->get('/activate/no-valido')->assertNotFound();
    }

    public function test_invitation_is_delivered_and_limited(): void
    {
        config(['institution.invitations_enabled' => true]);
        $admin = DB::table('accounts')->insertGetId(['username' => 'admin', 'name' => 'Admin', 'role' => 'superadmin', 'password' => 'x', 'active' => true, 'legacy_access' => true]);
        $this->signIn($admin);
        $this->post('/users/'.$this->technician.'/invite')->assertRedirect(route('users.index'))->assertSessionHas('notice');
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('tecnico@azuay.cruzroja.org.ec', $message->getTo()[0]->getAddress());
        $this->assertSame(1, preg_match('#/activate/([a-f0-9]{64})\s.*?(\d{6})#s', $message->getTextBody(), $match));
        $this->assertDatabaseHas('account_invitations', ['account_id' => $this->technician, 'ticket_hash' => hash('sha256', $match[1]), 'code_hash' => AccountInvitations::hash($match[2])]);
        $this->assertDatabaseMissing('account_invitations', ['code_hash' => $match[2]]);
        $this->post('/users/'.$this->technician.'/invite')->assertSessionHasErrors('email');
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_profile_password_change_validates_and_revokes_other_sessions(): void
    {
        $this->activateTechnician();
        $input = ['_token' => csrf_token(), 'nombre' => 'Técnico', 'clave_actual' => 'Prueba@7', 'clave_nueva' => self::PASSWORD, 'clave_repetida' => self::PASSWORD];
        $this->post('/?vista=perfil', ['clave_actual' => 'incorrecta'] + $input)->assertStatus(422)->assertSee('La contraseña actual no es correcta');
        $this->post('/?vista=perfil', ['clave_repetida' => 'Distinta@9'] + $input)->assertStatus(422)->assertSee('La confirmación');
        $this->post('/?vista=perfil', ['clave_nueva' => 'debil', 'clave_repetida' => 'debil'] + $input)->assertStatus(422);
        $this->assertTrue(password_verify('Prueba@7', DB::table('accounts')->where('id', $this->technician)->value('password')));

        DB::table('sessions')->insert(['id' => 'otro-equipo', 'user_id' => (string) $this->technician, 'payload' => 'x', 'last_activity' => time()]);
        $this->post('/?vista=perfil', $input)->assertStatus(303);
        $account = DB::table('accounts')->find($this->technician);
        $this->assertTrue(password_verify(self::PASSWORD, $account->password));
        $this->assertSame(1, $account->revision);
        $this->assertDatabaseMissing('sessions', ['id' => 'otro-equipo']);
        $this->assertDatabaseHas('security_events', ['event' => 'password_changed']);
        $this->assertSame(Security::stamp(Auth::getProvider()->retrieveById((string) $this->technician)), session('credential'));
    }

    public function test_changed_credentials_close_a_live_session(): void
    {
        $this->activateTechnician();
        $this->get('/security')->assertOk();
        DB::table('accounts')->where('id', $this->technician)->increment('revision');
        Auth::setUser(Auth::getProvider()->retrieveById((string) $this->technician));
        $this->get('/security')->assertRedirect('/login');
        $this->assertDatabaseHas('security_events', ['event' => 'credential_revoked']);
        $this->assertGuest();
    }

    public function test_password_service_failure_rejects_the_change(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('', 503)]);
        $this->activateTechnician();
        $this->post('/?vista=perfil', ['_token' => csrf_token(), 'nombre' => 'Técnico', 'clave_actual' => 'Prueba@7', 'clave_nueva' => self::PASSWORD, 'clave_repetida' => self::PASSWORD])->assertStatus(422)->assertSee('No se pudo verificar');
        $this->assertTrue(password_verify('Prueba@7', DB::table('accounts')->where('id', $this->technician)->value('password')));
    }

    public function test_excel_contains_only_closed_records_of_the_province_as_text(): void
    {
        require_once base_path('legacy/modelo.php');
        $insert = function (string $sheet, array $columns = [], array $values = []): void {
            $data = ['K' => $sheet, 'B' => '2026-11-20'] + $values + array_fill_keys(array_keys(FIELDS), '');
            DB::table('atenciones')->insert($columns + ['version' => 1, 'estado' => 'Cerrada', 'provincia' => 'azuay', 'datos' => json_encode($data)]);
        };
        $insert('HOJA-OK', [], ['L' => '=HYPERLINK("http://example.test")', 'T' => 'Dolor <agudo> & "fiebre"', 'P' => '34', 'Y' => 'Técnico (tecnico)']);
        $insert('HOJA-PENDIENTE', ['estado' => 'Pendiente']);
        $insert('HOJA-ELIMINADA', ['eliminado' => date('c')]);
        $insert('HOJA-AJENA', ['provincia' => 'pichincha']);
        $this->activateTechnician();
        $response = $this->get('/?vista=exportar')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'aph-test');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
        $this->assertCount(2, $sheet->sheetData->row);
        $cells = [];
        foreach ($sheet->sheetData->row[1]->c as $cell) {
            $cells[(string) $cell['r']] = ['type' => (string) $cell['t'], 'value' => (string) ($cell->is->t ?? ''), 'number' => (string) ($cell->v ?? ''), 'formula' => isset($cell->f)];
        }
        $this->assertSame('HOJA-OK', $cells['K2']['value']);
        $this->assertSame(['inlineStr', '=HYPERLINK("http://example.test")', false], [$cells['L2']['type'], $cells['L2']['value'], $cells['L2']['formula']]);
        $this->assertSame('Dolor <agudo> & "fiebre"', $cells['T2']['value']);
        $this->assertSame('34', $cells['P2']['number']);
        $this->assertSame('Técnico (tecnico)', $cells['Y2']['value']);
    }
}

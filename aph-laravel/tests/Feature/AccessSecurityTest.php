<?php

namespace Tests\Feature;

use App\Security\PasswordPolicy;
use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected int $tech;

    protected int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('aph_test', DB::connection()->getDatabaseName());
        foreach (['azuay', 'pichincha'] as $p) {
            DB::table('usuarios')->insert(['provincia' => $p, 'clave' => 'unused']);
        }
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        $op = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'azuay', 'nombre' => 'Técnico Azuay', 'perfil' => 'Técnico provincial APH', 'clave' => $hash, 'activo' => 1, 'revision' => 0]);
        $this->tech = DB::table('accounts')->insertGetId(['username' => 'azuay', 'name' => 'Técnico Azuay', 'province' => 'azuay', 'operator_id' => $op, 'password' => $hash, 'active' => true, 'legacy_access' => true]);
        $this->admin = DB::table('accounts')->insertGetId(['username' => 'manejoinformacion', 'name' => 'Cuenta nacional', 'email' => 'manejoinformacion@cruzroja.org.ec', 'role' => 'superadmin', 'password' => $hash, 'active' => true, 'legacy_access' => true]);
    }

    private function signed(bool $admin = false, array $extra = [])
    {
        $u = Auth::getProvider()->retrieveById((string) ($admin ? $this->admin : $this->tech));

        return $this->actingAs($u)->withSession(array_merge(['credential' => Security::stamp($u), 'authenticated_at' => time(), 'last_activity' => time()], $extra));
    }

    public function test_login_needs_only_username_and_password(): void
    {
        $this->post('/login', ['usuario' => 'AZUAY', 'password' => 'Prueba@7'])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/mfa')->assertNotFound();
    }

    public function test_national_login(): void
    {
        $this->post('/login', ['usuario' => 'manejoinformacion', 'password' => 'Prueba@7'])->assertRedirect('/national');
    }

    public function test_anonymous_cannot_reach_data(): void
    {
        foreach (['/users', '/national', '/?vista=atenciones', '/?vista=exportar', '/?vista=geojson'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_technician_cannot_manage_users(): void
    {
        $this->signed()->get('/users')->assertForbidden();
        $this->post('/users', ['name' => 'No'])->assertForbidden();
        $this->get('/national')->assertForbidden();
    }

    public function test_technician_can_edit_other_operators_ficha_in_own_province(): void
    {
        require_once base_path('legacy/modelo.php');
        $data = array_fill_keys(array_keys(FIELDS), '');
        $data['B'] = '2026-11-10';
        $data['K'] = 'TEST';
        $data['Y'] = 'Otro responsable';
        $id = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'azuay']);
        $this->signed()->get('/?id='.$id)->assertOk()->assertSee('TEST');
        $this->get('/?vista=recursos')->assertOk();
        DB::table('atenciones')->where('id', $id)->update(['provincia' => 'pichincha']);
        $this->get('/?id='.$id)->assertNotFound();
    }

    public function test_admin_can_see_all_users_and_national_data(): void
    {
        $this->signed(true)->get('/users')->assertOk()->assertSee('Usuarios nacionales')->assertSee('Activo');
        $this->get('/national')->assertOk()->assertSee('Panel principal');
    }

    public function test_national_records_ignore_previous_scope_and_can_filter(): void
    {
        require_once base_path('legacy/modelo.php');
        foreach (['azuay' => 'HOJA-AZUAY', 'pichincha' => 'HOJA-PICHINCHA'] as $province => $sheet) {
            $data = array_fill_keys(array_keys(FIELDS), '');
            $data['K'] = $sheet;
            DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => $province]);
        }
        $this->signed(true, ['scope_province' => 'azuay'])->get('/national?view=atenciones')->assertOk()->assertSee('HOJA-AZUAY')->assertSee('HOJA-PICHINCHA')->assertSee('menu-open', false);
        $this->get('/national?view=atenciones&province=pichincha')->assertOk()->assertSee('HOJA-PICHINCHA')->assertDontSee('HOJA-AZUAY');
        $this->get('/national?view=atenciones&province=')->assertOk()->assertSee('HOJA-PICHINCHA')->assertSee('HOJA-AZUAY');
        $this->get('/national?province=invalid')->assertSessionHasErrors('province');
        $this->get('/?vista=atenciones')->assertRedirect('/national?view=atenciones');
    }

    public function test_national_navigation_without_selected_province(): void
    {
        $this->signed(true)->get('/national')->assertOk()
            ->assertSee('href="/national?view=atenciones"', false)
            ->assertSee('dashboard-shortcuts', false)->assertDontSee('sheet-filter', false);
        $this->get('/?vista=perfil')->assertOk()->assertSee('clave_actual', false);
        $this->get('/?vista=nueva')->assertOk()->assertSee('Selecciona la provincia');
        $this->get('/?vista=recursos')->assertOk()->assertSee('Selecciona la provincia');
        $this->post('/scope', ['province' => 'pichincha', 'next' => 'nueva'])->assertRedirect('/?vista=nueva');
        $this->get('/?vista=nueva')->assertOk()->assertSee('f-K', false)->assertSee('Pichincha');
        $this->post('/scope', ['province' => 'azuay', 'next' => 'recursos'])->assertRedirect('/?vista=recursos');
        $this->get('/?vista=recursos')->assertOk()->assertSee('Recursos');
        $this->post('/scope', ['province' => 'azuay', 'next' => 'https://example.com'])->assertSessionHasErrors('next');
    }

    public function test_deletion_is_scoped_and_audited_for_both_roles(): void
    {
        require_once base_path('legacy/modelo.php');
        $data = array_fill_keys(array_keys(FIELDS), '');
        $data['K'] = 'DELETE-TEST';
        $data['Y'] = 'Otro técnico';
        $own = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'azuay']);
        $other = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'pichincha']);
        $this->signed()->get('/?vista=eliminar&id='.$own)->assertOk()->assertSee('Confirmar eliminación');
        $this->post('/?vista=eliminar&id='.$other, ['_token' => csrf_token(), 'version' => '1', '_province' => 'pichincha'])->assertNotFound();
        $this->post('/?vista=eliminar&id='.$own, ['_token' => csrf_token(), 'version' => '0'])->assertStatus(409);
        $this->post('/?vista=eliminar&id='.$own, ['_token' => csrf_token(), 'version' => '1'])->assertStatus(303);
        $this->assertNotNull(DB::table('atenciones')->where('id', $own)->value('eliminado'));
        $this->assertDatabaseHas('cambios', ['atencion' => $own, 'autor' => 'Técnico Azuay (azuay)', 'motivo' => 'Eliminación de atención']);
        $this->get('/?id='.$own)->assertNotFound();
        $this->signed(true)->post('/?vista=eliminar&id='.$other, ['_token' => csrf_token(), 'version' => '1', '_province' => 'pichincha'])->assertRedirect('/national?view=atenciones&eliminada=1');
        $this->assertDatabaseHas('cambios', ['atencion' => $other, 'autor' => 'Cuenta nacional (manejoinformacion)', 'motivo' => 'Eliminación de atención']);
    }

    public function test_user_editor_preserves_history_and_revokes_old_access(): void
    {
        Http::fake(['*' => Http::response(str_repeat('A', 35).':0', 200)]);
        $old = DB::table('accounts')->find($this->tech);
        $ficha = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => '{}', 'provincia' => 'azuay', 'responsable_id' => $old->operator_id]);
        DB::table('sessions')->insert(['id' => 'editor-session', 'user_id' => (string) $this->tech, 'payload' => 'dummy', 'last_activity' => time()]);
        $this->signed(true)->post('/users/'.$this->tech, ['action' => 'edit', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@pichincha.cruzroja.org.ec', 'password' => 'Xq7!Rtabab', 'role' => 'technician', 'province' => 'pichincha'])->assertRedirect();
        $updated = DB::table('accounts')->find($this->tech);
        $this->assertSame('Ana Pérez', $updated->name);
        $this->assertSame('Pérez', $updated->last_name);
        $this->assertSame('pichincha', $updated->province);
        $this->assertTrue(password_verify('Xq7!Rtabab', $updated->password));
        $this->assertNotEquals($old->operator_id, $updated->operator_id);
        $this->assertDatabaseHas('atenciones', ['id' => $ficha, 'provincia' => 'azuay', 'responsable_id' => $old->operator_id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'editor-session']);
        $this->assertDatabaseHas('security_events', ['event' => 'account_password_reset', 'target' => 'account:'.$this->tech]);
        $this->assertDatabaseHas('security_events', ['event' => 'account_permissions_changed', 'target' => 'account:'.$this->tech]);
        $this->signed()->get('/?id='.$ficha)->assertNotFound();
    }

    public function test_user_editor_validates_permissions_and_preserves_blank_password(): void
    {
        $hash = DB::table('accounts')->where('id', $this->tech)->value('password');
        $input = ['action' => 'edit', 'first_name' => 'Ana', 'last_name' => '', 'email' => null, 'password' => '', 'role' => 'technician', 'province' => 'azuay'];
        $this->signed()->post('/users/'.$this->tech, $input)->assertForbidden();
        $this->signed(true)->post('/users/'.$this->tech, array_replace($input, ['role' => 'owner']))->assertSessionHasErrors('role');
        $this->post('/users/'.$this->tech, array_replace($input, ['province' => 'invalid']))->assertSessionHasErrors('province');
        $this->post('/users/'.$this->tech, array_replace($input, ['password' => 'abc']))->assertSessionHasErrors('password');
        $this->post('/users/'.$this->admin, $input)->assertSessionHasErrors('role');
        $this->post('/users/'.$this->tech, $input)->assertRedirect();
        $this->assertSame($hash, DB::table('accounts')->where('id', $this->tech)->value('password'));
        $this->post('/users/'.$this->tech, array_replace($input, ['role' => 'superadmin', 'province' => null]))->assertRedirect();
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'role' => 'superadmin', 'province' => null, 'operator_id' => null]);
    }

    public function test_navigation_header_and_module_order_are_consistent(): void
    {
        $this->signed(true);
        foreach (['/national', '/national?view=atenciones', '/users', '/security', '/?vista=recursos&select_province=1', '/?vista=perfil'] as $url) {
            $html = $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'strict-origin')->getContent();
            preg_match('/<header.*?<\/header>/s', $html, $header);
            $this->assertStringContainsString('action="/logout"', $header[0]);
            preg_match('/<nav aria-label="Menú principal">(.*?)<\/nav>/s', $html, $menu);
            if ($url === '/?vista=perfil') {
                $this->assertStringContainsString('href="/security"', $menu[1]);
            } else {
                $this->assertStringNotContainsString('href="/security"', $menu[1]);
            }
            if ($url !== '/national') {
                $this->assertLessThan(strpos($html, '<h1>'), strpos($html, 'Volver al panel principal'));
            }
        }
    }

    public function test_attention_summaries_are_provincial_and_exclude_deleted_records(): void
    {
        require_once base_path('legacy/modelo.php');
        $data = array_fill_keys(array_keys(FIELDS), '');
        $data['K'] = 'KPI-PENDING';
        $pending = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'azuay']);
        $data['K'] = 'KPI-CLOSED';
        DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Cerrada', 'datos' => json_encode($data), 'provincia' => 'azuay']);
        DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'pichincha']);
        DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'azuay', 'eliminado' => now()]);
        $response = $this->signed(true)->get('/national?view=atenciones&province=azuay&estado=Pendiente');
        $response->assertOk()->assertViewHas('total', 2)->assertViewHas('counts', fn ($v) => (int) $v['Cerrada'] === 1 && (int) $v['Pendiente'] === 1)->assertViewHas('rows', fn ($rows) => $rows->total() === 1)->assertSee('KPI-PENDING')->assertDontSee('KPI-CLOSED');
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'national-province'), strpos($html, 'attention-kpis'));
        $response->assertSee('class="attention-pending"', false);
        $this->get('/?id='.$pending.'&return_province=azuay&return_estado=Pendiente&return_page=2')->assertOk()->assertSee('Volver a atenciones')->assertSee('/national?view=atenciones&amp;province=azuay&amp;estado=Pendiente&amp;page=2', false);
        $this->get('/national?view=atenciones&estado=invalid')->assertSessionHasErrors('estado');
        $this->signed()->get('/?vista=atenciones&estado=Cerrada')->assertOk()->assertSee('KPI-CLOSED')->assertDontSee('KPI-PENDING')->assertSee('data-count="2"', false)->assertDontSee('<tr class="attention-pending">', false);
        $this->get('/?id='.$pending.'&return_estado=Pendiente')->assertOk()->assertSee('Volver a atenciones')->assertSee('/?vista=atenciones&amp;estado=Pendiente', false);
        $this->get('/?vista=nueva')->assertOk()->assertSee('Volver al panel principal')->assertDontSee('Volver a atenciones');
    }

    public function test_user_update_with_origin_only_referer_redirects_explicitly_and_persists(): void
    {
        $email = 'same@azuay.cruzroja.org.ec';
        DB::table('accounts')->where('id', $this->tech)->update(['email' => $email]);
        $originalHash = DB::table('accounts')->where('id', $this->tech)->value('password');
        $input = ['action' => 'edit', 'first_name' => 'Nombre cambiado', 'last_name' => 'Apellido', 'email' => $email, 'password' => '', 'role' => 'technician', 'province' => 'azuay'];
        $this->signed(true)->withHeader('Referer', 'http://localhost/')->put('/users/'.$this->tech, $input)->assertRedirect(route('users.index'))->assertSessionHas('notice');
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'name' => 'Nombre cambiado Apellido', 'email' => $email, 'password' => $originalHash]);
        $this->put('/users/'.$this->tech, array_replace($input, ['email' => 'fuera@gmail.com', 'password' => 'abc']))->assertRedirect(route('users.index'))->assertSessionHasErrors(['email', 'password'])->assertSessionHas('_old_input.edit_user_id', (string) $this->tech)->assertSessionMissing('_old_input.password');
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'email' => $email, 'password' => $originalHash]);
    }

    public function test_editing_own_account_keeps_current_session_and_revokes_others(): void
    {
        DB::table('sessions')->insert(['id' => 'other-admin-session', 'user_id' => (string) $this->admin, 'payload' => 'dummy', 'last_activity' => time()]);
        $this->signed(true)->withHeader('Referer', 'http://localhost/')->put('/users/'.$this->admin, ['action' => 'edit', 'first_name' => 'Cuenta', 'last_name' => 'Actualizada', 'email' => 'manejoinformacion@cruzroja.org.ec', 'password' => '', 'role' => 'superadmin'])->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-admin-session']);
        $this->assertSame(Security::stamp(Auth::getProvider()->retrieveById((string) $this->admin)), session('credential'));
        $this->get('/users')->assertOk()->assertSee('Cuenta Actualizada');
    }

    public function test_deactivation_revokes_sessions(): void
    {
        DB::table('sessions')->insert(['id' => 'testsession', 'user_id' => (string) $this->tech, 'payload' => 'dummy', 'last_activity' => time()]);
        $this->signed(true)->post('/users/'.$this->tech, ['action' => 'state', 'active' => '0'])->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'testsession']);
        $this->assertNull(Auth::getProvider()->retrieveById((string) $this->tech));
    }

    public function test_cannot_disable_own_superadmin(): void
    {
        $this->signed(true)->post('/users/'.$this->admin, ['action' => 'state', 'active' => '0'])->assertStatus(422);
        $this->assertDatabaseHas('accounts', ['id' => $this->admin, 'active' => 1]);
    }

    public function test_exact_institutional_domains_and_pending_users(): void
    {
        $this->signed(true);
        foreach (['persona@gmail.com', 'persona@cruzroja.org.ec.fake.com', 'persona@fakecruzroja.org.ec', 'persona@carchi.cruzroja.org'] as $email) {
            $this->post('/users', ['username' => 'nuevo', 'name' => 'Nuevo', 'province' => 'azuay', 'email' => $email])->assertSessionHasErrors('email');
        }
        $this->post('/users', ['username' => 'nuevo', 'name' => 'Nuevo', 'province' => 'azuay', 'email' => 'persona@caniar.cruzroja.org.ec'])->assertRedirect('/users');
        $this->assertDatabaseHas('accounts', ['username' => 'nuevo', 'active' => 0, 'legacy_access' => 0]);
        $this->assertNull(Auth::getProvider()->retrieveByCredentials(['usuario' => 'nuevo']));
    }

    public function test_invitation_disabled_does_not_send_or_create_code(): void
    {
        Mail::fake();
        $this->signed(true)->post('/users/'.$this->tech.'/invite')->assertSessionHas('notice');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('account_invitations', 0);
    }

    public function test_timeouts_and_background_polling(): void
    {
        $this->signed(false, ['last_activity' => time() - 600])->getJson('/session/status')->assertUnauthorized();
        $this->signed(false, ['authenticated_at' => time() - 28800])->getJson('/session/status')->assertUnauthorized();
        $t = time() - 400;
        $this->signed(false, ['last_activity' => $t])->getJson('/session/status')->assertJsonPath('idle_until', $t + 600);
        $this->assertSame($t, session('last_activity'));
    }

    public function test_logout_and_login_throttle(): void
    {
        $this->signed()->post('/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['usuario' => 'azuay', 'password' => 'wrong']);
        }$this->post('/login', ['usuario' => 'azuay', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_form_errors_return_to_their_page_with_origin_only_referer(): void
    {
        $this->withHeader('Referer', 'http://localhost/');
        $this->post('/login', ['usuario' => 'azuay', 'password' => 'wrong'])->assertRedirect(route('login'));
        $this->get('/login')->assertSee('Los datos de acceso no son correctos.');
        $this->post('/login', [])->assertRedirect(route('login'))->assertSessionHasErrors(['usuario', 'password']);
        $activation = '/activate/'.str_repeat('a', 64);
        $this->post($activation, [])->assertRedirect($activation)->assertSessionHasErrors('code');
        $this->post($activation, ['code' => '123456', 'password' => 'Prueba@7', 'password_confirmation' => 'Prueba@7'])->assertRedirect($activation)->assertSessionHasErrors('code');
        $this->signed()->post('/security/revoke', ['password' => 'wrong'])->assertRedirect('/security')->assertSessionHasErrors('password');
        $this->signed(true)->post('/users', ['username' => 'nuevo'])->assertRedirect('/users')->assertSessionHasErrors('email');
        $this->post('/users/'.$this->tech.'/invite')->assertRedirect(route('users.index'))->assertSessionHas('notice');
        $this->post('/scope', ['province' => 'invalid'])->assertRedirect('/national')->assertSessionHasErrors('province');
    }

    public function test_expired_session_reports_unsaved_changes(): void
    {
        $this->post('/?vista=nueva')->assertRedirect('/login')->assertSessionHas('notice', Security::UNSAVED_NOTICE);
        $this->signed(false, ['last_activity' => time() - 600])->post('/?vista=nueva')->assertRedirect('/login')->assertSessionHas('notice', Security::UNSAVED_NOTICE);
        $this->signed(false, ['last_activity' => time() - 600])->get('/')->assertRedirect('/login')->assertSessionHas('notice', 'La sesión terminó. Vuelve a ingresar.');
        $this->signed()->post('/logout')->assertRedirect('/login')->assertSessionHas('notice', 'La sesión terminó. Vuelve a ingresar.');
    }

    public function test_session_status_identifies_user_and_current_token(): void
    {
        $this->signed()->getJson('/session/status')->assertOk()->assertJsonPath('user', (string) $this->tech)->assertJsonPath('csrf_token', csrf_token());
        $this->get('/security')->assertSee('<meta name="session-user" content="'.$this->tech.'">', false);
        $this->get('/?vista=recursos')->assertSee('<meta name="session-user" content="'.$this->tech.'">', false);
    }

    public function test_login_limit_cannot_be_bypassed_with_accented_usernames(): void
    {
        foreach (['azuáy', 'ázuay', 'àzuay'] as $variant) {
            $this->assertNull(Auth::getProvider()->retrieveByCredentials(['usuario' => $variant]));
            $this->post('/login', ['usuario' => $variant, 'password' => 'Prueba@7'])->assertSessionHasErrors('login');
            $this->assertGuest();
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['usuario' => 'azuay', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['usuario' => 'AZUAY', 'password' => 'Prueba@7'])->assertStatus(429);
        $this->assertGuest();
        $this->assertSame(5, DB::table('security_events')->where('event', 'login_failed')->where('target', 'account:'.$this->tech)->count());
        $this->assertSame(1, DB::table('security_events')->where('event', 'login_throttled')->where('target', 'account:'.$this->tech)->count());
    }

    public function test_only_superadmin_cannot_lose_access_by_changing_email(): void
    {
        config(['institution.invitations_enabled' => true]);
        DB::table('accounts')->where('id', $this->admin)->update(['legacy_access' => false, 'email_verified_at' => now()]);
        $input = ['action' => 'edit', 'first_name' => 'Cuenta', 'email' => 'nuevo@cruzroja.org.ec', 'password' => '', 'role' => 'superadmin'];
        $this->signed(true)->put('/users/'.$this->admin, $input)->assertRedirect(route('users.index'))->assertSessionHasErrors('email');
        $this->assertDatabaseHas('accounts', ['id' => $this->admin, 'email' => 'manejoinformacion@cruzroja.org.ec', 'active' => 1]);

        DB::table('accounts')->where('id', $this->tech)->update(['role' => 'superadmin', 'province' => null, 'operator_id' => null]);
        $this->put('/users/'.$this->admin, $input)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $this->admin, 'email' => 'nuevo@cruzroja.org.ec', 'active' => 0]);
    }

    public function test_verified_email_cannot_change_while_invitations_are_disabled(): void
    {
        config(['institution.invitations_enabled' => false]);
        DB::table('accounts')->where('id', $this->tech)->update(['legacy_access' => false, 'email' => 'a@azuay.cruzroja.org.ec', 'email_verified_at' => now()]);
        $input = ['action' => 'edit', 'first_name' => 'Ana', 'email' => 'b@azuay.cruzroja.org.ec', 'password' => '', 'role' => 'technician', 'province' => 'azuay'];
        $this->signed(true)->put('/users/'.$this->tech, $input)->assertSessionHasErrors('email');
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'email' => 'a@azuay.cruzroja.org.ec', 'active' => 1]);

        config(['institution.invitations_enabled' => true]);
        $this->put('/users/'.$this->tech, $input)->assertSessionHasNoErrors()->assertSessionHas('notice', fn (string $notice): bool => str_contains($notice, 'inactiva hasta verificar'));
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'email' => 'b@azuay.cruzroja.org.ec', 'active' => 0]);
    }

    public function test_last_usable_superadmin_cannot_be_disabled_or_demoted(): void
    {
        $other = DB::table('accounts')->insertGetId(['username' => 'otro.nacional', 'name' => 'Otro', 'email' => 'otro@cruzroja.org.ec', 'role' => 'superadmin', 'password' => 'x', 'active' => true, 'legacy_access' => true]);
        $this->signed(true);
        DB::table('accounts')->where('id', $this->admin)->update(['role' => 'technician']);
        $actor = Auth::user();
        $actor->role = 'superadmin';
        $this->actingAs($actor)->post('/users/'.$other, ['action' => 'state', 'active' => '0'])->assertSessionHasErrors('active');
        $this->put('/users/'.$other, ['action' => 'edit', 'first_name' => 'Otro', 'email' => 'otro@cruzroja.org.ec', 'password' => '', 'role' => 'technician', 'province' => 'azuay'])->assertSessionHasErrors('role');
        $this->assertDatabaseHas('accounts', ['id' => $other, 'role' => 'superadmin', 'active' => 1]);

        DB::table('accounts')->where('id', $this->admin)->update(['role' => 'superadmin']);
        $this->post('/users/'.$other, ['action' => 'state', 'active' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $other, 'active' => 0]);
    }

    public function test_suspended_unverified_account_can_return_to_pending(): void
    {
        DB::table('accounts')->where('id', $this->tech)->update(['legacy_access' => false, 'email' => 'a@azuay.cruzroja.org.ec', 'active' => false, 'disabled_at' => now()]);
        $this->signed(true)->post('/users/'.$this->tech, ['action' => 'state', 'active' => '1'])->assertSessionHasNoErrors()->assertSessionHas('notice');
        $this->assertDatabaseHas('accounts', ['id' => $this->tech, 'active' => 0, 'disabled_at' => null]);
        $this->assertNull(Auth::getProvider()->retrieveById((string) $this->tech));
        $this->post('/users/'.$this->tech, ['action' => 'state', 'active' => '1'])->assertSessionHasErrors('active');
    }

    public function test_new_technician_requires_an_explicit_province_and_keeps_it_after_errors(): void
    {
        $this->signed(true)->get('/users')->assertSee('<option value="">Selecciona una provincia</option>', false);
        $input = ['username' => 'nuevo', 'name' => 'Nuevo', 'email' => 'manejoinformacion@cruzroja.org.ec', 'province' => 'loja'];
        $this->post('/users', array_replace($input, ['province' => '']))->assertSessionHasErrors('province');
        $this->post('/users', $input)->assertRedirect('/users');
        $html = $this->get('/users')->assertOk()->assertSee('class="errors"', false)->getContent();
        $this->assertMatchesRegularExpression('/<details class="card"\s+open/', $html);
        $this->assertMatchesRegularExpression('/<option value="loja"\s+selected/', $html);
        $this->assertDatabaseMissing('accounts', ['username' => 'nuevo']);
    }

    public function test_password_rules_and_leak_filter(): void
    {
        Http::fake(['*' => Http::response(str_repeat('A', 35).':0', 200)]);
        $this->assertTrue(PasswordPolicy::accepts('Xq7!Rtab'));
        foreach (['abcdef1@', 'ABCDEFG1@', 'Abcdefg@', 'Abcdefg1', 'Ab1!', 'abcdef', 'Abc123', 'Ábc12!', 'Abñ12!', 'Ab 12!', 'Ab12🙂', 'Password1!'] as $bad) {
            $this->assertFalse(PasswordPolicy::accepts($bad));
        }
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(substr(strtoupper(sha1('Xq7!Rtab')), 5).':14', 200)]);
        $this->assertFalse(PasswordPolicy::accepts('Xq7!Rtab'));
    }

    public function test_https_and_headers(): void
    {
        $this->app->instance('env', 'production');
        config(['session.secure' => true]);
        $this->get('http://example.test/login')->assertForbidden();
        $this->get('https://example.test/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000')->assertHeader('X-Frame-Options', 'DENY');
    }
}

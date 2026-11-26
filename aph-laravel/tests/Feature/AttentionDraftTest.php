<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttentionDraftTest extends TestCase
{
    use RefreshDatabase;

    private int $azuay;

    private int $pichincha;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['azuay', 'pichincha'] as $province) {
            DB::table('usuarios')->insert(['provincia' => $province, 'clave' => 'unused']);
        }
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        foreach (['azuay', 'pichincha'] as $province) {
            $operator = DB::table('operadores')->insertGetId(['provincia' => $province, 'usuario' => $province, 'nombre' => 'Técnico', 'perfil' => 'p', 'clave' => $hash]);
            $this->{$province} = DB::table('accounts')->insertGetId(['username' => $province, 'name' => 'Técnico '.$province, 'province' => $province, 'operator_id' => $operator, 'password' => $hash, 'active' => true, 'legacy_access' => true]);
        }
    }

    private function signIn(int $id, int $lastActivity = 0): void
    {
        $user = Auth::getProvider()->retrieveById((string) $id);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => $lastActivity ?: time()]);
    }

    private function draft(string $key, array $fields): TestResponse
    {
        return $this->postJson('/drafts', ['key' => $key, 'version' => 0, 'fields' => $fields], ['X-CSRF-TOKEN' => csrf_token()]);
    }

    public function test_draft_is_encrypted_offered_and_removed_after_saving(): void
    {
        $this->signIn($this->azuay);
        $this->draft('nueva:azuay', ['B' => '2026-11-20', 'L' => 'Paciente Borrador', 'K' => 'BORRADOR-1', 'T' => '', 'recurso_id' => ''])->assertOk()->assertJsonStructure(['saved_at']);
        $stored = DB::table('drafts')->where('account_id', $this->azuay)->value('payload');
        $this->assertStringNotContainsString('Paciente Borrador', $stored);

        $html = $this->get('/?vista=nueva')->assertOk()->getContent();
        $this->assertStringContainsString('data-draft-key="nueva:azuay"', $html);
        $this->assertStringContainsString('Paciente Borrador', html_entity_decode($html));
        $this->assertStringContainsString('/attention-draft.js', $html);

        $this->post('/?vista=nueva', ['_token' => csrf_token(), 'version' => '0', 'accion' => 'guardar', 'B' => '2026-11-20', 'K' => 'BORRADOR-1'])->assertStatus(303);
        $this->assertDatabaseCount('drafts', 0);
    }

    public function test_drafts_respect_the_province_scope(): void
    {
        $other = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'provincia' => 'pichincha', 'datos' => '{}']);
        $this->signIn($this->azuay);
        $this->draft('nueva:pichincha', ['B' => '2026-11-20'])->assertForbidden();
        $this->draft('atencion:'.$other, ['B' => '2026-11-20'])->assertForbidden();
        $this->draft('otra:cosa', ['B' => '2026-11-20'])->assertForbidden();
        $this->draft('nueva:azuay', ['B' => ['x']])->assertStatus(422);
        $this->draft('nueva:azuay', ['bad-name!' => 'x'])->assertStatus(422);
        $this->assertDatabaseCount('drafts', 0);

        $this->signIn($this->pichincha);
        $this->draft('atencion:'.$other, ['B' => '2026-11-21'])->assertOk();
        $this->assertStringContainsString('data-draft="', $this->get('/?id='.$other)->getContent());
        $this->signIn($this->azuay);
        $this->assertStringNotContainsString('data-draft="', $this->get('/?vista=nueva')->getContent());
    }

    public function test_autosave_does_not_extend_an_idle_session_and_can_be_discarded(): void
    {
        $idleSince = time() - 400;
        $this->signIn($this->azuay, $idleSince);
        $this->draft('nueva:azuay', ['B' => '2026-11-20'])->assertOk();
        $this->assertSame($idleSince, session('last_activity'));
        $this->postJson('/drafts/discard', ['key' => 'nueva:azuay'], ['X-CSRF-TOKEN' => csrf_token()])->assertOk();
        $this->assertDatabaseCount('drafts', 0);
    }

    public function test_old_drafts_expire(): void
    {
        $this->signIn($this->azuay);
        $this->draft('nueva:azuay', ['B' => '2026-11-20'])->assertOk();
        DB::table('drafts')->update(['updated_at' => now()->subDays(8)]);
        $this->assertStringNotContainsString('data-draft="', $this->get('/?vista=nueva')->getContent());
        $this->signIn($this->pichincha);
        $this->draft('nueva:pichincha', ['B' => '2026-11-20'])->assertOk();
        $this->assertDatabaseMissing('drafts', ['account_id' => $this->azuay]);
    }

    public function test_status_tabs_and_keyboard_shortcuts(): void
    {
        $this->signIn($this->azuay);
        $html = $this->get('/?vista=atenciones&estado=Pendiente')->getContent();
        $this->assertStringContainsString('aria-label="Filtrar atenciones por estado"', $html);
        $this->assertMatchesRegularExpression('/data-state="Pendiente"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-state=""[^>]*aria-current/', $html);
        $this->assertStringNotContainsString('aria-pressed', $html);
        $form = $this->get('/?vista=nueva')->getContent();
        $this->assertStringContainsString('value="guardar" data-shortcut-save aria-keyshortcuts="Control+S"', $form);
        $this->assertStringContainsString('<kbd>Ctrl+S</kbd>', $form);
    }

    public function test_scripts_and_styles_carry_a_version_so_deploys_bypass_the_cache(): void
    {
        $version = filemtime(public_path('ubicaciones.js'));
        $this->signIn($this->azuay);
        $form = $this->get('/?vista=nueva')->getContent();
        $this->assertStringContainsString('src="/ubicaciones.js?v='.$version.'"', $form);
        $this->assertStringContainsString('href="/estilos.css?v=', $form);
        $this->assertStringNotContainsString('src="ubicaciones.js"', $form);
        $this->assertStringContainsString('href="/estilos.css?v=', $this->get('/security')->getContent());
    }
}

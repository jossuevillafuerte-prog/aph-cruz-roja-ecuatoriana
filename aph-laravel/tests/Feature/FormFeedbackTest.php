<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('usuarios')->insert(['provincia' => 'azuay', 'clave' => 'unused']);
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        $operator = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'azuay', 'nombre' => 'Técnico', 'perfil' => 'p', 'clave' => $hash]);
        DB::table('accounts')->insert(['username' => 'azuay', 'name' => 'Técnico', 'province' => 'azuay', 'operator_id' => $operator, 'password' => $hash, 'active' => true, 'legacy_access' => true]);
        DB::table('accounts')->insert(['username' => 'admin', 'name' => 'Admin', 'role' => 'superadmin', 'password' => $hash, 'active' => true, 'legacy_access' => true]);
    }

    private function signIn(string $username): void
    {
        $user = Auth::getProvider()->retrieveByCredentials(['usuario' => $username]);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    public function test_attention_errors_are_linked_to_each_field_with_readable_names(): void
    {
        $this->signIn('azuay');
        $html = $this->post('/?vista=nueva', ['_token' => csrf_token(), 'version' => '0', 'accion' => 'cerrar', 'B' => '2026-11-20', 'K' => 'HOJA-UX', 'P' => '150', 'M' => '', 'N' => 'abc'])
            ->assertStatus(422)->getContent();
        $this->assertStringContainsString('id="form-errors" tabindex="-1"', $html);
        $this->assertStringContainsString('<a href="#f-P">La edad debe ser un número entero entre 0 y 120.</a>', $html);
        $this->assertStringContainsString('<a href="#f-C">Completa «Código APH».</a>', $html);
        $this->assertStringContainsString('<a href="#f-G">Completa «Cantón».</a>', $html);
        $this->assertStringNotContainsString('Codigo_APH_Salud', $html);
        $this->assertMatchesRegularExpression('/id="f-P"[^>]*value="150"[^>]*aria-invalid="true" aria-describedby="f-P-error"/', $html);
        $this->assertStringContainsString('<p class="field-error" id="f-P-error">La edad debe ser un número entero entre 0 y 120.</p>', $html);
        $this->assertMatchesRegularExpression('/id="f-N"[^>]*aria-invalid="true" aria-describedby="documento-ayuda f-N-error"/', $html);
        $this->assertMatchesRegularExpression('/id="f-K"[^>]*value="HOJA-UX"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="f-B"[^>]*aria-invalid/', $html);
        $this->assertStringContainsString('/form-feedback.js', $html);
    }

    public function test_valid_attention_has_no_error_markup(): void
    {
        $this->signIn('azuay');
        $html = $this->get('/?vista=nueva')->assertOk()->getContent();
        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('form-errors', $html);
        $this->assertStringContainsString('aria-describedby="documento-ayuda"', $html);
    }

    public function test_login_keeps_the_username_and_marks_the_fields(): void
    {
        $this->post('/login', ['usuario' => 'azuay', 'password' => 'incorrecta'])->assertRedirect(route('login'));
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('El usuario o la contraseña no son correctos.', $html);
        $this->assertMatchesRegularExpression('/id="usuario"[^>]*value="azuay"[^>]*aria-invalid="true" aria-describedby="form-errors"/', $html);
        $this->assertMatchesRegularExpression('/id="password"[^>]*aria-invalid="true"[^>]*autofocus/', $html);
        $this->assertStringContainsString('id="form-errors" tabindex="-1"', $html);
        $this->assertStringNotContainsString('value="incorrecta"', $html);
        $this->get('/login')->assertDontSee('aria-invalid', false);
    }

    public function test_validation_messages_are_in_spanish_and_next_to_each_field(): void
    {
        $this->signIn('admin');
        $this->post('/users', ['username' => 'NO VALIDO', 'name' => '', 'email' => 'x', 'province' => ''])->assertRedirect('/users');
        $html = $this->get('/users')->assertOk()->getContent();
        foreach (['name' => 'Completa «nombre».', 'email' => 'Ingresa un correo institucional válido.', 'province' => 'Completa «provincia».', 'username' => 'El usuario debe tener de 3 a 40 caracteres'] as $field => $message) {
            $this->assertMatchesRegularExpression('/id="new-'.$field.'"[^>]*aria-invalid="true" aria-describedby="new-'.$field.'-error"/', $html);
            $this->assertStringContainsString('<p class="field-error" id="new-'.$field.'-error">'.htmlspecialchars($message, ENT_QUOTES), $html);
        }
        $this->assertStringContainsString('<label for="new-email">Correo institucional</label>', $html);
        $this->assertStringNotContainsString('The ', strip_tags($html));
    }

    public function test_activation_errors_are_next_to_the_code(): void
    {
        $url = '/activate/'.str_repeat('a', 64);
        $this->post($url, ['code' => '12', 'password' => 'x', 'password_confirmation' => 'x'])->assertRedirect($url);
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('<p class="field-error" id="code-error">«código» debe tener 6 dígitos.</p>', $html);
        $this->assertMatchesRegularExpression('/id="code"[^>]*value="12"[^>]*aria-invalid="true" aria-describedby="code-error"/', $html);
        $this->assertStringContainsString('<label for="password">Nueva contraseña</label>', $html);
    }

    public function test_user_filters_are_labelled_and_deactivation_asks_for_confirmation(): void
    {
        DB::table('accounts')->insert(['username' => 'inactivo', 'name' => 'Cuenta Inactiva', 'province' => 'azuay', 'password' => 'x', 'active' => false]);
        $this->signIn('admin');
        $html = $this->get('/users')->assertOk()->getContent();
        foreach (['q' => 'Buscar usuario, nombre o correo', 'province' => 'Provincia', 'state' => 'Estado'] as $field => $label) {
            $this->assertStringContainsString('<label for="filter-'.$field.'">'.$label.'</label>', $html);
            $this->assertStringContainsString('id="filter-'.$field.'"', $html);
        }
        $this->assertStringContainsString('data-deactivate="Técnico (azuay)"', $html);
        $this->assertStringNotContainsString('data-deactivate="Admin', $html);
        $this->assertStringNotContainsString('data-deactivate="Cuenta Inactiva', $html);
        $this->assertStringContainsString('<dialog id="deactivate-confirmation"', $html);
        $this->assertStringContainsString('>Desactivar cuenta</button>', $html);
    }
}

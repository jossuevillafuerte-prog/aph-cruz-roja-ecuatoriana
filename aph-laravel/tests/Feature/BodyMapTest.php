<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BodyMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('usuarios')->insert(['provincia' => 'azuay', 'clave' => 'unused']);
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        $operator = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'azuay', 'nombre' => 'Técnico', 'perfil' => 'p', 'clave' => $hash]);
        $id = DB::table('accounts')->insertGetId(['username' => 'azuay', 'name' => 'Técnico', 'province' => 'azuay', 'operator_id' => $operator, 'password' => $hash, 'active' => true, 'legacy_access' => true]);
        $user = Auth::getProvider()->retrieveById((string) $id);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    public function test_form_offers_the_body_map_with_official_descriptions(): void
    {
        $html = $this->get('/?vista=nueva')->assertOk()->getContent();
        $this->assertStringContainsString('<select id="f-AE" name="AE"', $html);
        $this->assertStringContainsString('data-zone="Cabeza"', $html);
        $this->assertStringContainsString('mapa-corporal.js?v=', $html);
        $zones = json_decode(html_entity_decode(preg_match('/data-zones="([^"]+)"/', $html, $m) ? $m[1] : ''), true);
        $this->assertSame('S00–S09', $zones['Cabeza']['range']);
        $this->assertSame(['S06.0', 'Concusión'], $zones['Cabeza']['codes'][0]);
        $this->assertNull($zones['Sin zona (clínico)']['range']);
    }

    public function test_form_is_split_into_four_steps_with_one_tap_choices(): void
    {
        $html = $this->get('/?vista=nueva')->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, 'class="card form-step"'));
        $this->assertSame(4, substr_count($html, 'data-step-target="'));
        $this->assertStringContainsString('form-steps.js?v=', $html);
        $this->assertStringContainsString('<input type="radio" id="f-O" name="O" value="Mujer"', $html);
        $this->assertStringContainsString('name="R" value="No_aplica"', $html);
        $this->assertStringContainsString('<span>No aplica</span>', $html);
        $this->assertStringNotContainsString('<select id="f-O"', $html);
        $this->assertStringContainsString('<input type="hidden" id="f-H" name="H"', $html);
        $this->assertStringContainsString('<input type="hidden" id="f-W" name="W"', $html);
        $this->assertMatchesRegularExpression('/id="paso-1".*?<div class="grid">\s*<div class="field"><label for="f-K">/s', $html);
        $this->assertStringContainsString('<label for="f-X">Médico de recepción<span class="required-mark" aria-hidden="true">*</span>', $html);
        $this->assertStringContainsString('<label for="f-AD">Observaciones</label>', $html);

        $this->post('/?vista=nueva', ['_token' => csrf_token(), 'version' => '0', 'accion' => 'guardar', 'B' => '2026-11-26', 'O' => 'Mujer', 'AB' => 'Medio'])->assertStatus(303);
        $saved = json_decode(DB::table('atenciones')->value('datos'), true);
        $this->assertSame(['Mujer', 'Medio'], [$saved['O'], $saved['AB']]);
        $this->assertMatchesRegularExpression('/name="O" value="Mujer" checked/', $this->get('/?id='.DB::table('atenciones')->value('id'))->getContent());
    }

    public function test_zone_is_saved_printed_and_validated(): void
    {
        $base = ['_token' => csrf_token(), 'version' => '0', 'accion' => 'guardar', 'B' => '2026-11-26', 'U' => 'S06.0'];
        $this->post('/?vista=nueva', $base + ['AE' => 'Planeta'])->assertStatus(422)->assertSee('Selecciona una zona afectada de la lista.');
        $this->assertDatabaseCount('atenciones', 0);

        $this->post('/?vista=nueva', $base + ['AE' => 'Cabeza'])->assertStatus(303);
        $record = DB::table('atenciones')->first();
        $this->assertSame('Cabeza', json_decode($record->datos, true)['AE']);
        $this->get('/?id='.$record->id.'&vista=imprimir')->assertSee('Zona afectada')->assertSee('Cabeza');
    }

    public function test_cie_search_can_be_limited_to_a_zone(): void
    {
        $result = $this->getJson('/?vista=cie&zona='.urlencode('Tobillo y pie'))->assertOk()->json();
        $this->assertGreaterThan(0, $result['total']);
        foreach ($result['items'] as $item) {
            $this->assertMatchesRegularExpression('/\AS9\d/', $item['codigo']);
        }
        $this->assertGreaterThan($result['total'], $this->getJson('/?vista=cie&zona=Inexistente')->json('total'));
    }
}

<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardAndListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('legacy/modelo.php');
        foreach (['azuay', 'pichincha'] as $province) {
            DB::table('usuarios')->insert(['provincia' => $province, 'clave' => 'unused']);
        }
    }

    private function signIn(string $role, ?string $province = null): void
    {
        $id = DB::table('accounts')->insertGetId(['username' => 'prueba.'.$role, 'name' => 'Prueba', 'role' => $role, 'province' => $province, 'password' => 'x', 'active' => true, 'legacy_access' => true]);
        $user = Auth::getProvider()->retrieveById((string) $id);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $columns
     */
    private function attention(array $values = [], array $columns = []): int
    {
        $data = $values + ['B' => date('Y-m-d')] + array_fill_keys(array_keys(FIELDS), '');

        return DB::table('atenciones')->insertGetId($columns + ['version' => 1, 'estado' => 'Pendiente', 'provincia' => 'azuay', 'datos' => json_encode($data)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dashboardData(TestResponse $response): array
    {
        preg_match('/<script id="dashboard-data" type="application\/json">(.*?)<\/script>/s', $response->assertOk()->getContent(), $match);

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_dashboard_is_limited_to_the_period_and_the_province(): void
    {
        $recent = $this->attention(['K' => 'RECIENTE']);
        $old = $this->attention(['K' => 'ANTIGUA', 'B' => '2020-05-10']);
        $this->attention([], ['provincia' => 'pichincha']);
        $this->attention([], ['eliminado' => date('c')]);
        $this->signIn('technician', 'azuay');
        $this->assertSame([$recent], array_column($this->dashboardData($this->get('/')), 'id'));
        $this->assertSame([$old], array_column($this->dashboardData($this->get('/?desde=2020-01-01&hasta=2020-12-31')), 'id'));
        $this->assertEqualsCanonicalizing([$recent, $old], array_column($this->dashboardData($this->get('/?periodo=todo')), 'id'));
        foreach (['/?desde=2020-12-31&hasta=2020-01-01', '/?desde=nada&hasta=2020-01-01', '/?desde[]=x&hasta=2020-01-01', '/?desde=2020-01-01'] as $url) {
            $this->assertSame([$recent], array_column($this->dashboardData($this->get($url)->assertSee('no son válidas')), 'id'));
        }
    }

    public function test_dashboard_never_contains_patient_identity_or_diagnosis(): void
    {
        $this->attention(['L' => 'PACIENTE-SECRETO', 'N' => '0102030405', 'T' => 'DIAGNOSTICO-SECRETO', 'AD' => 'OBSERVACION-SECRETA', 'H' => 'DIRECCION-SECRETA', 'O' => 'Mujer', 'P' => '0', 'evento_lat' => -2.9, 'evento_lon' => -79.0, 'recurso_tipo' => 'Ambulancia']);
        $this->signIn('superadmin');
        $response = $this->get('/national');
        foreach (['PACIENTE-SECRETO', '0102030405', 'DIAGNOSTICO-SECRETO', 'OBSERVACION-SECRETA', 'DIRECCION-SECRETA'] as $secret) {
            $response->assertDontSee($secret);
        }
        $record = $this->dashboardData($response)[0];
        $this->assertEquals(['provincia' => 'Azuay', 'tipo' => 'Ambulancia', 'O' => 'Mujer', 'P' => '0', 'evento_lat' => -2.9], array_intersect_key($record, array_flip(['provincia', 'tipo', 'O', 'P', 'evento_lat'])));
    }

    public function test_national_list_does_not_load_the_dashboard(): void
    {
        $this->signIn('superadmin');
        $this->get('/national?view=atenciones')->assertOk()->assertDontSee('dashboard-mapas.js', false)->assertDontSee('dashboard-data', false);
        $this->get('/national')->assertOk()->assertSee('dashboard-mapas.js', false)->assertViewMissing('rows');
    }

    public function test_provincial_list_is_paginated_in_the_database_and_keeps_its_context(): void
    {
        for ($number = 1; $number <= 31; $number++) {
            $this->attention(['K' => sprintf('HOJA-%03d', $number)]);
        }
        $this->attention(['K' => 'CERRADA'], ['estado' => 'Cerrada']);
        $this->attention(['K' => 'AJENA'], ['provincia' => 'pichincha']);
        $this->signIn('technician', 'azuay');
        $this->get('/?vista=atenciones')->assertOk()->assertSee('CERRADA')->assertSee('HOJA-031')->assertDontSee('HOJA-002')->assertDontSee('AJENA')->assertSee('data-count="32"', false)->assertSee('Página 1 de 2');
        $this->get('/?vista=atenciones&pagina=2')->assertOk()->assertSee('HOJA-001')->assertSee('HOJA-002')->assertDontSee('HOJA-003');
        $this->get('/?vista=atenciones&estado=Pendiente&pagina=2')->assertOk()->assertSee('HOJA-001')->assertDontSee('HOJA-002')->assertSee('return_page=2', false);
        $this->get('/?vista=atenciones&estado=Cerrada')->assertOk()->assertSee('CERRADA')->assertDontSee('HOJA-031')->assertSee('Página 1 de 1');
        $this->get('/?vista=atenciones&pagina=999')->assertOk()->assertSee('Página 2 de 2');
        $this->get('/?vista=atenciones&pagina[]=x')->assertOk()->assertSee('Página 1 de 2');
        $id = DB::table('atenciones')->min('id');
        $this->get('/?id='.$id.'&return_estado=Pendiente&return_page=2')->assertOk()->assertSee('/?vista=atenciones&amp;estado=Pendiente&amp;pagina=2', false);
    }

    public function test_incomplete_record_does_not_break_the_list(): void
    {
        DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Pendiente', 'provincia' => 'azuay', 'datos' => '{}']);
        $this->signIn('technician', 'azuay');
        $this->get('/?vista=atenciones')->assertOk()->assertSee('Sin número de hoja');
        $this->get('/?periodo=todo')->assertOk();
    }

    public function test_superadmin_returns_to_all_provinces(): void
    {
        $id = $this->attention();
        $this->signIn('superadmin');
        $this->get('/?id='.$id.'&return_province=&return_estado=&return_page=3')->assertOk()->assertSee('/national?view=atenciones&amp;province=&amp;estado=&amp;page=3', false);
    }
}

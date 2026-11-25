<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class NationalAccessAndMigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function closedAttention(string $province, float $latitude): void
    {
        require_once base_path('legacy/modelo.php');
        $data = array_fill_keys(array_keys(FIELDS), '');
        DB::table('atenciones')->insert(['version' => 1, 'estado' => 'Cerrada', 'provincia' => $province, 'datos' => json_encode($data + ['evento_lat' => $latitude, 'evento_lon' => -79.5])]);
    }

    public function test_superadmin_can_search_diagnoses_and_addresses_without_a_province(): void
    {
        $this->signIn('superadmin');
        $this->assertGreaterThan(0, $this->get('/?vista=cie&q=diabetes')->assertOk()->json('total'));
        $this->post('/?vista=direccion', ['_token' => csrf_token(), 'lat' => 'x', 'lon' => '1'])->assertStatus(422);
        $this->get('/?vista=imprimir')->assertRedirect('/national');
        $this->get('/')->assertRedirect('/national');
    }

    public function test_superadmin_creates_and_exports_for_the_explicit_province(): void
    {
        $this->closedAttention('azuay', -2.5);
        $this->closedAttention('pichincha', -0.5);
        $this->signIn('superadmin');
        $this->withSession(['scope_province' => 'azuay']);
        $this->get('/national?view=atenciones')->assertOk()->assertSee('/?vista=nueva&amp;select_province=1', false)->assertDontSee('vista=exportar', false);
        $this->get('/national?view=atenciones&province=pichincha')->assertOk()->assertSee('/?vista=exportar&amp;province=pichincha', false)->assertSee('/?vista=geojson&amp;province=pichincha', false);
        $coordinates = $this->get('/?vista=geojson&province=pichincha')->assertOk()->json('features.*.geometry.coordinates');
        $this->assertSame([[-79.5, -0.5]], $coordinates);
        $this->get('/?vista=exportar&province=pichincha')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="BDAPH-pichincha-piloto.xlsx"');
        foreach (['/?vista=exportar', '/?vista=geojson', '/?vista=exportar&province=invalid', '/?vista=geojson&province[]=azuay'] as $url) {
            $this->get($url)->assertRedirect('/national?view=atenciones')->assertSessionHas('notice');
        }
        $this->get('/?vista=nueva&province=pichincha')->assertOk()->assertSee('name="_province" value="pichincha"', false);
        $this->post('/?vista=nueva&province=azuay', ['_token' => csrf_token(), '_province' => 'pichincha', 'version' => '0', 'accion' => 'guardar', 'B' => '2026-11-20', 'K' => 'N-1'])->assertStatus(303);
        $this->assertSame('pichincha', DB::table('atenciones')->orderByDesc('id')->value('provincia'));
    }

    public function test_technician_cannot_choose_another_province(): void
    {
        $this->closedAttention('azuay', -2.5);
        $this->closedAttention('pichincha', -0.5);
        $this->signIn('technician', 'azuay');
        $this->assertSame([[-79.5, -2.5]], $this->get('/?vista=geojson&province=pichincha')->assertOk()->json('features.*.geometry.coordinates'));
        $this->get('/?vista=exportar&province=pichincha')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="BDAPH-azuay-piloto.xlsx"');
        $this->get('/?vista=nueva&province=pichincha')->assertOk()->assertSee('name="_province" value="azuay"', false);
        $this->get('/?vista=cie&q=diabetes')->assertOk();
    }

    public function test_rollback_is_refused_without_losing_data(): void
    {
        try {
            $this->artisan('migrate:rollback')->run();
            $refused = false;
        } catch (RuntimeException $error) {
            $refused = str_contains($error->getMessage(), 'Restaurar copia de seguridad');
        }
        $this->assertTrue($refused);
        $this->assertTrue(Schema::hasColumns('accounts', ['first_name', 'last_name', 'disabled_at']));
        $this->assertTrue(Schema::hasColumn('atenciones', 'solicitud'));
        $this->assertTrue(Schema::hasColumns('security_events', ['reference', 'target']));
    }

    /**
     * Las migraciones de MySQL corren sin transacción; RefreshDatabase vuelve a migrar al terminar.
     */
    public function test_unified_accounts_migration_can_be_retried(): void
    {
        DB::rollBack();
        DB::table('usuarios')->insert([['provincia' => 'azuay', 'clave' => 'x'], ['provincia' => 'pichincha', 'clave' => 'x']]);
        Schema::drop('drafts');
        Schema::drop('account_invitations');
        Schema::drop('accounts');
        $migration = require database_path('migrations/2026_11_10_000002_unified_accounts.php');
        $operator = ['usuario' => 'jperez', 'nombre' => 'J', 'perfil' => 'p', 'clave' => 'x'];
        DB::table('operadores')->insert([$operator + ['provincia' => 'azuay'], $operator + ['provincia' => 'pichincha']]);
        $fixes = [
            'jperez' => ['usuario' => 'azuay'],
            'azuay' => ['usuario' => 'mlopez', 'nombre' => str_repeat('n', 201)],
            'mlopez' => ['nombre' => 'M'],
        ];
        foreach ($fixes as $conflict => $fix) {
            $this->assertStringContainsString($conflict, $this->migrationError($migration));
            DB::table('operadores')->where('provincia', 'pichincha')->update($fix);
        }
        Schema::rename('auth_security', 'auth_security_tmp');
        $this->assertStringContainsString('auth_security', $this->migrationError($migration));
        $this->assertSame(2, DB::table('operadores')->count());
        Schema::rename('auth_security_tmp', 'auth_security');
        $migration->up();
        $this->assertEqualsCanonicalizing(['jperez', 'mlopez', 'azuay', 'pichincha'], DB::table('accounts')->pluck('username')->all());
    }

    private function migrationError(object $migration): string
    {
        $message = '';
        try {
            $migration->up();
        } catch (Throwable $error) {
            $message = $error->getMessage();
        }
        $this->assertFalse(Schema::hasTable('accounts'));
        $this->assertFalse(Schema::hasTable('account_invitations'));

        return $message;
    }
}

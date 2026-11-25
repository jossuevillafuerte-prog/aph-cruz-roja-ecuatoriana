<?php

namespace Tests\Feature;

use App\Security\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttentionTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['azuay', 'pichincha'] as $province) {
            DB::table('usuarios')->insert(['provincia' => $province, 'clave' => 'unused']);
        }
        $hash = password_hash('Prueba@7', PASSWORD_BCRYPT);
        $operatorId = DB::table('operadores')->insertGetId(['provincia' => 'azuay', 'usuario' => 'azuay', 'nombre' => 'Técnico Azuay', 'perfil' => 'Técnico provincial APH', 'clave' => $hash, 'activo' => 1, 'revision' => 0]);
        $this->accountId = DB::table('accounts')->insertGetId(['username' => 'azuay', 'name' => 'Técnico Azuay', 'province' => 'azuay', 'operator_id' => $operatorId, 'password' => $hash, 'active' => true, 'legacy_access' => true]);
        $this->signIn();
    }

    private function signIn(): void
    {
        $user = Auth::getProvider()->retrieveById((string) $this->accountId);
        $this->actingAs($user)->withSession(['credential' => Security::stamp($user), 'authenticated_at' => time(), 'last_activity' => time()]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function submit(string $url, array $input): TestResponse
    {
        return $this->post($url, $input + ['_token' => csrf_token(), 'version' => '0', 'accion' => 'guardar', 'B' => '2026-11-20', 'K' => 'HOJA-1']);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(int $id): array
    {
        return json_decode(DB::table('atenciones')->where('id', $id)->value('datos'), true);
    }

    private function reasons(int $id): array
    {
        return DB::table('cambios')->where('atencion', $id)->orderBy('id')->pluck('motivo')->all();
    }

    public function test_history_describes_each_real_action(): void
    {
        $this->submit('/?vista=nueva', [])->assertStatus(303);
        $id = DB::table('atenciones')->value('id');
        $this->submit('/?id='.$id, ['version' => '1'])->assertStatus(303);
        $closing = ['C' => array_key_first(json_decode(file_get_contents(base_path('legacy/catalogos.json')), true)['aph']), 'G' => 'Cuenca'];
        $this->submit('/?id='.$id, $closing + ['version' => '2', 'accion' => 'cerrar'])->assertStatus(303);
        $this->submit('/?id='.$id, $closing + ['version' => '3', 'accion' => 'corregir', 'motivo' => ''])->assertStatus(422);
        $this->submit('/?id='.$id, $closing + ['version' => '3', 'accion' => 'corregir', 'motivo' => '0'])->assertStatus(303);
        $this->assertSame(['Creación de atención', 'Actualización de atención pendiente', 'Cierre de atención', '0'], $this->reasons($id));

        $this->submit('/?vista=nueva', $closing + ['accion' => 'cerrar', 'K' => 'HOJA-2'])->assertStatus(303);
        $this->assertSame(['Creación y cierre de atención'], $this->reasons((int) DB::table('atenciones')->max('id')));
        $this->submit('/?vista=nueva', ['accion' => 'cerrar', 'K' => 'HOJA-3'])->assertStatus(422);
        $this->assertDatabaseCount('atenciones', 2);
    }

    public function test_saving_preserves_unknown_data_and_records_resource_type_changes(): void
    {
        require_once base_path('legacy/modelo.php');
        $data = array_fill_keys(array_keys(FIELDS), '');
        $data = ['B' => '2026-11-20', 'K' => 'DEMO', 'J' => 'AMB-ANTIGUA'] + $data + ['demo_lote' => 'L1', 'extra' => ['a' => 1], 'recurso_tipo' => 'Ambulancia'];
        $id = DB::table('atenciones')->insertGetId(['version' => 1, 'estado' => 'Pendiente', 'datos' => json_encode($data), 'provincia' => 'azuay']);
        $this->submit('/?id='.$id, ['version' => '1', 'K' => 'DEMO-2', 'conservar_recurso' => '1'])->assertStatus(303);
        $saved = $this->data($id);
        $this->assertSame(['DEMO-2', 'L1', ['a' => 1], 'Ambulancia', 'AMB-ANTIGUA'], [$saved['K'], $saved['demo_lote'], $saved['extra'], $saved['recurso_tipo'], $saved['J']]);
        $this->get('/?vista=imprimir&id='.$id)->assertOk()->assertSee('Datos ficticios');

        $this->submit('/?id='.$id, ['version' => '2', 'K' => 'DEMO-2'])->assertStatus(303);
        $this->assertArrayNotHasKey('recurso_tipo', $this->data($id));
        $changes = json_decode(DB::table('cambios')->where('atencion', $id)->orderByDesc('id')->value('detalle'), true);
        $this->assertSame(['Ambulancia', ''], $changes['recurso_tipo']);
        $this->get('/?id='.$id)->assertOk()->assertSee('recurso_tipo');
    }

    public function test_audit_distinguishes_writes_rejections_and_reads(): void
    {
        $this->submit('/?vista=nueva', [])->assertStatus(303);
        $id = DB::table('atenciones')->value('id');
        $this->assertDatabaseHas('security_events', ['event' => 'application_write', 'target' => 'atencion:'.$id, 'identity' => (string) $this->accountId]);
        $this->submit('/?id='.$id, ['version' => '9'])->assertStatus(422);
        $this->post('/?vista=eliminar&id='.$id, ['_token' => csrf_token(), 'version' => '9'])->assertStatus(409);
        $this->get('/?id=999999')->assertNotFound();
        $this->get('/?id='.$id)->assertOk();
        $this->get('/?vista=geojson')->assertOk();
        $this->assertSame(1, DB::table('security_events')->where('event', 'application_write')->count());
        $this->assertDatabaseHas('security_events', ['event' => 'application_write_rejected', 'target' => 'registro:'.$id.':422']);
        $this->assertDatabaseHas('security_events', ['event' => 'application_write_rejected', 'target' => 'eliminar:'.$id.':409']);
        $this->assertDatabaseHas('security_events', ['event' => 'record_access_denied', 'target' => 'registro:999999:404']);
        $this->assertDatabaseHas('security_events', ['event' => 'record_read', 'target' => 'registro:'.$id]);
        $this->assertDatabaseHas('security_events', ['event' => 'record_output', 'target' => 'geojson:azuay']);
        $this->assertDatabaseMissing('security_events', ['event' => 'record_read', 'target' => 'registro:999999']);

        $this->post('/?vista=eliminar&id='.$id, ['_token' => csrf_token(), 'version' => '1'])->assertStatus(303);
        $this->assertDatabaseHas('security_events', ['event' => 'application_write', 'target' => 'eliminar:'.$id]);
    }

    public function test_unknown_views_are_rejected_before_writing(): void
    {
        $this->submit('/?vista=nueva', [])->assertStatus(303);
        $id = DB::table('atenciones')->value('id');
        $this->submit('/?id='.$id.'&vista='.str_repeat('x', 90), ['version' => '1', 'K' => 'CAMBIADO'])->assertNotFound();
        $this->submit('/?id='.$id.'&vista[]=x', ['version' => '1', 'K' => 'CAMBIADO'])->assertNotFound();
        $this->assertSame('HOJA-1', $this->data($id)['K']);
    }

    public function test_responsible_includes_the_unforgeable_username(): void
    {
        $this->post('/?vista=perfil', ['_token' => csrf_token(), 'nombre' => 'María Pérez (mperez)'])->assertStatus(422);
        $this->post('/?vista=perfil', ['_token' => csrf_token(), 'nombre' => 'María Pérez'])->assertStatus(303);
        $this->assertDatabaseHas('security_events', ['event' => 'profile_name_changed', 'target' => 'account:'.$this->accountId, 'reference' => 'Técnico Azuay → María Pérez']);
        $this->signIn();
        $this->submit('/?vista=nueva', ['Y' => 'Otra persona'])->assertStatus(303);
        $id = DB::table('atenciones')->value('id');
        $this->assertSame('María Pérez (azuay)', $this->data($id)['Y']);
        $this->get('/?vista=imprimir&id='.$id)->assertOk()->assertSee('María Pérez (azuay)');
    }

    public function test_repeated_submission_creates_one_record_and_never_hides_different_data(): void
    {
        $html = $this->get('/?vista=nueva')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="solicitud" value="[0-9a-f-]{36}"/', $html);
        $request = ['solicitud' => (string) Str::uuid(), 'evento_lat' => '-2.900000', 'evento_lon' => '-79.000000'];
        $first = $this->submit('/?vista=nueva', $request)->assertStatus(303)->headers->get('Location');
        $second = $this->submit('/?vista=nueva', $request)->assertStatus(303)->headers->get('Location');
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('atenciones', 1);
        $this->assertDatabaseCount('cambios', 1);
        $this->submit('/?vista=nueva', ['K' => 'DISTINTA'] + $request)->assertStatus(422)->assertSee('ya se guardó con otros datos')->assertSee('DISTINTA');
        $this->submit('/?vista=nueva', ['solicitud' => (string) Str::uuid()])->assertStatus(303);
        $this->assertDatabaseCount('atenciones', 2);
        $this->submit('/?vista=nueva', [])->assertStatus(303);
        $this->assertDatabaseCount('atenciones', 3);
    }

    public function test_resources_are_audited_and_duplicates_are_reported(): void
    {
        $input = ['_token' => csrf_token(), 'numero' => 'AMB-1', 'ubicacion' => 'Cuenca', 'capacidad' => '1', 'combustible' => 'Diésel', 'tipo' => 'Ambulancia'];
        $this->post('/?vista=recursos', $input)->assertStatus(303);
        $this->assertDatabaseHas('security_events', ['event' => 'application_write', 'target' => 'recurso:'.DB::table('recursos')->value('id')]);
        $this->post('/?vista=recursos', $input)->assertStatus(422)->assertSee('Ya existe un recurso');
        $this->assertDatabaseCount('recursos', 1);
    }
}

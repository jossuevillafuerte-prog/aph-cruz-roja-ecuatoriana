<?php

namespace App\Http\Controllers;

use App\Security\Security;
use App\Support\LegacyStop;
use App\Support\LegacyTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LegacyController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $view = $request->query('vista', '');
        if ($view === 'operadores') {
            abort_unless($user->role === 'superadmin', 403);

            return redirect('/users');
        }
        if ($user->role === 'superadmin' && $request->isMethod('GET') && $view === 'atenciones') {
            return redirect('/national?view=atenciones');
        }
        $provinces = json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
        $scope = $user->province;
        $nationalProfile = $user->role === 'superadmin' && in_array($view, ['perfil', 'avatar'], true);
        if ($user->role === 'superadmin') {
            $scope = $request->isMethod('POST') ? $request->input('_province') : $request->session()->get('scope_province');
            if ($request->isMethod('GET') && $request->query('id')) {
                $table = $view === 'recursos' ? 'recursos' : 'atenciones';
                $scope = DB::table($table)->where('id', (int) $request->query('id'))->value('provincia') ?? $scope;
            }
            if ($nationalProfile) {
                $scope = 'nacional';
            } elseif ($request->isMethod('GET') && in_array($view, ['nueva', 'recursos'], true) && (! isset($provinces[$scope ?? '']) || $request->boolean('select_province'))) {
                return view('users.province', ['provinces' => $provinces, 'next' => $view]);
            } elseif (! isset($provinces[$scope ?? ''])) {
                return redirect('/national')->with('notice', 'Selecciona una provincia para gestionar registros.');
            }
        }
        abort_unless($nationalProfile || isset($provinces[$scope ?? '']), 403);
        $request->attributes->set('scope_province', $scope);
        $returnState = $request->query('return_estado', '');
        $returnState = in_array($returnState, ['Pendiente', 'Cerrada'], true) ? $returnState : '';
        $context = ['return_estado' => $returnState];
        $backQuery = ['vista' => 'atenciones', 'estado' => $returnState];
        $backPath = '/';
        if ($user->role === 'superadmin') {
            $returnProvince = $request->query('return_province', $scope) ?? '';
            $returnProvince = is_string($returnProvince) && isset($provinces[$returnProvince]) ? $returnProvince : '';
            $returnPage = filter_var($request->query('return_page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
            $context += ['return_province' => $returnProvince, 'return_page' => $returnPage];
            $backPath = '/national';
            $backQuery = ['view' => 'atenciones', 'province' => $returnProvince, 'estado' => $returnState, 'page' => $returnPage];
        }
        $request->attributes->set('attention_back_url', $backPath.'?'.http_build_query($backQuery));
        $request->attributes->set('attention_return_context', http_build_query($context));
        // No second PHP session or publicly reachable legacy entry point.
        $transport = new LegacyTransport;
        app()->instance(LegacyTransport::class, $transport);
        require_once base_path('legacy/transport.php');
        $_GET = $request->query();
        $_POST = array_map(static fn ($value) => $value === null ? '' : $value, $request->post());
        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_SESSION = ['token' => $request->session()->token(), 'credencial' => $request->session()->get('credential')];
        ob_start();
        try {
            require base_path('legacy/index.php');
        } catch (LegacyStop) {
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $body = ob_get_clean();
        $body = preg_replace('/(<form\b[^>]*>)/i', '$1<input type="hidden" name="_province" value="'.htmlspecialchars($scope, ENT_QUOTES).'">', $body);
        if ($_SESSION['credencial'] !== $request->session()->get('credential')) {
            $request->session()->put('credential', $_SESSION['credencial']);
            DB::table('sessions')->where('user_id', Auth::id())->where('id', '!=', $request->session()->getId())->delete();
            Security::event('password_changed');
        }
        if ($request->isMethod('POST')) {
            Security::event('application_write', null, ($request->query('vista') ?? 'atencion').':'.(int) $request->query('id', 0));
        } elseif ($request->query('id')) {
            Security::event('record_read', null, 'registro:'.(int) $request->query('id'));
        }
        if (in_array($request->query('vista'), ['imprimir', 'exportar', 'geojson'])) {
            Security::event('record_output', null, ($request->query('vista') ?? '').':'.(int) $request->query('id', 0));
        }

        return response($body, $transport->status, $transport->headers);
    }
}

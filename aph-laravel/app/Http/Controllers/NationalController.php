<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NationalController extends Controller
{
    public function index(Request $request)
    {
        $provinces = json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
        $this->validateOrRedirect($request, '/national', ['province' => ['nullable', Rule::in(array_keys($provinces))], 'estado' => ['nullable', Rule::in(['Pendiente', 'Cerrada'])]]);
        $province = $request->query('province') ?? '';
        $state = $request->query('estado') ?? '';
        if ($request->query('view') !== 'atenciones') {
            return view('users.national', compact('provinces', 'province'));
        }
        $query = DB::table('atenciones')->whereNull('eliminado')->when($province, fn ($query) => $query->where('provincia', $province));
        $counts = (clone $query)->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado')->all();
        $total = array_sum($counts);
        $rows = $query->when($state, fn ($query) => $query->where('estado', $state))->orderByDesc('id')->paginate(30)->withQueryString();

        return view('users.national', compact('provinces', 'rows', 'province', 'state', 'counts', 'total'));
    }

    public function scope(Request $r)
    {
        $all = json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
        $v = $this->validateOrRedirect($r, '/national', ['province' => ['required', Rule::in(array_keys($all))], 'next' => ['nullable', Rule::in(['nueva', 'recursos', 'atenciones'])]]);
        $r->session()->put('scope_province', $v['province']);

        return redirect(isset($v['next']) ? '/?vista='.$v['next'] : '/');
    }
}

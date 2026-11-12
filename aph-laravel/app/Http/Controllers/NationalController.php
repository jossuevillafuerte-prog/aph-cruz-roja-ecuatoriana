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
        $request->validate(['province' => ['nullable', Rule::in(array_keys($provinces))]]);
        $province = $request->query('province') ?? '';
        $rows = DB::table('atenciones')->whereNull('eliminado')->when($province, fn ($query) => $query->where('provincia', $province))->orderByDesc('id')->paginate(30)->withQueryString();

        return view('users.national', compact('provinces', 'rows', 'province'));
    }

    public function scope(Request $r)
    {
        $all = json_decode(file_get_contents(base_path('legacy/provincias.json')), true);
        $v = $r->validate(['province' => ['required', Rule::in(array_keys($all))], 'next' => ['nullable', Rule::in(['nueva', 'recursos', 'atenciones'])]]);
        $r->session()->put('scope_province', $v['province']);

        return redirect(isset($v['next']) ? '/?vista='.$v['next'] : '/');
    }
}

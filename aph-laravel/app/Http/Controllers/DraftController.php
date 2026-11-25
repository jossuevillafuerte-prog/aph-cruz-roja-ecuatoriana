<?php

namespace App\Http\Controllers;

use App\Services\AttentionDrafts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $key = $this->authorizedKey($request);
        $fields = $request->input('fields');
        abort_unless(is_array($fields) && count($fields) <= 80, 422);
        $clean = [];
        foreach ($fields as $name => $value) {
            // Laravel convierte los campos vacíos en null.
            $value ??= '';
            abort_unless(is_string($name) && preg_match('/\A[A-Za-z_]{1,30}\z/', $name) && is_string($value) && mb_strlen($value) <= 4000, 422);
            $clean[$name] = $value;
        }
        abort_if(strlen(json_encode($clean, JSON_UNESCAPED_UNICODE)) > AttentionDrafts::MAX_BYTES, 413);
        AttentionDrafts::store((int) $request->user()->id, $key, max(0, (int) $request->input('version', 0)), $clean);

        return response()->json(['saved_at' => now()->format('H:i')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        AttentionDrafts::forget((int) $request->user()->id, $this->authorizedKey($request));

        return response()->json(['deleted' => true]);
    }

    private function authorizedKey(Request $request): string
    {
        $key = $request->input('key');
        abort_unless(is_string($key) && AttentionDrafts::allowed($request->user(), $key), 403);

        return $key;
    }
}

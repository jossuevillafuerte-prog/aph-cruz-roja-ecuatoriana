<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * Valida y, si falla, vuelve a una ruta explícita: el Referer solo contiene el origen (strict-origin).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validateOrRedirect(Request $request, string $redirectTo, array $rules): array
    {
        try {
            return $request->validate($rules);
        } catch (ValidationException $error) {
            throw $error->redirectTo(url($redirectTo));
        }
    }
}

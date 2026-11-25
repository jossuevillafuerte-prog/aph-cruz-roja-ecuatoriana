<?php

/**
 * Mensajes de las reglas que usa la aplicación. Una regla sin traducción muestra el texto en inglés de Laravel.
 */
return [
    'boolean' => 'Selecciona un valor válido para «:attribute».',
    'confirmed' => 'La confirmación de «:attribute» no coincide.',
    'digits' => '«:attribute» debe tener :digits dígitos.',
    'email' => 'Ingresa un correo electrónico válido.',
    'in' => 'Selecciona una opción válida para «:attribute».',
    'integer' => '«:attribute» debe ser un número entero.',
    'max' => [
        'string' => '«:attribute» admite hasta :max caracteres.',
        'numeric' => '«:attribute» debe ser como máximo :max.',
    ],
    'min' => [
        'string' => '«:attribute» debe tener al menos :min caracteres.',
        'numeric' => '«:attribute» debe ser como mínimo :min.',
    ],
    'regex' => '«:attribute» tiene un formato no válido.',
    'required' => 'Completa «:attribute».',
    'required_if' => 'Completa «:attribute».',
    'string' => '«:attribute» debe ser texto.',
    'unique' => 'Ya existe una cuenta con ese «:attribute».',

    'custom' => [
        'username' => [
            'regex' => 'El usuario debe tener de 3 a 40 caracteres: minúsculas, números, punto, guion o guion bajo, y empezar por letra o número.',
        ],
    ],

    'attributes' => [
        'active' => 'estado',
        'code' => 'código',
        'email' => 'correo institucional',
        'first_name' => 'nombre',
        'last_name' => 'apellido',
        'name' => 'nombre',
        'next' => 'destino',
        'password' => 'contraseña',
        'province' => 'provincia',
        'role' => 'rol',
        'username' => 'usuario',
        'usuario' => 'usuario',
        'estado' => 'estado',
    ],
];

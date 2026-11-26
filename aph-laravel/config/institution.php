<?php

return [
    // PENDIENTE: habilitar SMTP/API y una URL HTTPS accesible antes de activar envíos.
    'invitations_enabled' => (bool) env('APH_INVITATIONS_ENABLED', false),
    // Tablero público de Power BI enlazado desde el inicio de sesión; vacío oculta el botón.
    'powerbi_url' => env('APH_POWERBI_URL'),
    'domains' => [
        'azuay' => 'azuay.cruzroja.org.ec', 'bolivar' => 'bolivar.cruzroja.org.ec', 'canar' => 'caniar.cruzroja.org.ec', 'carchi' => 'carchi.cruzroja.org.ec',
        'chimborazo' => 'chimborazo.cruzroja.org.ec', 'cotopaxi' => 'cotopaxi.cruzroja.org.ec', 'el-oro' => 'eloro.cruzroja.org.ec', 'esmeraldas' => 'esmeraldas.cruzroja.org.ec',
        'galapagos' => 'galapagos.cruzroja.org.ec', 'guayas' => 'guayas.cruzroja.org.ec', 'imbabura' => 'imbabura.cruzroja.org.ec', 'loja' => 'loja.cruzroja.org.ec',
        'los-rios' => 'losrios.cruzroja.org.ec', 'manabi' => 'manabi.cruzroja.org.ec', 'morona-santiago' => 'morona.cruzroja.org.ec', 'napo' => 'napo.cruzroja.org.ec',
        'orellana' => 'orellana.cruzroja.org.ec', 'pastaza' => 'pastaza.cruzroja.org.ec', 'pichincha' => 'pichincha.cruzroja.org.ec', 'santa-elena' => 'staelena.cruzroja.org.ec',
        'santo-domingo-de-los-tsachilas' => 'stodomingo.cruzroja.org.ec', 'sucumbios' => 'sucumbios.cruzroja.org.ec', 'tungurahua' => 'tungurahua.cruzroja.org.ec', 'zamora-chinchipe' => 'zamora.cruzroja.org.ec',
    ],
    'national_domain' => 'cruzroja.org.ec',
];

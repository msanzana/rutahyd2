<?php
declare(strict_types=1);

// V109C7C: fail closed. No habilitar hasta validar el esquema y el alcance
// físico de las bodegas. La lista contiene exclusivamente pares del mismo recinto
// acreditados por sucursal. No equivale a autorización de transporte externo.
return [
    'escrituras' => false,
    'pares_recinto_verificados' => [],
];

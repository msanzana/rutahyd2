{{ $categoria }}
{{ $titulo }}

{{ $saludo }}

@foreach($parrafos as $parrafo)
{{ $parrafo }}

@endforeach
@if($accionUrl && $accionEtiqueta)
{{ $accionEtiqueta }}:
{{ $accionUrl }}

@endif
@if($nota)
Nota importante: {{ $nota }}

@endif
@if($referencia)
Referencia: {{ $referencia }}

@endif
Saludos,
Equipo Ruta H&D V2

Este es un mensaje automático. No compartas contraseñas ni códigos de acceso.
Sitio: {{ $sitioUrl }}
Contacto y soporte: {{ $correoSoporte }}

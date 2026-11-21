<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $titulo }} · Ruta H&D V2</title>
<style>
  @media only screen and (max-width: 640px) {
    .correo-shell { width: 100% !important; }
    .correo-pad { padding-left: 24px !important; padding-right: 24px !important; }
    .correo-title { font-size: 29px !important; line-height: 36px !important; }
    .correo-logo { width: 57px !important; height: 57px !important; }
    .correo-brand { font-size: 21px !important; }
    .correo-button { display: block !important; text-align: center !important; }
  }
</style>
</head>
<body style="padding:0;margin:0;background:#f5f1fa;font-family:Arial,Helvetica,sans-serif;color:#342747;-webkit-text-size-adjust:100%;">
<div style="display:none;font-size:1px;color:#f5f1fa;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
{{ $categoria }} — {{ $titulo }} · Comunicación oficial de Ruta H&D V2.
</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background:#f5f1fa;">
  <tr><td align="center" style="padding:38px 12px 46px;">
    <table role="presentation" class="correo-shell" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:600px;border-collapse:separate;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(37,12,66,.10);">
      <tr><td style="height:6px;font-size:0;background:#a875df;line-height:6px;">&nbsp;</td></tr>
      <tr><td class="correo-pad" style="background:#281342;padding:30px 42px 27px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
          <tr>
            <td width="76" valign="middle" style="width:76px;">
              <a href="{{ $sitioUrl }}" style="text-decoration:none;">
                <img class="correo-logo" src="{{ $message->embed(resource_path('mail/logo-hd-email.png')) }}" alt="H&amp;D" width="68" height="68" style="display:block;width:68px;height:68px;object-fit:contain;border:0;border-radius:12px;background:#ffffff;">
              </a>
            </td>
            <td valign="middle" style="padding-left:14px;">
              <div class="correo-brand" style="color:#ffffff;font-size:25px;font-weight:800;letter-spacing:-.5px;line-height:1.15;">Ruta H&amp;D <span style="color:#d5b3ff;">V2</span></div>
              <div style="color:#d9c8eb;font-size:12px;line-height:20px;letter-spacing:1.2px;text-transform:uppercase;margin-top:4px;">Gestión comercial y operativa</div>
            </td>
          </tr>
        </table>
      </td></tr>
      <tr><td class="correo-pad" style="padding:40px 44px 12px;">
        <div style="display:inline-block;background:#efe6fa;border-radius:30px;padding:8px 13px;color:#6b359c;font-weight:700;font-size:10px;line-height:14px;letter-spacing:1.6px;">{{ $categoria }}</div>
        <h1 class="correo-title" style="margin:21px 0 15px;font-size:33px;line-height:1.22;letter-spacing:-.85px;font-weight:800;color:#281342;">{{ $titulo }}</h1>
        <div style="width:54px;height:4px;border-radius:10px;background:#9b65d6;margin:0 0 26px;"></div>
        <p style="font-size:16px;line-height:1.75;color:#352840;margin:0 0 18px;font-weight:700;">{{ $saludo }}</p>
        @foreach($parrafos as $parrafo)
          <p style="font-size:15px;line-height:1.8;color:#62576c;margin:0 0 16px;">{{ $parrafo }}</p>
        @endforeach
      </td></tr>
      @if($accionUrl && $accionEtiqueta)
      <tr><td class="correo-pad" style="padding:8px 44px 30px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr><td bgcolor="#6f3cb2" style="border-radius:10px;background:#6f3cb2;">
            <a class="correo-button" href="{{ $accionUrl }}" style="font-size:15px;font-weight:700;letter-spacing:.1px;text-decoration:none;color:#ffffff;display:inline-block;border:1px solid #6f3cb2;border-radius:10px;padding:15px 26px;line-height:20px;">{{ $accionEtiqueta }} &nbsp;→</a>
          </td></tr>
        </table>
        <p style="font-size:12px;line-height:1.7;color:#807288;margin:16px 0 0;">Si el botón no funciona, copia este enlace en tu navegador:</p>
        <p style="font-size:12px;line-height:1.7;margin:5px 0 0;overflow-wrap:anywhere;word-break:break-word;"><a href="{{ $accionUrl }}" style="color:#6f3cb2;text-decoration:underline;">{{ $accionUrl }}</a></p>
      </td></tr>
      @endif
      @if($nota)
      <tr><td class="correo-pad" style="padding:4px 44px 28px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;background:#f7f3fc;border-left:4px solid #a875df;border-radius:8px;">
          <tr><td style="padding:14px 17px;color:#625075;font-size:13px;line-height:1.7;"><strong style="color:#44235f;">Nota importante.</strong> {{ $nota }}</td></tr>
        </table>
      </td></tr>
      @endif
      <tr><td class="correo-pad" style="padding:8px 44px 35px;">
        <p style="font-size:14px;line-height:1.8;color:#554563;margin:0;">Saludos,<br><strong style="color:#281342;">Equipo Ruta H&amp;D</strong></p>
        @if($referencia)
          <p style="font-size:11px;line-height:1.6;color:#8b7e94;margin:18px 0 0;">Referencia: {{ $referencia }}</p>
        @endif
      </td></tr>
      <tr><td style="border-top:1px solid #eae2f0;background:#fbf9fe;padding:25px 35px 27px;" align="center">
        <p style="font-size:12px;line-height:1.7;color:#806f91;margin:0 0 8px;">Mensaje automático de <strong style="color:#593a78;">Ruta H&amp;D V2</strong> · No compartas contraseñas ni códigos de acceso.</p>
        <p style="font-size:12px;line-height:1.7;margin:0 0 10px;"><a href="{{ $sitioUrl }}" style="color:#6f3cb2;text-decoration:none;">Ir a Ruta H&amp;D V2</a><span style="color:#c1b2cf;"> &nbsp;·&nbsp; </span><a href="mailto:{{ $correoSoporte }}" style="color:#6f3cb2;text-decoration:none;">Contacto y soporte</a></p>
        <p style="font-size:11px;line-height:1.6;color:#a296af;margin:0;">© {{ date('Y') }} Ruta H&amp;D V2 · Comunicación institucional</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>

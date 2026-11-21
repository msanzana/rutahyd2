# Correos corporativos Ruta H&D V2

## Identidad
- Marca: Ruta H&D V2; paleta violeta #281342, #6f3cb2, #a875df.
- Logotipo oficial: https://raw.githubusercontent.com/msanzana/rutahyd2front/main/assets/img/logo-hd-transparente.webp
- Remitente y soporte temporal: neo.hiper.xavier@gmail.com.
- Enlaces de acción: exclusivamente HTTPS del origen https://rutahyd2.free.nf/.
- El conector Gmail de ChatGPT NO configura automáticamente SMTP dentro de Laravel.

## Componentes reutilizables
- app/Mail/CorreoCorporativo.php: Mailable común para todos los mensajes transaccionales.
- resources/views/emails/corporativo.blade.php: HTML de diseño adaptable, estilos inline, CTA y nota.
- resources/views/emails/corporativo-texto.blade.php: alternativa para clientes de correo sin HTML.
- app/Http/Controllers/RecuperacionContrasenaControlador.php: recuperación e invitación inicial con la plantilla.

Campos: asunto, categoría, título, saludo, lista de párrafos, botón y enlace opcionales, nota y referencia opcionales.

Los textos se escapan mediante Blade. Los enlaces de acción se validan contra el dominio HTTPS de producción. Nunca se envían contraseñas temporales en el diseño automatizado. Para restablecimiento se usa un token de un solo uso que vence a los 30 minutos y no debe quedar en logs.

## Aplicaciones previstas
- Recuperación, activación e invitaciones (enlace temporal).
- Confirmaciones de operación (referencia y detalle mínimo).
- Avisos importantes de seguridad o cambios de perfil.
- Notificaciones de Compras, Ventas, Proveedores, Clientes, Productos, Bodega y Cuentas corrientes a medida que sus disparadores se implementen.

## Ejemplo de consumo
Al enviar, construir un nuevo objeto App\Mail\CorreoCorporativo con asunto, categoria, titulo, saludo y parrafos; opcionalmente accionEtiqueta y accionUrl, nota y referencia. Enviar mediante Mail::to($destinatario)->send($correo). El transporte toma credenciales solamente del .env privado.

No se requiere DDL ni DML para instalar esta plantilla. El test realizado desde la conexión Gmail no implica que MAIL_MAILER=smtp esté habilitado dentro de InfinityFree; hay que verificarlo por separado antes de iniciar notificaciones automáticas reales.

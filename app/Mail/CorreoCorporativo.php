<?php
declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Envoltorio HTML + texto plano para mensajes transaccionales de Ruta H&D V2.
 * No contiene transporte ni secretos: usa MAIL_* del entorno privado.
 */
final class CorreoCorporativo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $asunto,
        private readonly string $categoria,
        private readonly string $titulo,
        private readonly string $saludo,
        private readonly array $parrafos,
        private readonly ?string $accionEtiqueta = null,
        private readonly ?string $accionUrl = null,
        private readonly ?string $nota = null,
        private readonly ?string $referencia = null,
    ) {
        if (($accionEtiqueta === null) !== ($accionUrl === null)) {
            throw new InvalidArgumentException('La acción de correo requiere etiqueta y URL simultáneamente.');
        }
        if ($accionUrl !== null) {
            // Los enlaces de acción deben permanecer en el origen HTTPS autorizado.
            $app = (string) config('app.url');
            $originHost = parse_url($app, PHP_URL_HOST);
            if (!filter_var($accionUrl, FILTER_VALIDATE_URL)
                || parse_url($accionUrl, PHP_URL_SCHEME) !== 'https'
                || parse_url($accionUrl, PHP_URL_HOST) !== $originHost) {
                throw new InvalidArgumentException('El enlace debe usar HTTPS y el mismo dominio de Ruta H&D V2.');
            }
        }
        foreach ($parrafos as $parrafo) {
            if (!is_string($parrafo)) {
                throw new InvalidArgumentException('Los párrafos del correo deben ser texto sin HTML.');
            }
        }
    }

    public static function recuperacion(string $enlace): self
    {
        return new self(
            asunto: 'Ruta H&D V2 | Recuperación de acceso',
            categoria: 'SEGURIDAD DE CUENTA',
            titulo: 'Restablece tu contraseña',
            saludo: 'Hola,',
            parrafos: [
                'Recibimos una solicitud para actualizar la contraseña de tu cuenta de Ruta H&D V2.',
                'Por seguridad, este enlace funciona durante 30 minutos y es de un solo uso.',
            ],
            accionEtiqueta: 'Crear nueva contraseña',
            accionUrl: $enlace,
            nota: 'Si tú no solicitaste este cambio, puedes ignorar el mensaje. Tu contraseña actual permanecerá intacta.',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.corporativo',
            text: 'emails.corporativo-texto',
            with: [
                'categoria' => $this->categoria,
                'titulo' => $this->titulo,
                'saludo' => $this->saludo,
                'parrafos' => $this->parrafos,
                'accionEtiqueta' => $this->accionEtiqueta,
                'accionUrl' => $this->accionUrl,
                'nota' => $this->nota,
                'referencia' => $this->referencia,
                'sitioUrl' => 'https://rutahyd2.free.nf/',
                'correoSoporte' => 'neo.hiper.xavier@gmail.com',
            ],
        );
    }
}

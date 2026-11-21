<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RecuperacionContrasenaControlador extends Controller
{
    private const RESPUESTA = 'Si la cuenta existe y está activa, recibirás instrucciones en tu correo.';

    public static function enviarEnlace(Usuario $usuario): void
    {
        // Prohibido el transporte "log": los enlaces contienen un token de recuperación.
        abort_unless(config('mail.default') === 'smtp', 503, 'Correo de recuperación no configurado');
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        DB::transaction(function () use ($usuario, $hash): void {
            DB::table('restablecimientos_contrasena')->where('usuario_id', $usuario->id)->delete();
            DB::table('restablecimientos_contrasena')->insert([
                'usuario_id' => $usuario->id,
                'hash_token' => $hash,
                'expira_en' => now()->addMinutes(30),
                'creado_en' => now(),
            ]);
        });

        $base = rtrim((string)config('app.url'), '/');
        abort_unless(str_starts_with($base, 'https://'), 503, 'URL HTTPS no configurada');
        // El fragmento # evita enviar el token al servidor web al abrir el sitio.
        $url = $base . '/#recuperar?token=' . $token;
        try {
            Mail::raw(
                "Se solicitó restablecer la contraseña de Ruta H&D2.\n\n"
                . "Abre este enlace durante los próximos 30 minutos:\n" . $url
                . "\n\nSi no lo solicitaste, ignora el mensaje. La contraseña actual sigue funcionando.",
                static function ($message) use ($usuario): void {
                    $message->to($usuario->correo_electronico)->subject('Ruta H&D2 · Recuperación de cuenta');
                }
            );
        } catch (\Throwable $e) {
            DB::table('restablecimientos_contrasena')->where('hash_token', $hash)->delete();
            report(new \RuntimeException('No se pudo entregar el mensaje de recuperación; se invalidó el token'));
            abort(503, 'Servicio de recuperación no disponible');
        }
    }

    public function solicitar(Request $request)
    {
        $datos = Validator::make($request->all(), [
            'correo_electronico' => ['required', 'email', 'max:190'],
        ])->validate();
        // Si SMTP no está disponible, la respuesta es idéntica para todas las cuentas.
        abort_unless(config('mail.default') === 'smtp', 503, 'Servicio de recuperación no disponible');
        $correo = mb_strtolower(trim($datos['correo_electronico']));
        $clave = 'recuperar:' . hash('sha256', $correo . '|' . $request->ip());
        // Límite por combinación cuenta/IP además del throttle de la ruta.
        if (RateLimiter::tooManyAttempts($clave, 3)) {
            return response()->json(['mensaje' => self::RESPUESTA])->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($clave, 3600);
        $usuario = Usuario::where('correo_electronico', $correo)->where('activo', true)->first();
        if ($usuario) self::enviarEnlace($usuario);
        return response()->json(['mensaje' => self::RESPUESTA])->header('Cache-Control', 'no-store');
    }

    public function restablecer(Request $request)
    {
        $datos = Validator::make($request->all(), [
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'contrasena' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ])->validate();

        DB::transaction(function () use ($datos): void {
            $registro = DB::table('restablecimientos_contrasena')
                ->where('hash_token', hash('sha256', $datos['token']))
                ->lockForUpdate()->first();
            if (!$registro || now()->greaterThanOrEqualTo($registro->expira_en)) {
                throw ValidationException::withMessages(['token' => 'Enlace inválido o vencido']);
            }
            $usuario = Usuario::query()->find($registro->usuario_id);
            if (!$usuario || !$usuario->activo) {
                throw ValidationException::withMessages(['token' => 'Enlace inválido o vencido']);
            }
            $usuario->hash_contrasena = Hash::make($datos['contrasena']);
            $usuario->save();
            DB::table('restablecimientos_contrasena')->where('usuario_id', $usuario->id)->delete();
            DB::table('tokens_acceso')->where('usuario_id', $usuario->id)->delete();
        });
        return response()->json(['mensaje' => 'Contraseña cambiada. Vuelve a iniciar sesión.'])
            ->header('Cache-Control', 'no-store');
    }
}

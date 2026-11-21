<?php
declare(strict_types=1);
namespace App\Console\Commands;

use App\Http\Controllers\RecuperacionContrasenaControlador;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\RutChileno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class CrearAdministrador extends Command
{
    protected $signature='rutahd:crear-administrador {correo} {--rut=} {--nombre=}';
    protected $description='Alta privada del administrador con RUT verificado y enlace de contraseña por correo';

    public function handle(): int
    {
        $correo=mb_strtolower(trim((string)$this->argument('correo')));
        $rut=RutChileno::normalizar((string)$this->option('rut'));
        $nombre=trim((string)$this->option('nombre'));
        if (!filter_var($correo,FILTER_VALIDATE_EMAIL) || strlen($correo)>190
            || !$rut || mb_strlen($nombre)<3 || mb_strlen($nombre)>160) {
            $this->error('Correo, nombre o RUT inválidos. El RUT debe incluir dígito verificador correcto.');
            return self::FAILURE;
        }
        // Sin SMTP o tabla de tokens NO se crea una cuenta inaccesible.
        if (config('mail.default')!=='smtp'
            || !Schema::hasColumn('usuarios','rut')
            || !Schema::hasTable('restablecimientos_contrasena')
            || !Schema::hasTable('tokens_acceso')) {
            $this->error('Faltan SMTP o migraciones 12, 18 y 19. Verifica y respalda la base antes.');
            return self::FAILURE;
        }
        if (Usuario::query()->where('correo_electronico',$correo)
                ->orWhere('rut',$rut)->exists()) {
            $this->error('Ya existe un usuario con este correo o RUT. No se creó una cuenta duplicada.');
            return self::FAILURE;
        }
        $rol=Rol::query()->where('codigo','administrador')->first();
        if (!$rol) {
            $this->error('Primero instala el perfil administrador de la base española.');
            return self::FAILURE;
        }
        try {
            DB::transaction(function () use($correo,$rut,$nombre,$rol): void {
                // El usuario no recibe una clave fija ni se imprime una clave o hash.
                $usuario=Usuario::query()->create([
                    'nombre'=>$nombre,'rut'=>$rut,
                    'correo_electronico'=>$correo,'rol_id'=>$rol->id,
                    'hash_contrasena'=>Hash::make(bin2hex(random_bytes(32))),
                    'activo'=>true,
                ]);
                RecuperacionContrasenaControlador::enviarEnlace($usuario);
            });
        } catch (\Throwable) {
            $this->error('No fue posible crear y notificar al administrador; operación revertida.');
            return self::FAILURE;
        }
        $this->info('Cuenta administradora creada. Se envió enlace de un solo uso para definir contraseña.');
        return self::SUCCESS;
    }
}

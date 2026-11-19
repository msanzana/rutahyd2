<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
final class CrearAdministrador extends Command
{
    protected $signature='rutahd:crear-administrador {correo}';
    protected $description='Crea de manera local el primer administrador de RutaH&D2 V2';
    public function handle(): int
    {
        $correo=(string)$this->argument('correo');
        if(!filter_var($correo,FILTER_VALIDATE_EMAIL)){ $this->error('Correo inválido');return self::FAILURE; }
        if(Usuario::query()->where('correo_electronico',$correo)->exists()){
            $this->error('El correo ya existe');return self::FAILURE;
        }
        $rol=Rol::query()->where('codigo','administrador')->first();
        if(!$rol){$this->error('Primero importar semillas en español');return self::FAILURE;}
        $nombre=(string)$this->ask('Nombre del administrador');
        $pass=(string)$this->secret('Contraseña larga del administrador');
        $errors=Validator::make(['nombre'=>$nombre,'contrasena'=>$pass],[
            'nombre'=>['required','string','min:3','max:160'],
            'contrasena'=>['required','string','min:12'],
        ])->errors();
        if($errors->isNotEmpty()){$this->error($errors->first());return self::FAILURE;}
        Usuario::query()->create(['rol_id'=>$rol->id,'nombre'=>$nombre,
            'correo_electronico'=>$correo,'hash_contrasena'=>Hash::make($pass),'activo'=>true]);
        $this->info('Administrador creado sin mostrar ni guardar la contraseña');
        return self::SUCCESS;
    }
}

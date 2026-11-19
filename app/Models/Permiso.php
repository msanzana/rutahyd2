<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Permiso extends Model
{
    protected $table = 'permisos';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DiaLaboralProgramado extends Model
{
    protected $table = 'dias_laborales_programados';
    public $timestamps = false;

    protected $fillable = [
        'mes_objetivo',
        'fecha_laboral',
        'habilitado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'mes_objetivo' => 'date',
            'fecha_laboral' => 'date',
            'habilitado' => 'boolean',
        ];
    }
}

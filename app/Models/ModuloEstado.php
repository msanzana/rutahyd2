<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class ModuloEstado extends Model
{
    protected $table='modulos_estados';
    public $timestamps=false;
    protected $fillable=['modulo_id','estado_id','orden','activo'];
    public function modulo(): BelongsTo {return $this->belongsTo(Modulo::class,'modulo_id');}
    public function estado(): BelongsTo {return $this->belongsTo(Estado::class,'estado_id');}
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

}

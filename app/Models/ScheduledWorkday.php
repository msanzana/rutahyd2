<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledWorkday extends Model
{
    protected $table = 'scheduled_workdays';
    public $timestamps = false;

    protected $fillable = [
        'target_month',
        'work_date',
        'enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_month' => 'date',
            'work_date' => 'date',
            'enabled' => 'boolean',
        ];
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRefreshRun extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'successful',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'successful' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSourceCheck extends Model
{
    protected $primaryKey = 'source';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source',
        'status',
        'message',
        'last_checked_at',
        'last_successful_at',
        'records_found',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'immutable_datetime',
            'last_successful_at' => 'immutable_datetime',
            'records_found' => 'integer',
        ];
    }
}

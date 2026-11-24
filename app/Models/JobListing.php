<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
    protected $fillable = [
        'source',
        'source_listing_key',
        'entry_method',
        'title',
        'company',
        'job_url',
        'employer_apply_url',
        'posted_at',
        'employment_type',
        'pay',
        'description',
        'qualifications',
        'is_open',
        'is_remote',
        'accepts_philippines',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
            'is_open' => 'boolean',
            'is_remote' => 'boolean',
            'accepts_philippines' => 'boolean',
        ];
    }
}

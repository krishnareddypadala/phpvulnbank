<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Transfer ledger. New in the Laravel port -- the legacy app kept no record
 * of money movement at all.
 */
class Transaction extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

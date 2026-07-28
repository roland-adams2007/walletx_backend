<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'mailable',
        'recipient_email',
        'subject',
        'loggable_type',
        'loggable_id',
        'sent_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}

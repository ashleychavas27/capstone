<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    public const STATUS_SENT = 'Sent';

    public const STATUS_FAILED = 'Failed';

    public const STATUS_MOCKED = 'Mocked';

    protected $fillable = [
        'recipient_phone',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

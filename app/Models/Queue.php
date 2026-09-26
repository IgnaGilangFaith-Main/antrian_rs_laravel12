<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Queue extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'WAITING';

    public const STATUS_CALLED = 'CALLED';

    public const STATUS_DONE = 'DONE';

    public const STATUS_SKIPPED = 'SKIPPED';

    // called_at/finished_at WAJIB ada di sini: Model::update() Hormati $fillable,
    // jadi kolom yang tak terdaftar dibuang diam-diam (status/counter_id tetap masuk).
    protected $fillable = [
        'public_token', 'queue_date', 'queue_number', 'queue_label', 'status', 'counter_id',
        'called_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'queue_number' => 'integer',
            'called_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $queue) {
            $queue->public_token ??= (string) Str::uuid();
        });
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }
}

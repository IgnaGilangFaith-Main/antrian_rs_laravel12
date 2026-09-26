<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dikirim setelah admin mengosongkan antrean: TV display harus ikutonkosong,
 * kalau tidak layar masih menampilkan nomor lama yang sudah tidak berlaku.
 */
class QueueResetEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $queueDate, public int $deleted) {}

    public function broadcastOn(): array
    {
        return [new Channel('queue-channel')];
    }

    public function broadcastAs(): string
    {
        return 'queue.reset';
    }

    public function broadcastWith(): array
    {
        return ['queue_date' => $this->queueDate, 'deleted' => $this->deleted];
    }
}

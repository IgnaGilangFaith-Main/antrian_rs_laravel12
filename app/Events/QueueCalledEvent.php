<?php

namespace App\Events;

use App\Models\Queue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueCalledEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $queueNumber,
        public int $counterNumber,
        public string $status,
        public string $queueLabel,
    ) {}

    public static function fromQueue(Queue $queue): self
    {
        return new self(
            queueNumber: $queue->queue_number,
            counterNumber: (int) $queue->counter?->counter_number,
            status: $queue->status,
            queueLabel: $queue->queue_label,
        );
    }

    /** Channel publik agar TV display tidak butuh auth. */
    public function broadcastOn(): array
    {
        return [new Channel('queue-channel')];
    }

    public function broadcastAs(): string
    {
        return 'queue.called';
    }

    public function broadcastWith(): array
    {
        return [
            'queue_number' => $this->queueNumber,
            'queue_label' => $this->queueLabel,
            'counter_number' => $this->counterNumber,
            'status' => $this->status,
        ];
    }
}

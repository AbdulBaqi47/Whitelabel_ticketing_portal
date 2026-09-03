<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DepositRequested implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deposit_id;
    public $userIds;
    public $type;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct($deposit_id, array $userIds, $type, $message)
    {
        $this->deposit_id = $deposit_id;
        $this->userIds = $userIds;
        $this->type = $type;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn($userId) => new PrivateChannel('user.' . $userId), $this->userIds);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'deposit.requested';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'notification_id' => $this->deposit_id,
        ];
    }
}

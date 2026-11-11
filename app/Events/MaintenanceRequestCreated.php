<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestCreated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public array $data) {}

    // Private: it carries the request's number and title. Only the staff who get the new-job bell may listen (routes/channels.php).
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('maintenance-requests');
    }

    public function broadcastAs(): string
    {
        return 'maintenance.created';
    }
}

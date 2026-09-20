<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestCreated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public array $data) {}

    public function broadcastOn(): Channel
    {
        return new Channel('maintenance-requests');
    }

    public function broadcastAs(): string
    {
        return 'maintenance.created';
    }
}

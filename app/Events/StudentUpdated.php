<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $student;
    public $courseId;
    public $previousExemptData;

    /**
     * Create a new event instance.
     */
    public function __construct($courseId , $student, $previousExemptData = null)
    {
        $this->student = $student;
        $this->courseId = $courseId;
        $this->previousExemptData = $previousExemptData;
    }


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}

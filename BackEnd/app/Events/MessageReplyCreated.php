<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReplyCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message, public int $recipientId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.reply.created';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing(['sender:id,name,role', 'recipient:id,name,role', 'replies.sender:id,name']);
        $message->setAttribute('unread_for_user', true);
        return ['message' => $message->toArray()];
    }
}

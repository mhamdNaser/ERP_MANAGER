<?php
namespace App\Modules\Communications\Repositories\Interfaces;

use App\Models\Circular;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface CommunicationRepositoryInterface {
    public function directory(User $actor): array;
    public function messages(User $actor): Collection;
    public function unreadMessagesCount(User $actor): int;
    public function createMessage(User $actor, array $data): Message;
    public function markMessageRead(User $actor, Message $message): Message;
    public function reply(User $actor, Message $message, array $data): Message;
    public function deleteMessage(User $actor, Message $message): bool;
    public function circulars(User $actor): Collection;
    public function findCircular(User $actor, Circular $circular): Circular;
    public function createCircular(User $actor, array $data): Circular;
}

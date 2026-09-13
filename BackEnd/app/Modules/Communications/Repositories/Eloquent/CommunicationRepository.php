<?php
namespace App\Modules\Communications\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\Circular;
use App\Models\CndNotification;
use App\Models\Message;
use App\Models\MessageReply;
use App\Models\Office;
use App\Models\User;
use App\Events\MessageCreated;
use App\Events\MessageReplyCreated;
use App\Modules\Communications\Repositories\Interfaces\CommunicationRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CommunicationRepository implements CommunicationRepositoryInterface
{
    public function directory(User $actor): array
    {
        $users = User::with(['branch:id,name','department:id,name','office:id,name'])->where('is_active',true)->whereKeyNot($actor->id)
            ->when($actor->primaryRole() !== 'general_manager' && ! $actor->office_id, fn($query)=>$query->where('role','!=','general_manager'))->get();
        return [
            'branches'=>Branch::with(['departments'=>fn($query)=>$query->with(['users'=>fn($usersQuery)=>$usersQuery->whereIn('id',$users->pluck('id'))->select('users.id','name','email','role','department_id')])])->get(),
            'offices'=>Office::with(['users'=>fn($query)=>$query->whereIn('id',$users->pluck('id'))->select('users.id','name','email','role','office_id')])->get(),
        ];
    }

    public function messages(User $actor): Collection
    {
        return $this->withUnreadState(Message::with(['sender:id,name,role','recipient:id,name,role','replies.sender:id,name'])->where(fn($query)=>$query->where('sender_id',$actor->id)->orWhere('recipient_id',$actor->id))->latest()->get(), $actor);
    }

    public function unreadMessagesCount(User $actor): int
    {
        return Message::where(fn($query)=>$query->where('sender_id',$actor->id)->orWhere('recipient_id',$actor->id))
            ->get()
            ->filter(fn (Message $message) => $this->isUnreadFor($message, $actor))
            ->count();
    }

    public function createMessage(User $actor, array $data): Message
    {
        $recipient=User::findOrFail($data['recipient_id']);
        if ($recipient->id === $actor->id || ($recipient->primaryRole()==='general_manager' && $actor->primaryRole()!=='general_manager' && ! $actor->office_id)) throw new AuthorizationException(__('messages.organization_forbidden'));
        $message=Message::create($data+['sender_id'=>$actor->id,'sender_read_at'=>now()]);
        CndNotification::create(['user_id'=>$recipient->id,'title'=>__('messages.message_received'),'message'=>$data['subject']]);
        $message->load(['sender:id,name,role','recipient:id,name,role','replies.sender:id,name']);
        $this->broadcastSafely(fn () => MessageCreated::dispatch($message));
        return $message;
    }

    public function markMessageRead(User $actor, Message $message): Message
    {
        if (! in_array($actor->id,[$message->sender_id,$message->recipient_id])) throw new AuthorizationException(__('messages.organization_forbidden'));

        $message->forceFill($actor->id === $message->sender_id ? ['sender_read_at' => now()] : ['recipient_read_at' => now(), 'read_at' => $message->read_at ?? now()])->save();

        return $this->withUnreadState($message->fresh()->load(['sender:id,name,role','recipient:id,name,role','replies.sender:id,name']), $actor);
    }

    public function reply(User $actor, Message $message, array $data): Message
    {
        if (! $message->allow_reply || ! in_array($actor->id,[$message->sender_id,$message->recipient_id])) throw new AuthorizationException(__('messages.organization_forbidden'));
        MessageReply::create($data+['message_id'=>$message->id,'sender_id'=>$actor->id]);
        $target=$actor->id===$message->sender_id?$message->recipient_id:$message->sender_id;
        $message->forceFill([
            'last_reply_at' => now(),
            'last_reply_sender_id' => $actor->id,
            'sender_read_at' => $actor->id === $message->sender_id ? now() : null,
            'recipient_read_at' => $actor->id === $message->recipient_id ? now() : null,
        ])->save();
        CndNotification::create(['user_id'=>$target,'title'=>__('messages.message_reply'),'message'=>$message->subject]);
        $updated = $message->fresh()->load(['sender:id,name,role','recipient:id,name,role','replies.sender:id,name']);
        $this->broadcastSafely(fn () => MessageReplyCreated::dispatch($updated, $target));
        return $this->withUnreadState($updated, $actor);
    }

    public function deleteMessage(User $actor, Message $message): bool
    {
        if ($message->sender_id !== $actor->id) {
            throw new AuthorizationException(__('messages.organization_forbidden'));
        }

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }
        $message->replies->each(function (MessageReply $reply) {
            if ($reply->attachment_path) {
                Storage::disk('public')->delete($reply->attachment_path);
            }
        });

        return (bool) $message->delete();
    }

    public function circulars(User $actor): Collection
    {
        return Circular::with('issuer:id,name,role')->where(fn($query)=>$query->where('issuer_id',$actor->id)->orWhereHas('recipients',fn($recipients)=>$recipients->where('users.id',$actor->id)))->latest()->get();
    }

    public function findCircular(User $actor, Circular $circular): Circular
    {
        abort_unless($circular->issuer_id === $actor->id || $circular->recipients()->where('users.id', $actor->id)->exists(), 403);
        return $circular->load('issuer:id,name,role');
    }

    public function createCircular(User $actor, array $data): Circular
    {
        $role = $actor->primaryRole();
        $audience = $data['audience'];
        $allowed = match ($role) {
            'department_head' => ['department_all', 'department_fixed_employees', 'department_contract_employees'],
            'branch_manager' => ['branch_heads', 'branch_all', 'branch_fixed_employees', 'branch_contract_employees'],
            'general_manager' => ['general_branch_managers', 'general_management', 'general_all', 'general_fixed_employees', 'general_contract_employees'],
            default => [],
        };

        if (!in_array($audience, $allowed, true)) {
            throw new AuthorizationException(__('messages.organization_forbidden'));
        }

        $query = User::where('is_active', true)->whereKeyNot($actor->id);
        $this->applyCircularAudience($query, $actor, $audience);

        return DB::transaction(function () use ($actor, $data, $query) {
            $circular = Circular::create($data + ['issuer_id' => $actor->id]);
            $ids = $query->pluck('id');
            $circular->recipients()->sync($ids);
            $ids->each(fn ($id) => CndNotification::create(['user_id' => $id, 'title' => __('messages.circular_received'), 'message' => $data['title'], 'circular_id' => $circular->id]));
            return $circular->load('issuer:id,name,role');
        });
    }

    private function applyCircularAudience($query, User $actor, string $audience): void
    {
        match ($audience) {
            'department_all' => $query->where('department_id', $actor->department_id)->whereIn('role', ['employee', 'technician']),
            'department_fixed_employees' => $query->where('department_id', $actor->department_id)->whereIn('role', ['employee', 'technician'])->where('employment_type', 'fixed'),
            'department_contract_employees' => $query->where('department_id', $actor->department_id)->whereIn('role', ['employee', 'technician'])->where('employment_type', 'contract'),
            'branch_heads' => $query->where('branch_id', $actor->branch_id)->where('role', 'department_head'),
            'branch_all' => $query->where('branch_id', $actor->branch_id)->whereIn('role', ['department_head', 'employee', 'technician']),
            'branch_fixed_employees' => $query->where('branch_id', $actor->branch_id)->whereIn('role', ['employee', 'technician'])->where('employment_type', 'fixed'),
            'branch_contract_employees' => $query->where('branch_id', $actor->branch_id)->whereIn('role', ['employee', 'technician'])->where('employment_type', 'contract'),
            'general_branch_managers' => $query->where('role', 'branch_manager'),
            'general_management' => $query->whereIn('role', ['branch_manager', 'department_head']),
            'general_all' => $query->whereIn('role', ['branch_manager', 'department_head', 'employee', 'technician']),
            'general_fixed_employees' => $query->whereIn('role', ['employee', 'technician'])->where('employment_type', 'fixed'),
            'general_contract_employees' => $query->whereIn('role', ['employee', 'technician'])->where('employment_type', 'contract'),
            default => null,
        };
    }


    private function broadcastSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Message realtime broadcast failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function withUnreadState(Message|Collection $messageOrMessages, User $actor): Message|Collection
    {
        if ($messageOrMessages instanceof Collection) {
            return $messageOrMessages->each(fn (Message $message) => $this->attachUnreadState($message, $actor));
        }

        return $this->attachUnreadState($messageOrMessages, $actor);
    }

    private function attachUnreadState(Message $message, User $actor): Message
    {
        $message->setAttribute('unread_for_user', $this->isUnreadFor($message, $actor));
        return $message;
    }

    private function isUnreadFor(Message $message, User $actor): bool
    {
        if ($actor->id === $message->recipient_id && ! $message->recipient_read_at) {
            return true;
        }

        if ($actor->id === $message->sender_id && $message->last_reply_sender_id && $message->last_reply_sender_id !== $actor->id && ! $message->sender_read_at) {
            return true;
        }

        return false;
    }
}

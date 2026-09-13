<?php
namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CndNotification;
use App\Modules\Notifications\Repositories\Interfaces\NotificationRepositoryInterface;
use App\Modules\Notifications\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(private NotificationRepositoryInterface $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection($this->notifications->visibleTo($request->user()));
    }

    public function show(Request $request, CndNotification $notification): NotificationResource
    {
        return new NotificationResource($this->notifications->findFor($request->user(), $notification));
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());
        return response()->json(['message' => __('messages.report_updated')]);
    }
}

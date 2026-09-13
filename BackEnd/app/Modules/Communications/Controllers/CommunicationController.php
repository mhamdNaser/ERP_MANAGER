<?php
namespace App\Modules\Communications\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Circular;
use App\Models\Message;
use App\Modules\Communications\Repositories\Interfaces\CommunicationRepositoryInterface;
use App\Modules\Communications\Resources\CircularResource;
use App\Modules\Communications\Requests\StoreCircularRequest;
use App\Modules\Communications\Requests\StoreMessageReplyRequest;
use App\Modules\Communications\Requests\StoreMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommunicationController extends Controller {
    public function __construct(private CommunicationRepositoryInterface $communications){}
    public function directory(Request $request): JsonResponse{return response()->json($this->communications->directory($request->user()));}
    public function messages(Request $request): JsonResponse{return response()->json($this->communications->messages($request->user()));}
    public function unreadMessagesCount(Request $request): JsonResponse{return response()->json(['count'=>$this->communications->unreadMessagesCount($request->user())]);}
    public function storeMessage(StoreMessageRequest $request): JsonResponse{
        $data=$request->validated();
        if($request->hasFile('attachment')){
            $file=$request->file('attachment');
            $data['attachment_path']=$file->store('communications/messages','public');
            $data['attachment_name']=$file->getClientOriginalName();
            $data['attachment_mime']=$file->getClientMimeType();
            $data['attachment_size']=$file->getSize();
        }
        return response()->json($this->communications->createMessage($request->user(),$data),201);
    }
    public function markMessageRead(Request $request, Message $message): JsonResponse{return response()->json($this->communications->markMessageRead($request->user(),$message));}
    public function reply(StoreMessageReplyRequest $request,Message $message): JsonResponse{
        $data=$request->validated();
        if($request->hasFile('attachment')){
            $file=$request->file('attachment');
            $data['attachment_path']=$file->store('communications/message-replies','public');
            $data['attachment_name']=$file->getClientOriginalName();
            $data['attachment_mime']=$file->getClientMimeType();
            $data['attachment_size']=$file->getSize();
        }
        return response()->json($this->communications->reply($request->user(),$message,$data));
    }
    public function deleteMessage(Request $request, Message $message): JsonResponse
    {
        $this->communications->deleteMessage($request->user(), $message);
        return response()->json(['message' => 'deleted']);
    }
    public function circulars(Request $request): JsonResponse{return response()->json($this->communications->circulars($request->user()));}
    public function showCircular(Request $request, Circular $circular): CircularResource{return new CircularResource($this->communications->findCircular($request->user(), $circular));}
    public function storeCircular(StoreCircularRequest $request): JsonResponse{
        $data=$request->validated();
        if($request->hasFile('attachment')){
            $file=$request->file('attachment');
            $data['attachment_path']=$file->store('communications/circulars','public');
            $data['attachment_name']=$file->getClientOriginalName();
            $data['attachment_mime']=$file->getClientMimeType();
            $data['attachment_size']=$file->getSize();
        }
        return response()->json($this->communications->createCircular($request->user(),$data),201);
    }
}

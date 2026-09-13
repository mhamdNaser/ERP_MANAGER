<?php
namespace App\Modules\Offices\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Modules\Offices\Repositories\Interfaces\OfficeRepositoryInterface;
use App\Modules\Offices\Requests\StoreOfficeRequest;
use App\Modules\Offices\Requests\UpdateOfficeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends Controller {
    public function __construct(private OfficeRepositoryInterface $offices){}
    public function index(): JsonResponse{return response()->json($this->offices->all());}
    public function store(StoreOfficeRequest $request): JsonResponse{return response()->json($this->offices->create($request->validated()),201);}
    public function update(UpdateOfficeRequest $request,Office $office): JsonResponse{return response()->json($this->offices->update($office,$request->validated()));}
    public function destroy(Request $request,Office $office): JsonResponse{$this->offices->delete($office);return response()->json(['message'=>__('messages.office_deleted')]);}
}

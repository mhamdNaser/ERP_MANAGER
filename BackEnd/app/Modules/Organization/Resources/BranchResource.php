<?php
namespace App\Modules\Organization\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array { return ['id'=>$this->id,'name'=>$this->name,'code'=>$this->code,'description'=>$this->description,'is_active'=>$this->is_active,'departments_count'=>$this->whenCounted('departments')]; }
}

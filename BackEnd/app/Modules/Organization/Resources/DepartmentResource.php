<?php
namespace App\Modules\Organization\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array { return ['id'=>$this->id,'name'=>$this->name,'code'=>$this->code,'description'=>$this->description,'is_active'=>$this->is_active,'branch_id'=>$this->branch_id,'branch'=>$this->whenLoaded('branch'),'users_count'=>$this->whenCounted('users')]; }
}

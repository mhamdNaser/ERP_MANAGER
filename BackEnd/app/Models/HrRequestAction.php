<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrRequestAction extends Model
{
    protected $fillable = ['hr_request_id', 'actor_id', 'stage', 'action', 'note'];

    public function request(): BelongsTo { return $this->belongsTo(HrRequest::class, 'hr_request_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}

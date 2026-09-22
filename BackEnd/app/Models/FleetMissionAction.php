<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetMissionAction extends Model
{
    protected $fillable = ['fleet_mission_id', 'actor_id', 'stage', 'action', 'note'];

    public function mission(): BelongsTo { return $this->belongsTo(FleetMission::class, 'fleet_mission_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}

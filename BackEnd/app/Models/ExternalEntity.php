<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalEntity extends Model
{
    protected $fillable = ['name', 'code', 'contact_name', 'contact_email', 'contact_phone', 'address'];
    public function sentFormalCorrespondences(): HasMany { return $this->hasMany(FormalCorrespondence::class, 'sender_external_entity_id'); }
    public function receivedFormalCorrespondences(): HasMany { return $this->hasMany(FormalCorrespondence::class, 'recipient_external_entity_id'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserFamilyDetail extends Model { protected $fillable = ['marital_status','spouse_name','children_count','emergency_contact_name','emergency_contact_phone','emergency_contact_relation']; }

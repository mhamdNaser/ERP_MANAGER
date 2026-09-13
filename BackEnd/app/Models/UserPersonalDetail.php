<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserPersonalDetail extends Model { protected $fillable = ['birth_date','national_id','gender','height_cm','weight_kg','blood_type','shoe_size','trouser_size','shirt_size','jacket_size','uniform_notes']; protected function casts(): array { return ['birth_date'=>'date']; } }

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model { protected $fillable = ['country','city','district','street','building','details']; }

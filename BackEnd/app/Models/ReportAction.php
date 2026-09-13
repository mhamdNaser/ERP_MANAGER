<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAction extends Model
{
    protected $fillable = ['report_id', 'actor_id', 'action', 'from_status', 'to_status', 'note'];
}

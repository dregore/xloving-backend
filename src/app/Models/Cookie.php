<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cookie extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_path',
        'unit_id',
        'task_id'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    // protected $primaryKey = 'unit_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'unit_id', 'status', 'type', 'attempts', 'delay_min', 'delay_max', 'search_term', 'search_field', 'search_scroll', 'install_button', 'delete', 'app_name', 'app_bundle', 'minutes', 'seconds', 'url'
    ];
}

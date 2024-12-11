<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Iksaku\Laravel\MassUpdate\MassUpdatable;

class Task extends Model
{
    use HasFactory, SoftDeletes, MassUpdatable;

    // protected $primaryKey = 'unit_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'status',
        'unit_id',
        'type',
        'attempts',
        'attempts_changes',
        'bundles',
        'delay_min',
        'delay_max',
        'search_term',
        'app_name',
        'app_bundle',
        'search_field',
        'search_scroll',
        'install_button',
        'delete',
        'url',
        'minutes',
        'seconds',
        'like',
        'backup_email_button',
        'backup_code_button',
        'another_method_button',
        'order_id',
        'retention',
        'country',
        'fill_type',
        'open_time',
        'share',
        'skip_button',
        'like_button',
        'confirm_button',
        'account_id',
        'ok_button',
        'deeplink_page',
        'error_type',
        'comment',
        'delayed_date',
        'scroll_duration',
        'retention_bundle_id',
        'proxy_id',
    ];
}

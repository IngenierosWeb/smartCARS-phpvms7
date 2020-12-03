<?php

namespace Modules\SmartAcars\Models;

use App\Contracts\Model;
use App\Models\User;

/**
 * Class Sesion
 * @package Modules\SmartAcars\Models
 */
class Sesion extends Model
{
    public $table = 'smartacars_sessions';
    protected $fillable = [
        'user_id',
        'session'
    ];

    protected $casts = [
        'user_id' => 'integer',

    ];

    public static $rules = [

    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}

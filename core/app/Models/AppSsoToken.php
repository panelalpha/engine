<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property string $token
 * @property string $username
 * @property string $cookie_name
 * @property string $cookie_value
 * @property string $redirect
 * @property Carbon $expires_at
 * @property ?Carbon $used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AppSsoToken extends Model
{
    protected $fillable = [
        'token',
        'username',
        'cookie_name',
        'cookie_value',
        'redirect',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}

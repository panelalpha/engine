<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return self
     */
    public static function rootAccount()
    {
        /** @var self */
        return self::firstOrCreate(
            ['name' => 'root'],
            ['email' => 'root@localhost'],
        );
    }
}

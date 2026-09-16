<?php

namespace App\Models;

use App\Lib\Traits\Models\HasDetails;
use App\System;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $user_id
 * @property string $token
 * @property Carbon $expires_at
 * @property User $user
 * @method static MysqlSsoToken create(array $params)
 */
class MysqlSsoToken extends Model
{
    use HasFactory, HasDetails;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'details',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expired(): bool
    {
        return Carbon::now() > $this->expires_at;
    }

    public function getCredentials(): array
    {
        $username = 'sso_' . $this->user->username;
        $password = Str::random(27);

        $mysql = (new System())->mysql();
        
        /** @var MysqlSsoUser */
        $ssoUser = MysqlSsoUser::firstOrNew([
            'user_id' => $this->user_id,
            'username' => $username,
        ]);

        if (!$mysql->users()->userExists($username)) {
            $mysql->users()->createUser($username, $password);
        } else {
            $mysql->users()->changeUserPassword($username, $password);
        }

        $mysql->privileges()->revokeAll($username);

        foreach($this->user->mysqlDatabases as $db) {
            $mysql->privileges()->updatePrivileges($username, $db->database, 'ALL PRIVILEGES');
        }

        $ssoUser->save();

        return [
            'username' => $username,
            'password' => $password,
        ];
    }
}

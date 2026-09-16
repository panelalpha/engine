<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\SftpAccount;

class SftpAccountResource extends JsonResource
{
    /**
     * @param \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $sftpAccount = $this->resource;
        assert($sftpAccount instanceOf SftpAccount);

        return [
            'id' => $sftpAccount->id,
            'user_id' => $sftpAccount->user_id,
            'username' => $sftpAccount->username,
            'auth_method' => $sftpAccount->auth_method,
            'created_at' => $sftpAccount->created_at,
            'updated_at' => $sftpAccount->updated_at,
        ];
    }
}

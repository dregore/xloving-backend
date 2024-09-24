<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
        // switch ($this->type) {
        //     case 'google':
        //         return [
        //             // 'account' => [
        //             'id' => $this->id,
        //             'type' => $this->type,
        //             'email' => $this->email,
        //             'password' => $this->password,
        //             'country' => $this->country,
        //             'backup_email' => $this->backup_email,
        //             // ]
        //         ];
        //         break;

        //     case 'tiktok':
        //         return [
        //             // 'account' => [
        //             'id' => $this->id,
        //             'type' => $this->type,
        //             'email' => $this->email,
        //             'password' => $this->password,
        //             'country' => $this->country,
        //             // ]
        //         ];
        //         break;
        // }
    }
}

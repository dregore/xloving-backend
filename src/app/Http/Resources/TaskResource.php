<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Http\Resources\ProxyResource;
use App\Models\Proxy;
use App\Http\Resources\GooglecodeResource;
use App\Models\Googlecode;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        if ($this->type == 'install') {
            return [
                'task' => [
                    'id' => $this->id,
                    'unit_id' => $this->unit_id,
                    'status' => $this->status,
                    'type' => $this->type,
                    'attempts' => $this->attempts,
                    'delay_min' => $this->delay_min,
                    'delay_max' => $this->delay_max,
                    'search_term' => $this->search_term,
                    'app_name' => $this->app_name,
                    'app_bundle' => $this->app_bundle,
                    'search_field' => $this->search_field,
                    'search_scroll' => $this->search_scroll,
                    'install_button' => $this->install_button,
                    'delete' => (bool) $this->delete,
                    'url' => $this->url,
                    'minutes' => $this->minutes,
                    'seconds' => $this->seconds,
                    'like' => (bool) $this->like,
                ],
                // 'account' => AccountsResource::collection(Accounts::all()),
                'account' => new AccountResource(Account::where('type', 'google')->inRandomOrder()->first()),
                'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
                // 'codes' => new GooglecodeResource(Googlecode::all()),
                'codes' => Googlecode::where('status', 0)->get(['id', 'code', 'status']),
                // 'account' => Accounts::first(),

                // 'task' => parent::toArray($request),
                // 'account' => new AccountsResource(Accounts::first()),
            ];
        }

        if ($this->type == 'tiktok') {
            return [
                'task' => [
                    'id' => $this->id,
                    'unit_id' => $this->unit_id,
                    'status' => $this->status,
                    'type' => $this->type,
                    'attempts' => $this->attempts,
                    'delay_min' => $this->delay_min,
                    'delay_max' => $this->delay_max,
                    'url' => $this->url,
                    'minutes' => $this->minutes,
                    'seconds' => $this->seconds,
                    'like' => (bool) $this->like,
                ],
                'account' => new AccountResource(Account::where('type', 'tiktok')->inRandomOrder()->first()),
                'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
            ];
        }

        if ($this->type == 'youtube') {
            return [
                'task' => [
                    'id' => $this->id,
                    'unit_id' => $this->unit_id,
                    'status' => $this->status,
                    'type' => $this->type,
                    'attempts' => $this->attempts,
                    'delay_min' => $this->delay_min,
                    'delay_max' => $this->delay_max,
                    'url' => $this->url,
                    'minutes' => $this->minutes,
                    'seconds' => $this->seconds,
                    'like' => (bool) $this->like,
                ],
                'account' => new AccountResource(Account::where('type', 'youtube')->inRandomOrder()->first()),
                'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
            ];
        }
    }
}

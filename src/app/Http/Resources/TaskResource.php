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

        /*
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
                    'backup_email_button' => $this->backup_email_button,
                    'backup_code_button' => $this->backup_code_button,
                    'another_method_button' => $this->another_method_button,
                    'delete' => (bool) $this->delete,
                    // 'url' => $this->url,
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
                    // 'url' => $this->url,
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
                'account' => new AccountResource(Account::where('type', 'google')->inRandomOrder()->first()),
                'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
            ];
        }
        */

        if ($this->type == 'install' || $this->type == 'launch' || $this->type == 'uninstall') {
            return [
                'task' => [
                    'id' => $this->id,
                    'unit_id' => $this->unit_id,
                    'status' => $this->status,
                    'type' => $this->type,
                    'attempts' => $this->attempts,
                    'delay_min' => $this->delay_min,
                    'delay_max' => $this->delay_max,
                    'app_name' => $this->app_name,
                    'app_bundle' => $this->app_bundle,
                    'delete' => (bool) $this->delete,
                    // 'url' => $this->url,
                    'minutes' => $this->minutes,
                    'seconds' => $this->seconds,
                    'like' => (bool) $this->like,
                    'search_term' => $this->search_term,
                    'search_field' => $this->search_field,
                    'search_scroll' => $this->search_scroll,
                    'install_button' => $this->install_button,
                    'backup_email_button' => $this->backup_email_button,
                    'backup_code_button' => $this->backup_code_button,
                    'another_method_button' => $this->another_method_button,
                    'skip_button' => $this->skip_button,
                    'like_button' => $this->like_button,
                    'confirm_button' => $this->confirm_button,
                    'ok_button' => $this->ok_button,
                    'deeplink_page' => (bool) $this->deeplink_page,
                    'scroll_duration' => $this->scroll_duration,
                    'proxy_id' => $this->proxy_id,
                    'app_company' => $this->app_company,
                ],
                // 'account' => AccountsResource::collection(Accounts::all()),
                // 'account' => new AccountResource(Account::where('type', 'google')->inRandomOrder()->first()),
                // 'proxy' => new ProxyResource(Proxy::where('country', $this->country)->inRandomOrder()->first()),
                // 'proxy' => new ProxyResource(Proxy::where('country', $this->country)->where('id', $this->proxy_id)->first()),
                'proxy' => new ProxyResource(Proxy::where('id', $this->proxy_id)->first()),
                'account' => new AccountResource(Account::where('type', 'google')->where('id', $this->account_id)->first()),
                // 'proxy' => new ProxyResource(Proxy::where('country', $this->country)->first()),
                // 'codes' => new GooglecodeResource(Googlecode::all()),
                // 'codes' => Googlecode::where('status', 0)->get(['id', 'code', 'status']),
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
                    'share' => (bool) $this->share,
                    'search_field' => $this->search_field,
                    'search_scroll' => $this->search_scroll,
                    'install_button' => $this->install_button,
                    'backup_email_button' => $this->backup_email_button,
                    'backup_code_button' => $this->backup_code_button,
                    'another_method_button' => $this->another_method_button,
                    'skip_button' => $this->skip_button,
                    'like_button' => $this->like_button,
                    'confirm_button' => $this->confirm_button,
                    'ok_button' => $this->ok_button,
                    'deeplink_page' => (bool) $this->deeplink_page,
                ],
                'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
                'account' => new AccountResource(Account::where('country', $this->country)->where('type', 'tiktok')->where('id', $this->account_id)->first()),
                // 'account' => new AccountResource(Account::where('type', 'tiktok')->inRandomOrder()->first()),
                // 'account' => new AccountResource(Account::where('type', 'tiktok')->where('id', $this->account_id)->first()),
                // 'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
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
                    'search_field' => $this->search_field,
                    'search_scroll' => $this->search_scroll,
                    'install_button' => $this->install_button,
                    'backup_email_button' => $this->backup_email_button,
                    'backup_code_button' => $this->backup_code_button,
                    'another_method_button' => $this->another_method_button,
                    'skip_button' => $this->skip_button,
                    'like_button' => $this->like_button,
                    'confirm_button' => $this->confirm_button,
                    'ok_button' => $this->ok_button,
                    'deeplink_page' => (bool) $this->deeplink_page,
                ],
                // 'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
                'proxy' => new ProxyResource(Proxy::where('id', $this->proxy_id)->first()),
                'account' => new AccountResource(Account::where('country', $this->country)->where('type', 'google')->where('id', $this->account_id)->first()),
                // 'account' => new AccountResource(Account::where('type', 'google')->inRandomOrder()->first()),
                // 'account' => new AccountResource(Account::where('type', 'google')->where('id', $this->account_id)->first()),
                // 'proxy' => new ProxyResource(Proxy::inRandomOrder()->first()),
                // 'codes' => Googlecode::where('status', 0)->get(['id', 'code', 'status']),
            ];
        }

        if ($this->type == 'export_chrome_cookies' || $this->type == 'import_chrome_cookies') {
            return [
                'task' => [
                    'id' => $this->id,
                    'unit_id' => $this->unit_id,
                    'task_id' => $this->id,
                    'status' => $this->status,
                    'type' => $this->type,
                ],
            ];
        }
    }
}

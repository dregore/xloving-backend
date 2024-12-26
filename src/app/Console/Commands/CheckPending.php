<?php

namespace App\Console\Commands;

use Generator;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Proxy;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check pendings tasks once a minute. Set accounts for it and set status created for tasks.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Check pending Job running at " . now());

        $message = '{prefix} Get all live units';
        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $shuffled = $units->shuffle();

        if ($units->count() == 0) {
            $message = '{prefix} There is no live units... exit';
            info($message, ['prefix' => '[otherPendingTasks]']);

            return;
        }

        /**
         * NO RETENTION TASKS
         */
        $this->otherPendingTasks($shuffled);

        /**
         * RETENTION TASKS
         */
        $this->retentionPendingTasks();
    }

    private function otherPendingTasks($units)
    {
        $message = '{prefix} Check pending tasks without retention';
        info($message, ['prefix' => '[otherPendingTasks]']);

        $tasks = Task::where('status', 'pending')->where('retention', 0)->whereIn('type', ['install', 'youtube', 'tiktok'])->orderBy('id', 'desc')->get();

        $message = '{prefix} otherPendingTasks count: ' . $tasks->count();
        info($message, ['prefix' => '[otherPendingTasks]']);

        if ($tasks->count() == 0) {
            $message = '{prefix} There is no pending tasks... exit';
            info($message, ['prefix' => '[otherPendingTasks]']);

            return;
        }

        $message = '{prefix} Shuffle unit ids';
        info($message, ['prefix' => '[otherPendingTasks]']);
        $b = $this->doStuff($units, 'unit_id');

        foreach ($tasks as $task) {
            $message = '{prefix} task id: ' . $task->id;
            info($message, ['prefix' => '[otherPendingTasks]']);

            $proxy_id = 0;

            switch ($task->type) {
                case 'install':
                    $usedAccounts = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'youtube':
                    $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'import_chrome_cookies':
                case 'export_chrome_cookies':
                    $usedAccounts = [];
                    $accountType = 'google';
                    break;

                case 'tiktok':
                    $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'tiktok';
                    break;
            }

            if ($task->country == 'any') {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
            }

            if (!$accounts) {
                $message = '{prefix} There is no accounts... exit';
                info($message, ['prefix' => '[otherPendingTasks]']);

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            if ($accounts->unit_id == 0) {
                $task->unit_id = $b->current();
                $b->next();

                $message = '{prefix} New unit id: ' . $task->unit_id;
                info($message, ['prefix' => '[otherPendingTasks]']);
                Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
            } else {
                $message = '{prefix} Old unit id: ' . $accounts->unit_id;
                info($message, ['prefix' => '[otherPendingTasks]']);
                $task->unit_id =  $accounts->unit_id;
            }

            $status = 'created';
            if ($task->fill_type == 'spread') {
                $now = strtotime(date('Y-m-d H:i:s'));
                $delayed = strtotime($task->delayed_date);
                $time_diff = $delayed - $now;

                $status = ($time_diff >= 0) ? 'delayed' : $status;
            }

            // if ($status == 'created' && $task->proxy_id == 0) {
            //     $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            //     if (!$proxy) {
            //         $message = 'There is no proxy... exit';
            //         echo $message . PHP_EOL;

            //         return;
            //     }

            //     $task->proxy_id = $proxy->id;
            //     Proxy::where('id', $task->proxy_id)->update(['active' => 0]);

            //     $message = 'Proxy: ' . $task->proxy_id;
            //     echo $message . PHP_EOL;
            // }

            // $insertArray[] = [
            //     'id' => $task->id,
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => $status,
            // ];
            Task::where('id', $task->id)->update([
                'unit_id' => $task->unit_id,
                'account_id' => $accounts->id,
                'status' => $status,
                // 'proxy_id' => $task->proxy_id,
            ]);
        }

        // if ($insertArray) {
        //     $chunks = array_chunk($insertArray, 100);
        //     foreach ($chunks as $item) {
        //         Task::upsert($item, uniqueBy: ['id'], update: ['unit_id', 'account_id', 'status']);
        //     }
        //     unset($chunks, $insertArray);
        // }
    }

    private function retentionPendingTasks()
    {
        $message = '{prefix} Check pending tasks with retention';
        info($message, ['prefix' => '[retentionPendingTasks]']);
        $tasks = Task::where('status', 'pending')->whereNot('retention', 0)->where('type', 'install')->orderBy('id', 'desc')->get();
        $message = '{prefix} retentionPendingTasks count: ' . $tasks->count();
        info($message, ['prefix' => '[retentionPendingTasks]']);

        if ($tasks->count() == 0) {
            $message = '{prefix} There is no pending tasks with retention... exit';
            info($message, ['prefix' => '[retentionPendingTasks]']);

            return;
        }

        foreach ($tasks as $task) {
            $message = '{prefix} task id: ' . $task->id;
            // info($message, ['prefix' => '[retentionPendingTasks]']);

            $proxy_id = 0;

            switch ($task->type) {
                case 'install':
                    // case 'launch':
                    $usedAccounts = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('account_id')->get(['account_id']);
                    $usedUnits = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('unit_id')->get(['unit_id']);
                    $accountType = 'google';
                    break;
                case 'youtube':
                    $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'import_chrome_cookies':
                case 'export_chrome_cookies':
                    $usedAccounts = [];
                    $accountType = 'google';
                    break;

                case 'tiktok':
                    $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'tiktok';
                    break;
            }

            if ($task->country == 'any') {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
            }

            if (!$accounts) {
                $message = '{prefix} There is no accounts... exit';
                info($message, ['prefix' => '[retentionPendingTasks]']);

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            $units = Unit::whereNotIn('id', $usedUnits)->where('blocked', 0)->inRandomOrder()->first();

            if (!$units) {
                $message = '{prefix} There is no units... exit';
                info($message, ['prefix' => '[retentionPendingTasks]']);

                return;
            }

            if ($accounts->unit_id == 0) {
                $task->unit_id = $units->id;
                Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);

                $message = '{prefix} New unit id: ' . $task->unit_id;
                info($message, ['prefix' => '[retentionPendingTasks]']);
            } else {
                $message = '{prefix} Old unit id: ' . $accounts->unit_id;
                info($message, ['prefix' => '[retentionPendingTasks]']);
                $task->unit_id =  $accounts->unit_id;
            }
            $message = '{prefix} task id: ' . $task->id . ', unit_id: ' . $task->unit_id . ', account_id: ' . $accounts->id;
            info($message, ['prefix' => '[retentionPendingTasks]']);

            $now = date('Y-m-d H:i:s');
            $delayed = $task->delayed_date;
            $time_diff = abs(strtotime($now) - strtotime($delayed));

            // $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            // if (!$proxy) {
            //     $message = 'There is now proxy... exit';
            //     echo $message . PHP_EOL;

            //     return;
            // }
            // $proxy_id = $proxy->id;
            // Proxy::where('id', $proxy_id)->update(['active' => 0]);

            // update install task
            // $installTasksArray[] = [
            //     'id' => $task->id,
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            //     'delayed_date' => date('Y-m-d H:i:s'),
            // ];
            Task::where('id', $task->id)->update([
                'unit_id' => $task->unit_id,
                'account_id' => $accounts->id,
                'status' => 'created',
                'delayed_date' => date('Y-m-d H:i:s'),
            ]);

            // update launch, uninstall task
            if ($time_diff > 0) {
                Task::where('retention_bundle_id', $task->id)->update([
                    'unit_id' => $task->unit_id,
                    'account_id' => $accounts->id,
                    'status' => 'delayed',
                    'delayed_date' => DB::raw('delayed_date + INTERVAL ' . $time_diff . ' SECOND'),
                ]);
            } else {
                Task::where('retention_bundle_id', $task->id)->update([
                    'unit_id' => $task->unit_id,
                    'account_id' => $accounts->id,
                    'status' => 'delayed',
                ]);
            }
        }

        // if ($installTasksArray) {
        //     $chunks = array_chunk($installTasksArray, 100);
        //     foreach ($chunks as $item) {
        //         Task::upsert($item, uniqueBy: ['id'], update: ['unit_id', 'account_id', 'status', 'delayed_date']);
        //     }
        //     unset($installTasksArray, $chunks);
        // }
    }

    /**
     * Iteration through any array and return id in infinite cycle
     *
     * @param \Illuminate\Database\Eloquent\Collection $array
     * @param string $column
     * 
     * @return Generator
     * 
     */
    private function doStuff(\Illuminate\Database\Eloquent\Collection $array, string $column): Generator
    {
        $last = 0;
        $current = 1;

        while (true) {
            $current = $last++;
            if ($last == count($array)) {
                $last = 0;
            }
            yield $array[$current][$column];
        }
    }
}

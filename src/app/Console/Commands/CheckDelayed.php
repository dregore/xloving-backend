<?php

namespace App\Console\Commands;

use Generator;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Proxy;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDelayed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-delayed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check tasks with status delayed and set it as created if delayed_date meets conditions.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Delayed Job running at " . now());
        $message = 'Get all live units';
        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $shuffled = $units->shuffle();

        if ($units->count() == 0) {
            $message = 'There is no live units... exit';
            echo $message . PHP_EOL;
            info($message);

            return;
        }

        /**
         * NO RETENTION TASKS
         */
        $this->otherDelayedTasks($shuffled);

        /**
         * RETENTION TASKS
         */
        $this->retentionDelayedTasks();

        /*
        // Проверяем таски со статусом delayed и delayed_date = now() с точностью до минуты
        $tasks = Task::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->get(['id', 'status']);

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                Task::where('id', $task->id)->update(['status' => 'created']);

                info('Task: ' . $task->id . ' status changed to created');
            }
        } else {
            info('No delayed tasks to run');
        }
        */
    }

    private function otherDelayedTasks($units)
    {
        $message = 'Check delayed tasks without retention started at ' . now();
        echo $message . PHP_EOL;
        info($message);
        $tasks = Task::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->where('retention', 0)->orderBy('id', 'desc')->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no delayed tasks... exit';
            echo $message . PHP_EOL;
            info($message);

            return;
        }

        $message = 'Shuffle unit ids';
        echo $message . PHP_EOL;
        $b = $this->doStuff($units, 'unit_id');

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            echo $message . PHP_EOL;
            info($message);

            $proxy_id = 0;

            $usedAccounts = Task::where('type', $task->type)->whereNot('status', 'delayed')->where('app_bundle', $task->app_bundle)->get(['account_id']);
            $accountType = 'google';

            if ($task->country == 'any') {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                // DB::enableQueryLog(); //Enable query logging
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
                // dd(DB::getQueryLog()); //print the SQl query
            }

            if (!$accounts && $task->account_id == 0) {
                $message = 'There is no accounts... exit';
                echo $message . PHP_EOL;
                info($message);

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            if ($accounts) {
                if ($accounts->unit_id == 0) {
                    $task->unit_id = $b->current();
                    $b->next();

                    $message = 'New unit id: ' . $task->unit_id;
                    echo $message . PHP_EOL;
                    info($message);
                    // info($message);
                    // Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
                } else {
                    $message = 'Old unit id: ' . $accounts->unit_id;
                    echo $message . PHP_EOL;
                    info($message);
                    // info($message);
                    $task->unit_id =  $accounts->unit_id;
                }
            }

            // $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            // if (!$proxy) {
            //     $message = 'There is no proxy... exit';
            //     echo $message . PHP_EOL;
            //     info($message);

            //     return;
            // }
            // $proxy_id = $proxy->id;
            // Proxy::where('id', $proxy_id)->update(['active' => 0]);

            // $message = 'Proxy: ' . $proxy_id;
            // echo $message . PHP_EOL;
            // info($message);

            //TODO: tasks array
            // $insertArray[] = [
            //     'id' => $task->id,
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            //     'proxy_id' => $proxy_id,
            // ];
            Task::where('id', $task->id)->update([
                'unit_id' => $task->unit_id,
                'account_id' => (!$accounts) ? $task->account_id : $accounts->id,
                'status' => 'created',
                // 'proxy_id' => $proxy_id,
            ]);
        }

        // if (isset($insertArray)) {
        //     $chunks = array_chunk($insertArray, 5);
        //     foreach ($chunks as $item) {
        //         Task::upsert($item, uniqueBy: ['id'], update: ['unit_id', 'account_id', 'status', 'proxy_id']);
        //     }
        //     unset($chunks, $insertArray);
        // }

        $message = 'Check delayed tasks without retention finished at ' . now();
        echo $message . PHP_EOL;
        info($message);
    }

    private function retentionDelayedTasks()
    {
        $message = 'Check delayed tasks with retention started at ' . now();
        echo $message . PHP_EOL;
        info($message);
        $tasks = Task::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->whereNot('retention', 0)->orderBy('id', 'desc')->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no delayed tasks with retention... exit';
            echo $message . PHP_EOL;
            info($message);

            return;
        }

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            info($message);
            // echo $message . PHP_EOL;

            $proxy_id = 0;

            /*
            $usedAccounts = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('account_id')->get(['account_id']);
            $usedUnits = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('unit_id')->get(['unit_id']);
            $accountType = 'google';

            if ($task->country == 'any') {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
            }

            if (!$accounts) {
                $message = 'There is now accounts... exit';
                echo $message . PHP_EOL;

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            $units = TestUnit::whereNotIn('id', $usedUnits)->where('blocked', 0)->inRandomOrder()->first(['id']);

            if (!$units) {
                $message = 'There is now units... exit';
                echo $message . PHP_EOL;

                return;
            }

            if ($accounts->unit_id == 0) {
                $task->unit_id = $units->id;
                // Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);

                $message = 'New unit id: ' . $task->unit_id;
                echo $message . PHP_EOL;
            } else {
                $message = 'Old unit id: ' . $accounts->unit_id;
                echo $message . PHP_EOL;
                $task->unit_id =  $accounts->unit_id;
            }
            $message = 'task id: ' . $task->id . ', unit_id: ' . $task->unit_id . ', account_id: ' . $accounts->id;
            echo $message . PHP_EOL;
            */

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
            //TODO: install tasks array
            /*
            $installTasksArray[] = [
                'id' => $task->id,
                // 'unit_id' => $task->unit_id,
                // 'account_id' => $accounts->id,
                'status' => 'created',
                'delayed_date' => date('Y-m-d H:i:s'),
                'proxy_id' => $proxy_id,
            ];
            */
            $status = 'created';
            Task::where('id', $task->id)->update([
                'status' => $status,
                'delayed_date' => date('Y-m-d H:i:s'),
                // 'proxy_id' => $proxy_id,
            ]);

            // update launch, uninstall task
            //TODO: launch, uninstall tasks array 1
            $status = 'delayed';
            if ($time_diff > 0) {
                // $launchTasksArray1[] = [
                //     'retention_bundle_id' => $task->id,
                //     'unit_id' => $task->unit_id,
                //     'account_id' => $accounts->id,
                //     'status' => 'delayed',
                //     'delayed_date' => DB::raw('delayed_date + ' . $time_diff),
                // ];
                Task::where('retention_bundle_id', $task->id)->update([
                    'status' => $status,
                    'delayed_date' => DB::raw('delayed_date + INTERVAL ' . $time_diff . ' SECOND'),
                ]);
            }
        }

        /*
        if (isset($installTasksArray)) {
            Task::upsert($installTasksArray, uniqueBy: ['id'], update: ['status', 'delayed_date', 'proxy_id']);
            unset($installTasksArray);
        }
        if (isset($launchTasksArray1)) {
            Task::upsert($launchTasksArray1, uniqueBy: ['retention_bundle_id'], update: ['unit_id', 'account_id', 'status', 'delayed_date']);
            unset($launchTasksArray1);
        }
        if (isset($launchTasksArray2)) {
            Task::upsert($launchTasksArray2, uniqueBy: ['retention_bundle_id'], update: ['unit_id', 'account_id', 'status']);
            unset($launchTasksArray2);
        }
        */

        $message = 'Check delayed tasks with retention finished at ' . now();
        echo $message . PHP_EOL;
        info($message);
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

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        // 2. Получаем все юниты, чтобы назначить их на аккаунты, у которых updated_at <= 10 минут
        // $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        if ($units->count() == 0) {
            $units = Unit::all();
            $input['status'] = 'pending';
        }
        $b = $this->doStuff($units, 'unit_id');

        $tasks = Task::where('status', 'pending')
            ->where('account_id', 0)
            ->groupBy('order_id')
            ->limit(5)
            ->get();

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                // echo 'Taks id: ' . $task->id . PHP_EOL;
                // Для ордера, в котором этот таск, берем все аккаунты.
                $usedAccounts = Task::where('order_id', $task->order_id)->get(['account_id']);

                // Делаеми выборку аккаунта, которого нет в этом ордере.
                if ($task->country == 'any') {
                    $accounts = Account::whereNotIn('unit_id', $usedAccounts)->inRandomOrder()->firstOrFail();
                } else {
                    $accounts = Account::whereNotIn('unit_id', $usedAccounts)->where('country', $task->country)->inRandomOrder()->firstOrFail();
                }
                // echo 'Account id: ' . $accounts->id . PHP_EOL;

                // Назначаем этот аккаунт юниту и этой таске
                if ($accounts->count() > 0) {
                    // echo 'Accounts more than 0' . PHP_EOL;

                    if ($task->country == 'any') {
                        $task->country = $accounts->country;
                    }

                    if ($accounts->unit_id == 0) {
                        $task->unit_id = $b->current();
                        $b->next();

                        // echo 'New unit id: ' . $task->unit_id . PHP_EOL;

                        Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
                    } else {
                        // echo 'Old unit id: ' . $accounts->unit_id . PHP_EOL;

                        $task->unit_id =  $accounts->unit_id;
                    }

                    Task::where('id', $task->id)->update([
                        'unit_id' => $task->unit_id,
                        'account_id' => $accounts->id,
                        'status' => 'created',
                    ]);
                    // echo 'Saved' . PHP_EOL;
                }
            }
        }
    }

    private function doStuff($array, $column)
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

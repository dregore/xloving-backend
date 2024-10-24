<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Account;
// use Illuminate\Database\Eloquent\Builder;

class TestJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check tasks with status created but never taken to run.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Unfreeze Job running at " . now());

        // Проверяем таски со статусом created, running и updated_at <= 15 минут (п. 5)
        // $tasks = Task::where('status', 'created')->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))->get(['id', 'unit_id', 'country']);
        $tasks = Task::where('status', 'created')->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))->get(['id', 'unit_id', 'country']);
        // $tasks = Task::where(function (Builder $query) {
        //     $query->where('status', 'created')
        //           ->orWhere('status', 'running');
        // })
        // ->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))
        // ->get(['id', 'unit_id', 'country']);

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                // Берем юнит из таблицы Accounts у которых есть связка, т.е. unit_id != 0
                $accounts = Account::whereNot('unit_id', 0)->whereNot('unit_id', $task->unit_id)->where('country', $task->country)->inRandomOrder()->limit(1)->get();
                if ($accounts->count() > 0) {
                    $unitId = $accounts[0]['unit_id'];

                    // Назначаем этот юнит на таску
                    Task::where('id', $task->id)->update(['unit_id' => $unitId]);

                    info('Task: ' . $task->id . ', old unit_id: ' . $task->unit_id . ', new unit_id: ' . $unitId);
                } else {
                    info('Task: ' . $task->id . '. No free units for task.');
                }

                // echo $task->unit_id . PHP_EOL;
            }
        } else {
            info('No tasks with outdated units.');
        }
    }
}

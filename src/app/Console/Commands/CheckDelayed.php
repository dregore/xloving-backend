<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Task;

class CheckDelayed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delayed-job';

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

        // Проверяем таски со статусом delayed и delayed_date = now() с точностью до минуты
        $tasks = Task::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '=', date('Y:m:d H:i'))->get(['id', 'status']);

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                Task::where('id', $task->id)->update(['status' => 'created']);

                info('Task: ' . $task->id . ' status changed to created');
            }
        } else {
            info('No delayed tasks to run');
        }
    }
}

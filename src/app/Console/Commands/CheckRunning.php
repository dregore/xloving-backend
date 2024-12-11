<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Account;
use App\Http\Controllers\API\TaskController;
use Illuminate\Http\Request;
// use Illuminate\Database\Eloquent\Builder;

class CheckRunning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-running';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check tasks with status running that running more than 15 minutes';

    protected $taskController;

    public function __construct(TaskController $taskController)
    {
        parent::__construct();
        $this->taskController = $taskController;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Check running install Job running at " . now());

        // Проверяем таски install со статусом running <= 15 минут (п. 7)
        $tasks = Task::where('status', 'running')->where('type', 'install')->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))->get(['id', 'unit_id', 'country', 'type']);

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                switch ($task->type) {
                    case 'install':
                        // $usedAccounts = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->get(['account_id']);
                        $accountType = 'google';
                        break;
                    case 'youtube':
                        // $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                        $accountType = 'google';
                        break;
                    case 'import_chrome_cookies':
                    case 'export_chrome_cookies':
                        // $usedAccounts = [];
                        $accountType = 'google';
                        break;

                    case 'tiktok':
                        // $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                        $accountType = 'tiktok';
                        break;
                }

                $request = new Request;
                $request_objects = $request->replace(['task_id' => $task->id, 'status' => 'failed', 'error' => ['type' => 'HANGING_TASKS', 'comment' => 'hanging running task']]);
                $unit_id = $task->unit_id;
                $response = $this->taskController->update($request_objects, $unit_id);
                info('Task: ' . $response->getData()->data->task->id . ', type: ' . $response->getData()->data->task->type . ', status: ' . $response->getData()->data->task->status);

                /*
                // Берем юнит из таблицы Accounts у которых есть связка, т.е. unit_id != 0
                $accounts = Account::whereNot('unit_id', 0)->whereNot('unit_id', $task->unit_id)->where('country', $task->country)->whereNot('blocked', 1)->where('type', $accountType)->inRandomOrder()->limit(1)->get();
                if ($accounts->count() > 0) {
                    $unitId = $accounts[0]['unit_id'];

                    // Назначаем этот юнит на таску
                    Task::where('id', $task->id)->update(['unit_id' => $unitId, 'status' => 'created']);

                    info('Task: ' . $task->id . ', old unit_id: ' . $task->unit_id . ', new unit_id: ' . $unitId);
                } else {
                    info('Task: ' . $task->id . '. No free units for task.');
                }
                */

                // echo $task->unit_id . PHP_EOL;
            }
        } else {
            info('No install tasks with outdated units.');
        }

        info("Check running youtube Job running at " . now());
        // Проверяем таски yoututbe со статусом running <= 15 минут (п. 7)
        $tasks = Task::where('status', 'running')->where('type', 'youtube')->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL (minutes*60+tasks.seconds+15) MINUTE)'))->get(['id', 'unit_id', 'country', 'type']);

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                switch ($task->type) {
                    case 'install':
                        // $usedAccounts = Task::where('type', $task->type)->where('app_bundle', $task->app_bundle)->get(['account_id']);
                        $accountType = 'google';
                        break;
                    case 'youtube':
                        // $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                        $accountType = 'google';
                        break;
                    case 'import_chrome_cookies':
                    case 'export_chrome_cookies':
                        // $usedAccounts = [];
                        $accountType = 'google';
                        break;

                    case 'tiktok':
                        // $usedAccounts = Task::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                        $accountType = 'tiktok';
                        break;
                }

                $request = new Request;
                $request_objects = $request->replace(['task_id' => $task->id, 'status' => 'failed', 'error' => ['type' => 'HANGING_TASKS', 'comment' => 'hanging running task']]);
                $unit_id = $task->unit_id;
                $response = $this->taskController->update($request_objects, $unit_id);
                info('Task: ' . $response->getData()->data->task->id . ', type: ' . $response->getData()->data->task->type . ', status: ' . $response->getData()->data->task->status);

                /*
                // Берем юнит из таблицы Accounts у которых есть связка, т.е. unit_id != 0
                $accounts = Account::whereNot('unit_id', 0)->whereNot('unit_id', $task->unit_id)->where('country', $task->country)->whereNot('blocked', 1)->where('type', $accountType)->inRandomOrder()->limit(1)->get();
                if ($accounts->count() > 0) {
                    $unitId = $accounts[0]['unit_id'];

                    // Назначаем этот юнит на таску
                    Task::where('id', $task->id)->update(['unit_id' => $unitId, 'status' => 'created']);

                    info('Task: ' . $task->id . ', old unit_id: ' . $task->unit_id . ', new unit_id: ' . $unitId);
                } else {
                    info('Task: ' . $task->id . '. No free units for task.');
                }
                */

                // echo $task->unit_id . PHP_EOL;
            }
        } else {
            info('No youtube tasks with outdated units.');
        }
    }
}

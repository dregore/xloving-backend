<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\Account;
use App\Http\Controllers\API\TestController;
use App\Http\Controllers\API\TaskController;
use Illuminate\Http\Request;
// use Illuminate\Database\Eloquent\Builder;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check tasks with status running that running more than 15 minutes';
    protected $testController;
    protected $taskController;

    public function __construct(TestController $testController, TaskController $taskController)
    {
        parent::__construct();
        $this->testController = $testController;
        $this->taskController = $taskController;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /*
        $request = new Request;
        $request_objects = $request->replace(['task_id' => 123, 'status' => 'failed', 'error' => 'hanging running task']);

        $unit_id = 51;
        $this->testController->update($request_objects, $unit_id);
        */

        $tasks = Task::where('status', 'running')->where('type', 'install')->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))->get(['id', 'unit_id', 'country', 'type']);
        // if ($tasks->count() > 0) {
        //     foreach ($tasks as $task) {
        $request = new Request;
        // $request_objects = $request->replace(['task_id' => $task->id, 'status' => 'failed', 'error' => 'hanging running task']);
        // $unit_id = $task->unit_id;
        $request_objects = $request->replace(['task_id' => 18355, 'status' => 'failed', 'error' => ['type' => 'HANGING_TASKS', 'comment' => 'hanging running task']]);
        $unit_id = 51;

        $response = $this->taskController->update($request_objects, $unit_id);
        echo $response . PHP_EOL;
        //     }
        // }
    }
}

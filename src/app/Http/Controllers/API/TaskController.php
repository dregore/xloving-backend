<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Validator;
use Illuminate\Http\JsonResponse;

use App\Models\Task;
use App\Http\Resources\TaskResource;
use DB;

use App\Models\Account;
use App\Models\Unit;

class TaskController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): JsonResponse
    {
        $Task = Task::all();

        return $this->sendResponse(TaskResource::collection($Task), 'Task retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            // 'unit_id' => 'required',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        switch ($input['type']) {
            case 'install':
            case 'youtube':
                $accountType = 'google';
                break;

            case 'tiktok':
                $accountType = 'tiktok';
                break;
        }

        // TODO: HARDCODED
        $input['attempts_changes'] = $input['attempts'];
        $input['bundles'] = 3;

        // 1. Берем аккаунты по стране и их юниты
        if ($input['country'] == 'any') {
            $accounts = Account::where('type', $accountType)->get();
        } else {
            $accounts = Account::where('type', $accountType)->where('country', $input['country'])->get();
        }

        // 2. Получаем все юниты, чтобы назначить их на аккаунты, у которых updated_at <= 10 минут
        // $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        if ($units->count() == 0) {
            $units = Unit::all();
            $input['status'] = 'pending';
        }
        $b = $this->doStuff($units, 'unit_id');

        if (isset($input['amount'])) {
            for ($i = 0; $i < $input['amount']; $i++) {
                $input['account_id'] = $accounts[$i]['id'] ?? 0;

                if ($input['country'] == 'any') {
                    $input['country'] = $accounts[$i]['country'];
                }

                // 3. Назначаем юниты на аккаунты
                if (isset($accounts[$i]['unit_id'])) {
                    if ($accounts[$i]['unit_id'] == 0) {
                        $input['unit_id'] = $b->current();
                        $b->next();

                        Account::where('id', $accounts[$i]['id'])->update(['unit_id' => $input['unit_id']]);
                    } else {
                        $input['unit_id'] =  $accounts[$i]['unit_id'];
                    }
                }

                if (!isset($accounts[$i]['id'])) {
                    $input['account_id'] = 0;
                    $input['status'] = 'pending';
                }

                $task = Task::create($input);
            }
        }

        return $this->sendResponse(new TaskResource($task), 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($unit_id): JsonResponse
    {
        // Обновляем updated_at у unit
        $unit = Unit::where('unit_id', $unit_id)->first();
        if ($unit) {
            $unit->last_seen = date('Y-m-d H:i:s');
            $unit->touch();
        }

        // DB::enableQueryLog(); //Enable query logging
        $task = Task::where('unit_id', $unit_id)->firstWhere('status', 'created');
        // dd(DB::getQueryLog()); //print the SQl query

        if (is_null($task)) {
            // return $this->sendError('Task not found.');
            return $this->sendResponse(['task' => ['idle' => 600]], 'Task not found.');
        }

        // $t = new TaskResource($task);
        // $a = new AccountsResource($account);
        // $s = compact($t, $a);
        // if (!empty($account)) {
        //     $a = [new TaskResource($task), new AccountsResource($account)];
        // } else {
        //     $a = new TaskResource($task);
        // }
        $a = new TaskResource($task);

        return $this->sendResponse($a, 'Task retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /*
    public function update(Request $request, Task $task): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            // 'task_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // $task->id = $input['task_id'];
        $task->status = $input['status'];
        $task->save();

        return $this->sendResponse(new TaskResource($task), 'Task updated successfully.');
    }
    */
    public function update(Request $request, $unit_id): JsonResponse
    {
        $input = $request->only(['task_id', 'status']);

        // print_r($input);

        $validator = Validator::make($input, [
            'task_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $task = Task::where('unit_id', $unit_id)->firstWhere('id', $input['task_id']);
        $task->id = $input['task_id'];
        $task->status = $input['status'];

        switch ($task->type) {
            case 'install':
            case 'youtube':
                $accountType = 'google';
                break;

            case 'tiktok':
                $accountType = 'tiktok';
                break;
        }

        if ($input['status'] == 'failed' && $task->attempts_changes > 0 && $task->bundles > 0) {
            $task->status = 'created';
            $task->attempts_changes = $task->attempts_changes - 1;
            // $task->save();
        }

        if ($input['status'] == 'failed' && $task->attempts_changes == 0 && $task->bundles > 0) {
            $accounts = Account::where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->inRandomOrder()->firstOrFail();
            if ($accounts->unit_id == 0) {
                // $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->firstOrFail();
                $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->firstOrFail();
                $accounts->unit_id = $units->id;

                Account::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
            }
            $task->unit_id = $accounts->unit_id;
            $task->account_id = $accounts->id;
            $task->attempts_changes = $task->attempts;
            $task->bundles = $task->bundles - 1;
            $task->status = 'created';
            // $task->save();
        }

        $task->save();


        /*
        // Если status = failed, ставим другой unit_id на эту task по нужной стране и делаем статус created
        if ($input['status'] == 'failed' && $task->attempts > 0) {

            // DB::enableQueryLog();
            $accounts = Account::where('type', $accountType)->whereNot('unit_id', $id)->where('country', $task->country)->inRandomOrder()->firstOrFail();
            // dd(DB::getQueryLog());

            if ($accounts->unit_id == 0) {
                $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->firstOrFail();
                $accounts->unit_id = $units->id;

                Account::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
            }
            $task->unit_id = $accounts->unit_id;
            $task->account_id = $accounts->id;
            $task->status = 'created';
        }

        $task->attempts = $task->attempts - 1;
        $task->save();
        */

        // Обновляем updated_at у unit
        $unit = Unit::where('unit_id', $unit_id)->first();
        if ($unit) {
            $unit->last_seen = date('Y-m-d H:i:s');
            $unit->touch();
        }

        return $this->sendResponse(new TaskResource($task), 'Task updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): JsonResponse
    {
        Task::where('id', $id)->delete();

        return $this->sendResponse([], 'Task deleted successfully.');
    }

    public function destoryByOrderId($order_id): JsonResponse
    {
        // $order_id = $request->order_id;
        Task::where('order_id', $order_id)->delete();

        return $this->sendResponse([], 'Tasks by order ID deleted successfully.');
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

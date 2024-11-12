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
use App\Models\Error;

use App\Http\Controllers\API\CookieUpload;

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
                $usedAccounts = Task::where('type', $input['type'])->where('app_bundle', $input['app_bundle'])->get(['account_id']);
                $accountType = 'google';
                break;
            case 'youtube':
                $usedAccounts = Task::where('type', $input['type'])->where('url', $input['url'])->get(['account_id']);
                $accountType = 'google';
                break;
            case 'import_chrome_cookies':
            case 'export_chrome_cookies':
                $usedAccounts = [];
                $accountType = 'google';
                break;

            case 'tiktok':
                $usedAccounts = Task::where('type', $input['type'])->where('url', $input['url'])->get(['account_id']);
                $accountType = 'tiktok';
                break;
        }

        // if ($input['type'] == 'export_chrome_cookies') {
        //     $cookie = new CookieUpload;
        //     $input['filename'] = $cookie->justUpload($request);
        // }

        // TODO: HARDCODED
        $input['attempts_changes'] = $input['attempts'];
        $input['bundles'] = 3;

        // 1. Берем аккаунты по стране и их юниты
        // if ($input['country'] == 'any') {
        //     $accounts = Account::where('type', $accountType)->where('blocked', 0)->get();
        // } else {
        //     $accounts = Account::where('type', $accountType)->where('country', $input['country'])->where('blocked', 0)->get();
        // }
        if ($input['country'] == 'any') {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->get();
        } else {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $input['country'])->get();
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

            if ($input['fill_type'] == 'spread') {
                $spreadDays = $input['spread_days'] ?? 1;
                $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
            }

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

                if ($input['fill_type'] == 'spread' && $i > 0) {
                    $input['status'] = 'delayed';
                    $input['delayed_date'] = $datesInterval[$i];
                }

                $task = Task::create($input);

                if ($input['retention'] != 0) {
                    $input['delete'] = 0;
                    $retentionArray = explode(',', $input['retention']);
                    foreach ($retentionArray as $item) {
                        $input['type'] = 'launch';
                        $input['status'] = 'delayed';
                        $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $item . ' day')->format('Y-m-d H:i:s');

                        $task = Task::create($input);
                    }

                    $lastRetentionDate = end($retentionArray) + 1;
                    $input['type'] = 'uninstall';
                    $input['status'] = 'delayed';
                    $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $lastRetentionDate . ' day')->format('Y-m-d H:i:s');
                    $task = Task::create($input);
                }
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
     * @param  int  $unit_id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $unit_id): JsonResponse
    {
        $input = $request->only(['task_id', 'status', 'error']);

        $validator = Validator::make($input, [
            'task_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $task = Task::where('unit_id', $unit_id)->firstWhere('id', $input['task_id']);

        if (!$task) {
            return $this->sendError('No task found.');
        }

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

        $accountErrors = [
            'captcha_unsolvable',
            'wrong_password',
            'account'
        ];

        if ($input['status'] == 'failed') {
            $error = new Error();
            $error->task_id = $input['task_id'];
            $error->unit_id = $unit_id;
            $error->account_id = $task->account_id;
            $error->type = $input['error']['type'];
            $error->comment = $input['error']['comment'];
            $error->save();

            $errors = Error::where('account_id', $task->account_id)->whereIn('type', $accountErrors)->get();
            if ($errors->count() >= 2) {
                Account::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => $error->comment]);
            }
        }

        if ($input['status'] == 'failed' && $task->attempts_changes > 0 && $task->bundles > 0) {
            $task->status = 'created';
            $task->attempts_changes = $task->attempts_changes - 1;
        }

        if ($input['status'] == 'failed' && $task->attempts_changes == 0 && $task->bundles > 0) {
            $accounts = Account::where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->where('blocked', 0)->inRandomOrder()->firstOrFail();
            if ($accounts->unit_id == 0) {
                $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->firstOrFail();
                $accounts->unit_id = $units->unit_id;

                Account::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
            }
            $task->unit_id = $accounts->unit_id;
            $task->account_id = $accounts->id;
            $task->attempts_changes = $task->attempts;
            $task->bundles = $task->bundles - 1;
            $task->status = 'created';
        }

        // if ($input['status'] == 'failed' && $task->status == 'failed' && $task->bundles == 0) {
        //     $task->error_type = $input['error']['type'];
        //     $task->comment = $input['error']['comment'];

        //     if ($input['error']['type'] == 'account') {
        //         Account::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => 'account']);
        //     }
        // }

        if ($input['status'] == 'needs_confirmation') {
            Account::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => 'needs_confirmation']);
        }

        $task->save();

        // Обновляем last_seen у unit
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

    public function serverside(Request $request): JsonResponse
    {
        $page = ($request->start / $request->length) + 1;
        $orderBy = $request->order[0]['name'] ?? 'id';
        $orderDir = $request->order[0]['dir'] ?? 'asc';
        $tasks = Task::orderBy($orderBy, $orderDir)->paginate($request->length, ['*'], 'page', $page);

        if ($tasks->count() == 0) {
            $base_array = array('draw' => $request->draw, 'recordsTotal' => 0, 'recordsFiltered' => 0);
            $data_array = array('data' => array());
            $final = array_merge($base_array, $data_array);
            echo json_encode($final);
            exit;
        }

        $base_array = array('draw' => $request->draw, 'recordsTotal' => $tasks->total(), 'recordsFiltered' => $tasks->total(), 'currentPage' => $page);
        foreach ($tasks as $item) {
            $data_array['data'][] = array_values([
                "id" => $item['id'],
                "unit_id" => $item['unit_id'],
                "status" => $item['status'],
                "type" => $item['type'],
                "attempts" => $item['attempts'],
                "delay_min" => $item['delay_min'],
                "delay_max" => $item['delay_max'],
                "app_name" => $item['app_name'],
                "app_bundle" => $item['app_bundle'],
                "delete" => $item['delete'],
                "minutes" => $item['minutes'],
                "seconds" => $item['seconds'],
                "like" => $item['like'],
            ]);
        }
        $final = array_merge($base_array, $data_array);

        return response()->json($final);
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

    private function intervalBreaks($spreadBlocks, $days = 1)
    {
        if ($spreadBlocks == 1) {
            return date('Y-m-d H:i:s');
        }

        $totalMinutes = 1440 * $days; // 24h
        $interval = round($totalMinutes / $spreadBlocks);

        $startDate = new \DateTime(date('Y-m-d H:i:s'));
        // $endDate = $startDate->modify('+1 day')->format('Y-m-d H:i:s');

        for ($i = 0; $i < $spreadBlocks; $i++) {
            $dates[] = $startDate->modify('+' . $interval . ' minute')->format('Y-m-d H:i:s');
        }

        return $dates;
    }
}

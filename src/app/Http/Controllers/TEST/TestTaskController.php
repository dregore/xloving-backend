<?php

namespace App\Http\Controllers\TEST;

use DateTime;
use Generator;
use App\Models\Error;
use App\Models\TestProxy;
use App\Models\TestTask;

use App\Models\TestUnit;
use App\Models\TestAccount;
use Illuminate\Http\Request;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\CookieUpload;
use App\Http\Controllers\API\BaseController as BaseController;

class TestTaskController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $Task = TestTask::all();

        return $this->sendResponse(TaskResource::collection($Task), 'Task retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
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
                $usedAccounts = TestTask::where('type', $input['type'])->where('app_bundle', $input['app_bundle'])->groupBy('account_id')->get(['account_id']);
                $accountType = 'google';
                break;
            case 'youtube':
                $usedAccounts = TestTask::where('type', $input['type'])->where('url', $input['url'])->groupBy('account_id')->get(['account_id']);
                $accountType = 'google';
                break;
            case 'import_chrome_cookies':
            case 'export_chrome_cookies':
                $usedAccounts = [];
                $accountType = 'google';
                break;

            case 'tiktok':
                $usedAccounts = TestTask::where('type', $input['type'])->where('url', $input['url'])->groupBy('account_id')->get(['account_id']);
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
        if ($input['country'] == 'any') {
            $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->get();
        } else {
            $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $input['country'])->get();
        }

        // 2. Получаем все юниты, чтобы назначить их на аккаунты, у которых updated_at <= 10 минут
        $b = [];
        $units = TestUnit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $units_count = $units->count();
        $b = $this->doStuff($units, 'unit_id');
        echo 'units_count: ' . $units_count . PHP_EOL;

        if ($input['retention'] != 0) {
            echo 'retentionTasks:' . PHP_EOL;
            $task = $this->retentionTasks($input, $units_count, $b, $accounts);
        } else {
            echo 'otherTasks:' . PHP_EOL;
            $task = $this->otherTasks($input, $units_count, $b, $accounts);
        }

        return $this->sendResponse(new TaskResource($task), 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($unit_id): JsonResponse
    {
        // Обновляем updated_at у unit
        $unit = TestUnit::where('unit_id', $unit_id)->first();
        if ($unit) {
            $unit->last_seen = date('Y-m-d H:i:s');
            $unit->touch();
        }

        $task = TestTask::where('unit_id', $unit_id)->firstWhere('status', 'created');

        if (is_null($task)) {
            return $this->sendResponse(['task' => ['idle' => 600]], 'Task not found.');
        }

        $a = new TaskResource($task);

        return $this->sendResponse($a, 'Task retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $unit_id
     * @return \Illuminate\Http\JsonResponse
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

        $task = TestTask::where('unit_id', $unit_id)->firstWhere('id', $input['task_id']);

        if (!$task) {
            return $this->sendError('No task found.');
        }

        $task->id = $input['task_id'];
        $task->status = $input['status'];

        $accountTypes = [
            'install' => 'google',
            'launch' => 'google',
            'uninstall' => 'google',
            'youtube' => 'google',
            'tiktok' => 'tiktok',
        ];

        $usedAccounts = TestTask::where('type', $task->type);
        $usedUnits = TestTask::where('type', $task->type);
        switch ($task->type) {
            case 'install':
            case 'launch':
            case 'uninstall':
                $usedAccounts->where('app_bundle', $task->app_bundle)->groupBy('account_id');
                $usedUnits->where('app_bundle', $task->app_bundle)->groupBy('unit_id');
                $accountType = $accountTypes[$task->type];
                break;

            case 'youtube':
            case 'tiktok':
                $usedAccounts->where('url', $task->url)->groupBy('account_id');
                $usedUnits->where('url', $task->url)->groupBy('unit_id');
                $accountType = $accountTypes[$task->type];
                break;
        }
        $usedAccountsResponse = $usedAccounts->get(['account_id']);
        $usedUnitsResponse = $usedUnits->get(['unit_id']);

        // TODO: If retention != 0 and type = install, change unit and account upto 3 times and for delayed too.
        // TODO: If retention != 0, after final fail - fail all delayed.
        // TODO: If retention != 0 and fail launch - do not change unit!

        $accountErrors = [
            'captcha_unsolvable',
            'wrong_password',
            'account',
        ];

        if ($input['status'] == 'failed') {
            $error = new Error();
            $error->task_id = $input['task_id'];
            $error->unit_id = $unit_id;
            $error->account_id = $task->account_id;
            $error->type = $input['error']['type'];
            $error->comment = $input['error']['comment'];
            $error->status = $input['status'];
            $error->save();

            $errors = Error::where('account_id', $task->account_id)->whereIn('type', $accountErrors)->get();
            if ($errors->count() >= 2) {
                TestAccount::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => $error->comment]);
            }
        }

        /**
         * Give a chance for linked unit_id and account_id for a $task->attempts_changes times.
         */
        if ($input['status'] == 'failed' && $task->attempts_changes > 0 && $task->bundles > 0) {
            $task->status = 'created';
            $task->attempts_changes = $task->attempts_changes - 1;
        }

        /**
         * If $task->attempts_changes ends - changes unit_id and account_id.
         * 
         * Exception: For retention tasks we can change only account_id if task types are launch or uninstall.
         *            If task type is install, we should change unit_id and account id for task types launch and uninstall too.
         *            Using retention_bundle_id which equal of id of install task.
         */
        if ($input['status'] == 'failed' && $task->attempts_changes == 0 && $task->bundles > 0) {
            if ($task->retention != 0) {
                $accounts = TestAccount::whereNotIn('id', $usedAccountsResponse)->where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->where('blocked', 0)->inRandomOrder()->first();
                if (!$accounts) {
                    $task->status = 'pending';
                } else {
                    if ($accounts->unit_id == 0 && $task->type == 'install') {
                        $units = TestUnit::whereNotIn('unit_id', $usedUnitsResponse)->where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->first();

                        if ($units) {
                            $accounts->unit_id = $units->unit_id;
                            TestAccount::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
                            $task->status = 'created';
                        } else {
                            $task->status = 'pending';
                        }
                    }
                    $task->unit_id = ($task->type == 'install') ? $accounts->unit_id : $task->unit_id;
                    $task->account_id = $accounts->id;
                }
                $task->attempts_changes = $task->attempts;
                $task->bundles = $task->bundles - 1;
            } else {
                $accounts = TestAccount::whereNotIn('id', $usedAccountsResponse)->where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->where('blocked', 0)->inRandomOrder()->first();
                if (!$accounts) {
                    $task->status = 'pending';
                } else {
                    if ($accounts->unit_id == 0) {
                        $units = TestUnit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->first();

                        if ($units) {
                            $accounts->unit_id = $units->unit_id;
                            TestAccount::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
                            $task->status = 'created';
                        } else {
                            $task->status = 'pending';
                        }
                    }
                    $task->unit_id = ($accounts->unit_id) ? $accounts->unit_id : $task->unit_id;
                    $task->account_id = $accounts->id;
                }
                $task->attempts_changes = $task->attempts;
                $task->bundles = $task->bundles - 1;
            }
        }

        if ($input['status'] == 'failed' && $task->bundles == 0 && $task->retention != 0) {
            $task->where('retention_bundle_id', $task->retention_bundle_id)->update([
                'status' => $input['status'],
            ]);
        }

        if ($input['status'] == 'needs_confirmation') {
            TestAccount::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => 'needs_confirmation']);
        }

        if ($task->retention != 0 && $task->type == 'install') {
            $now = date('Y-m-d H:i:s');
            $delayed = $task->updated_at;
            $time_diff = abs(strtotime($now) - strtotime($delayed));

            $task->where('retention_bundle_id', $task->id)->update([
                'unit_id' => $task->unit_id,
                'status' => ($task->status == 'created') ? 'delayed' : $task->status,
                'account_id' => $task->account_id,
                'delayed_date' => DB::raw('delayed_date + INTERVAL ' . $time_diff . ' SECOND'),
            ]);
        }
        $task->save();

        // Обновляем last_seen у unit
        $unit = TestUnit::where('unit_id', $unit_id)->first();
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        TestTask::where('id', $id)->delete();

        return $this->sendResponse([], 'Task deleted successfully.');
    }

    public function destoryByOrderId($order_id): JsonResponse
    {
        // $order_id = $request->order_id;
        TestTask::where('order_id', $order_id)->delete();

        return $this->sendResponse([], 'Tasks by order ID deleted successfully.');
    }

    public function serverside(Request $request): JsonResponse
    {
        $page = ($request->start / $request->length) + 1;
        $orderBy = $request->order[0]['name'] ?? 'id';
        $orderDir = $request->order[0]['dir'] ?? 'asc';
        $tasks = TestTask::orderBy($orderBy, $orderDir)->paginate($request->length, ['*'], 'page', $page);

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

    /**
     * Breaks given dates for interval and returns array or string of datetime strings.
     *
     * @param int $spreadBlocks
     * @param int $days
     * 
     * @return array | string
     * 
     */
    private function intervalBreaks(int $spreadBlocks, int $days = 1): array | string
    {
        if ($spreadBlocks == 1) {
            return date('Y-m-d H:i:s');
        }

        $totalMinutes = 1440 * $days; // 24h
        $interval = round($totalMinutes / $spreadBlocks);

        $startDate = new \DateTime(date('Y-m-d H:i:s'));

        for ($i = 0; $i < $spreadBlocks; $i++) {
            $dates[] = $startDate->modify('+' . $interval . ' minute')->format('Y-m-d H:i:s');
        }

        return $dates;
    }

    private function otherTasks($input, $units_count, $b, $accounts)
    {
        if ($input['fill_type'] == 'spread') {
            $spreadDays = $input['spread_days'] ?? 1;
            $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
        }

        for ($i = 0; $i < $input['amount']; $i++) {
            $input['proxy_id'] = 0;

            if ($input['country'] == 'any') {
                $input['country'] = $accounts[$i]['country'];
            }

            $input['status'] = 'created';
            if ($i >= $units_count) {
                $input['status'] = 'pending';
                $input['account_id'] = 0;
                $input['unit_id'] = 0;
            } else {
                $input['account_id'] = $accounts[$i]['id'] ?? 0;
            }

            // 3. Назначаем юниты на аккаунты
            if (isset($accounts[$i]['unit_id']) && $units_count > 0 && $i < $units_count) {
                if ($accounts[$i]['unit_id'] == 0) {
                    $input['unit_id'] = $b->current();
                    $b->next();

                    TestAccount::where('id', $accounts[$i]['id'])->update(['unit_id' => $input['unit_id']]);
                } else {
                    $input['unit_id'] =  $accounts[$i]['unit_id'];
                }
            }

            if ($input['fill_type'] == 'spread' && $i > 0) {
                $input['status'] = 'delayed';
                $input['account_id'] = 0;
                $input['unit_id'] = 0;

                // if ($i >= $units_count) {
                //     $input['status'] = 'delayed';
                //     $input['account_id'] = 0;
                //     $input['unit_id'] = 0;
                // }
                $input['delayed_date'] = $datesInterval[$i];
            }

            if (!isset($accounts[$i]['id'])) {
                $input['account_id'] = 0;
                $input['unit_id'] = 0;
                $input['status'] = 'pending';
            }

            if ($input['status'] == 'created') {
                $proxy = TestProxy::where('active', 1)->where('country', $input['country'])->inRandomOrder()->first(['id']);

                if ($proxy) {
                    $input['proxy_id'] = $proxy->id;
                    TestProxy::where('id', $input['proxy_id'])->update(['active' => 0]);
                } else {
                    $input['status'] = 'pending';
                    $input['proxy_id'] = 0;
                }
            }

            $task = TestTask::create($input);

            echo $i . ' -> INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . ', proxy_id: ' . $input['proxy_id'] . PHP_EOL;
        }

        return $task;
    }

    private function retentionTasks($input, $units_count, $b, $accounts)
    {
        if ($input['fill_type'] == 'spread') {
            $spreadDays = $input['spread_days'] ?? 1;
            $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
        }

        for ($i = 0; $i < $input['amount']; $i++) {
            $input['proxy_id'] = 0;
            $input['status'] = ($i >= $units_count) ? 'pending' : 'created';
            $input['account_id'] = $accounts[$i]['id'] ?? 0;
            $input['delete'] = 0;

            if (isset($accounts[$i]['unit_id']) && $units_count > 0 && $i < $units_count) {
                if ($accounts[$i]['unit_id'] == 0) {
                    $input['unit_id'] = $b->current();
                    $b->next();
                } else {
                    $input['unit_id'] =  $accounts[$i]['unit_id'];
                }
            }

            if (!isset($accounts[$i]['id']) || $i >= $units_count) {
                $input['account_id'] = 0;
                $input['status'] = 'pending';
                $input['unit_id'] = 0;
            }

            echo $i . ' -> units_count: ' . $units_count . ', INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;

            $input['type'] = 'install';
            $input['delayed_date'] = date('Y-m-d H:i:s');
            $input['retention_bundle_id'] = 0;

            if ($input['fill_type'] == 'spread' && $i > 0) {
                $input['status'] = 'delayed';
                if ($i >= $units_count) {
                    $input['status'] = 'pending';
                    $input['account_id'] = 0;
                    $input['unit_id'] = 0;
                }
                $input['delayed_date'] = $datesInterval[$i];
            }

            if ($input['status'] == 'created') {
                $proxy = TestProxy::where('active', 1)->where('country', $input['country'])->inRandomOrder()->first(['id']);

                if ($proxy) {
                    $input['proxy_id'] = $proxy->id;
                    TestProxy::where('id', $input['proxy_id'])->update(['active' => 0]);
                } else {
                    $input['status'] = 'pending';
                    $input['proxy_id'] = 0;
                }
            }

            $task = TestTask::create($input);
            $lastInsertedId = $task->id;
            $input['proxy_id'] = 0;

            $retentionArray = explode(',', $input['retention']);
            foreach ($retentionArray as $item) {
                $input['type'] = 'launch';
                $input['status'] = ($i >= $units_count) ? 'pending' : 'delayed';
                $input['delayed_date'] = (new \DateTime($input['delayed_date']))->modify('+' . $item . ' day')->format('Y-m-d H:i:s');
                $input['retention_bundle_id'] = $lastInsertedId;
                echo $i . ' -> LAUNCH: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . ', last: ' . $lastInsertedId . PHP_EOL;
                $task = TestTask::create($input);
            }

            $lastRetentionDate = end($retentionArray) + 1;
            $input['type'] = 'uninstall';
            $input['status'] = ($i >= $units_count) ? 'pending' : 'delayed';
            $input['delayed_date'] = (new \DateTime($input['delayed_date']))->modify('+' . $lastRetentionDate . ' day')->format('Y-m-d H:i:s');
            $input['retention_bundle_id'] = $lastInsertedId;
            echo $i . ' -> UNINSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . ', last: ' . $lastInsertedId . PHP_EOL . PHP_EOL;
            $task = TestTask::create($input);
        }

        return $task;
    }

    public function pendingJob()
    {
        $message = 'Get all live units';
        $units = TestUnit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $shuffled = $units->shuffle();

        if ($units->count() == 0) {
            $message = 'There is no live units... exit';
            echo $message . PHP_EOL;

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

    public function delayedJob()
    {
        $message = 'Get all live units';
        $units = TestUnit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        $shuffled = $units->shuffle();

        if ($units->count() == 0) {
            $message = 'There is no live units... exit';
            echo $message . PHP_EOL;

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
    }

    private function otherPendingTasks($units)
    {
        $message = 'Check pending tasks without retention';
        echo $message . PHP_EOL;
        $tasks = TestTask::where('status', 'pending')->where('retention', 0)->whereIn('type', ['install', 'youtube', 'tiktok'])->orderBy('id', 'asc')->limit(10)->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no pending tasks... exit';
            echo $message . PHP_EOL;

            return;
        }

        $message = 'Shuffle unit ids';
        echo $message . PHP_EOL;
        $b = $this->doStuff($units, 'unit_id');

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            echo $message . PHP_EOL;

            $proxy_id = 0;

            switch ($task->type) {
                case 'install':
                    $usedAccounts = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'youtube':
                    $usedAccounts = TestTask::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'import_chrome_cookies':
                case 'export_chrome_cookies':
                    $usedAccounts = [];
                    $accountType = 'google';
                    break;

                case 'tiktok':
                    $usedAccounts = TestTask::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'tiktok';
                    break;
            }

            if ($task->country == 'any') {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
            }

            if (!$accounts) {
                $message = 'There is no accounts... exit';
                echo $message . PHP_EOL;

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            if ($accounts->unit_id == 0) {
                $task->unit_id = $b->current();
                $b->next();

                $message = 'New unit id: ' . $task->unit_id;
                echo $message . PHP_EOL;
                // info($message);
                // TestAccount::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
            } else {
                $message = 'Old unit id: ' . $accounts->unit_id;
                echo $message . PHP_EOL;
                // info($message);
                $task->unit_id =  $accounts->unit_id;
            }

            $status = 'created';
            if ($task->fill_type == 'spread') {
                $now = strtotime(date('Y-m-d H:i:s'));
                $delayed = strtotime($task->delayed_date);
                $time_diff = $delayed - $now;

                $status = ($time_diff >= 0) ? 'delayed' : $status;
            }

            if ($status == 'created') {
                $proxy = TestProxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
                if (!$proxy) {
                    $message = 'There is no proxy... exit';
                    echo $message . PHP_EOL;

                    return;
                }

                $proxy_id = $proxy->id;
                TestProxy::where('id', $proxy_id)->update(['active' => 0]);

                $message = 'Proxy: ' . $proxy_id;
                echo $message . PHP_EOL;
            }

            //TODO: tasks array
            $insertArray[] = [
                'id' => $task->id,
                'unit_id' => $task->unit_id,
                'account_id' => $accounts->id,
                'status' => $status,
                'proxy_id' => $proxy_id,
            ];
            // TestTask::where('id', $task->id)->update([
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            // ]);
        }

        if (isset($insertArray)) {
            $chunks = array_chunk($insertArray, 5);
            foreach ($chunks as $item) {
                TestTask::upsert($item, uniqueBy: ['id'], update: ['unit_id', 'account_id', 'status', 'proxy_id']);
            }
            unset($chunks, $insertArray);
        }
    }

    private function retentionPendingTasks()
    {
        $message = 'Check pending tasks with retention';
        echo $message . PHP_EOL;
        $tasks = TestTask::where('status', 'pending')->whereNot('retention', 0)->where('type', 'install')->orderBy('id', 'desc')->limit(10)->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no pending tasks with retention... exit';
            echo $message . PHP_EOL;

            return;
        }

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            // echo $message . PHP_EOL;

            $proxy_id = 0;

            switch ($task->type) {
                case 'install':
                    // case 'launch':
                    // case 'uninstall':
                    $usedAccounts = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('account_id')->get(['account_id']);
                    $usedUnits = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('unit_id')->get(['unit_id']);
                    $accountType = 'google';
                    break;
                case 'youtube':
                    $usedAccounts = TestTask::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'google';
                    break;
                case 'import_chrome_cookies':
                case 'export_chrome_cookies':
                    $usedAccounts = [];
                    $accountType = 'google';
                    break;

                case 'tiktok':
                    $usedAccounts = TestTask::where('type', $task->type)->where('url', $task->url)->get(['account_id']);
                    $accountType = 'tiktok';
                    break;
            }

            if ($task->country == 'any') {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
            }

            if (!$accounts) {
                $message = 'There is now accounts... exit';
                echo $message . PHP_EOL;

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            $units = TestUnit::whereNotIn('id', $usedUnits)->where('blocked', 0)->inRandomOrder()->first();

            if (!$units) {
                $message = 'There is now units... exit';
                echo $message . PHP_EOL;

                return;
            }

            if ($accounts->unit_id == 0) {
                $task->unit_id = $units->id;
                // TestAccount::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);

                $message = 'New unit id: ' . $task->unit_id;
                echo $message . PHP_EOL;
            } else {
                $message = 'Old unit id: ' . $accounts->unit_id;
                echo $message . PHP_EOL;
                $task->unit_id =  $accounts->unit_id;
            }
            $message = 'task id: ' . $task->id . ', unit_id: ' . $task->unit_id . ', account_id: ' . $accounts->id;
            echo $message . PHP_EOL;

            $now = date('Y-m-d H:i:s');
            $delayed = $task->delayed_date;
            $time_diff = abs(strtotime($now) - strtotime($delayed));

            $proxy = TestProxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            if (!$proxy) {
                $message = 'There is now proxy... exit';
                echo $message . PHP_EOL;

                return;
            }
            $proxy_id = $proxy->id;
            TestProxy::where('id', $proxy_id)->update(['active' => 0]);

            // update install task
            //TODO: install tasks array
            $installTasksArray[] = [
                'id' => $task->id,
                'unit_id' => $task->unit_id,
                'account_id' => $accounts->id,
                'status' => 'created',
                'delayed_date' => date('Y-m-d H:i:s'),
                'proxy_id' => $proxy_id,
            ];
            // TestTask::where('id', $task->id)->update([
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            //     'delayed_date' => date('Y-m-d H:i:s'),
            // ]);



            DB::enableQueryLog(); //Enable query logging
            // update launch, uninstall task
            //TODO: launch, uninstall tasks array 1
            if ($time_diff > 0) {
                // $launchTasksArray1[] = [
                //     'retention_bundle_id' => $task->id,
                //     'unit_id' => $task->unit_id,
                //     'account_id' => $accounts->id,
                //     'status' => 'delayed',
                //     'delayed_date' => DB::raw('delayed_date + ' . $time_diff),
                // ];
                TestTask::where('retention_bundle_id', $task->id)->update([
                    'unit_id' => $task->unit_id,
                    'account_id' => $accounts->id,
                    'status' => 'delayed',
                    'delayed_date' => DB::raw('delayed_date + INTERVAL ' . $time_diff . ' SECOND'),
                ]);
            } else {
                //TODO: launch, uninstall tasks array 1
                // $launchTasksArray2[] = [
                //     'retention_bundle_id' => $task->id,
                //     'unit_id' => $task->unit_id,
                //     'account_id' => $accounts->id,
                //     'status' => 'delayed',
                // ];
                TestTask::where('retention_bundle_id', $task->id)->update([
                    'unit_id' => $task->unit_id,
                    'account_id' => $accounts->id,
                    'status' => 'delayed',
                ]);
            }
        }

        if (isset($installTasksArray)) {
            print_r($installTasksArray);
            // TestTask::upsert($installTasksArray, ['id'], ['unit_id', 'account_id', 'status', 'delayed_date', 'proxy_id']);
            TestTask::massUpdate(
                $installTasksArray,
                uniqueBy: 'id'
            );
            unset($installTasksArray);
        }
        dd(DB::getQueryLog()); //print the SQl query
        /*
        if (isset($launchTasksArray1)) {
            print_r($launchTasksArray1);
            // TestTask::upsert($launchTasksArray1, ['retention_bundle_id'], ['unit_id', 'account_id', 'status', 'delayed_date']);
            // TestTask::massUpdate(
            //     $launchTasksArray1,
            //     uniqueBy: 'retention_bundle_id'
            // );
            TestTask::batchUpdate($launchTasksArray1, 'retention_bundle_id');
            unset($launchTasksArray1);
        }
        if (isset($launchTasksArray2)) {
            print_r($launchTasksArray2);
            // TestTask::upsert($launchTasksArray2, ['retention_bundle_id'], ['unit_id', 'account_id', 'status']);
            // TestTask::massUpdate(
            //     $launchTasksArray2,
            //     uniqueBy: 'retention_bundle_id'
            // );
            TestTask::batchUpdate($launchTasksArray2, 'retention_bundle_id');
            unset($launchTasksArray2);
        }
        */
    }

    private function otherDelayedTasks($units)
    {
        $message = 'Check delayed tasks without retention';
        echo $message . PHP_EOL;
        $tasks = TestTask::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->where('retention', 0)->orderBy('id', 'desc')->limit(10)->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no delayed tasks... exit';
            echo $message . PHP_EOL;

            return;
        }

        $message = 'Shuffle unit ids';
        echo $message . PHP_EOL;
        $b = $this->doStuff($units, 'unit_id');

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            echo $message . PHP_EOL;

            $proxy_id = 0;

            $usedAccounts = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->get(['account_id']);
            $accountType = 'google';

            if ($task->country == 'any') {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                // DB::enableQueryLog(); //Enable query logging
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
                // dd(DB::getQueryLog()); //print the SQl query
            }

            if (!$accounts) {
                $message = 'There is no accounts... exit';
                echo $message . PHP_EOL;

                return;
            }

            $task->country = ($task->country == 'any') ? $accounts->country : $task->country;

            if ($accounts->unit_id == 0) {
                $task->unit_id = $b->current();
                $b->next();

                $message = 'New unit id: ' . $task->unit_id;
                echo $message . PHP_EOL;
                // info($message);
                // TestAccount::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
            } else {
                $message = 'Old unit id: ' . $accounts->unit_id;
                echo $message . PHP_EOL;
                // info($message);
                $task->unit_id =  $accounts->unit_id;
            }

            $proxy = TestProxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            if (!$proxy) {
                $message = 'There is no proxy... exit';
                echo $message . PHP_EOL;

                return;
            }
            $proxy_id = $proxy->id;
            TestProxy::where('id', $proxy_id)->update(['active' => 0]);

            $message = 'Proxy: ' . $proxy_id;
            echo $message . PHP_EOL;

            //TODO: tasks array
            $insertArray[] = [
                'id' => $task->id,
                'unit_id' => $task->unit_id,
                'account_id' => $accounts->id,
                'status' => 'created',
                'proxy_id' => $proxy_id,
            ];
            // TestTask::where('id', $task->id)->update([
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            // ]);
        }

        if (isset($insertArray)) {
            $chunks = array_chunk($insertArray, 5);
            foreach ($chunks as $item) {
                TestTask::upsert($item, uniqueBy: ['id'], update: ['unit_id', 'account_id', 'status', 'proxy_id']);
            }
            unset($chunks, $insertArray);
        }
    }

    private function retentionDelayedTasks()
    {
        $message = 'Check delayed tasks with retention';
        echo $message . PHP_EOL;
        $tasks = TestTask::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->whereNot('retention', 0)->orderBy('id', 'desc')->limit(10)->get();
        echo $tasks->count() . PHP_EOL;

        if ($tasks->count() == 0) {
            $message = 'There is no delayed tasks with retention... exit';
            echo $message . PHP_EOL;

            return;
        }

        foreach ($tasks as $task) {
            $message = 'task id: ' . $task->id;
            // echo $message . PHP_EOL;

            $proxy_id = 0;

            /*
            $usedAccounts = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('account_id')->get(['account_id']);
            $usedUnits = TestTask::where('type', $task->type)->where('app_bundle', $task->app_bundle)->groupBy('unit_id')->get(['unit_id']);
            $accountType = 'google';

            if ($task->country == 'any') {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->inRandomOrder()->first();
            } else {
                $accounts = TestAccount::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task->country)->inRandomOrder()->first();
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
                // TestAccount::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);

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

            $proxy = TestProxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            if (!$proxy) {
                $message = 'There is now proxy... exit';
                echo $message . PHP_EOL;

                return;
            }
            $proxy_id = $proxy->id;
            TestProxy::where('id', $proxy_id)->update(['active' => 0]);

            // update install task
            //TODO: install tasks array
            $installTasksArray[] = [
                'id' => $task->id,
                // 'unit_id' => $task->unit_id,
                // 'account_id' => $accounts->id,
                'status' => 'created',
                'delayed_date' => date('Y-m-d H:i:s'),
                'proxy_id' => $proxy_id,
            ];
            // TestTask::where('id', $task->id)->update([
            //     'unit_id' => $task->unit_id,
            //     'account_id' => $accounts->id,
            //     'status' => 'created',
            //     'delayed_date' => date('Y-m-d H:i:s'),
            // ]);

            // update launch, uninstall task
            //TODO: launch, uninstall tasks array 1
            if ($time_diff > 0) {
                // $launchTasksArray1[] = [
                //     'retention_bundle_id' => $task->id,
                //     'unit_id' => $task->unit_id,
                //     'account_id' => $accounts->id,
                //     'status' => 'delayed',
                //     'delayed_date' => DB::raw('delayed_date + ' . $time_diff),
                // ];
                TestTask::where('retention_bundle_id', $task->id)->update([
                    // 'unit_id' => $task->unit_id,
                    // 'account_id' => $accounts->id,
                    'status' => 'delayed',
                    'delayed_date' => DB::raw('delayed_date + INTERVAL ' . $time_diff . ' SECOND'),
                ]);
            } else {
                //TODO: launch, uninstall tasks array 1
                // $launchTasksArray2[] = [
                //     'retention_bundle_id' => $task->id,
                //     'unit_id' => $task->unit_id,
                //     'account_id' => $accounts->id,
                //     'status' => 'delayed',
                // ];
                TestTask::where('retention_bundle_id', $task->id)->update([
                    // 'unit_id' => $task->unit_id,
                    // 'account_id' => $accounts->id,
                    'status' => 'delayed',
                ]);
            }
        }

        if (isset($installTasksArray)) {
            TestTask::upsert($installTasksArray, uniqueBy: ['id'], update: ['status', 'delayed_date', 'proxy_id']);
            unset($installTasksArray);
        }
        /*
        if (isset($launchTasksArray1)) {
            TestTask::upsert($launchTasksArray1, uniqueBy: ['retention_bundle_id'], update: ['unit_id', 'account_id', 'status', 'delayed_date']);
            unset($launchTasksArray1);
        }
        if (isset($launchTasksArray2)) {
            TestTask::upsert($launchTasksArray2, uniqueBy: ['retention_bundle_id'], update: ['unit_id', 'account_id', 'status']);
            unset($launchTasksArray2);
        }
        */
    }
}

<?php

namespace App\Http\Controllers\API;

use DateTime;
use Generator;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Error;

use App\Models\Proxy;
use App\Models\Review;
use App\Models\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\CookieUpload;
use App\Http\Controllers\API\BaseController as BaseController;

class TaskController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
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
                $usedAccounts = Task::where('type', $input['type'])->where('app_bundle', $input['app_bundle'])->groupBy('account_id')->get(['account_id']);
                $accountType = 'google';
                break;
            case 'youtube':
                $usedAccounts = Task::where('type', $input['type'])->where('url', $input['url'])->groupBy('account_id')->get(['account_id']);
                $accountType = 'google';
                break;
            case 'import_chrome_cookies':
            case 'export_chrome_cookies':
                $usedAccounts = [];
                $accountType = 'google';
                break;

            case 'tiktok':
                $usedAccounts = Task::where('type', $input['type'])->where('url', $input['url'])->groupBy('account_id')->get(['account_id']);
                $accountType = 'tiktok';
                break;
        }

        // if ($input['type'] == 'export_chrome_cookies') {
        //     $cookie = new CookieUpload;
        //     $input['filename'] = $cookie->justUpload($request);
        // }

        // review
        if (isset($input['review']) && $input['review'] == 1) {
            $reviewNumber = floor(($input['amount'] * $input['review_amount']) / 100);
            $i1 = array_fill(0, $input['amount'], 0);
            $i2 = array_fill(0, $reviewNumber, 1); // NOTE: comes from frontend
            $input['review_mask'] = array_replace($i1, $i2);
            shuffle($input['review_mask']);
            // $r = new \Random\Randomizer();
            // $arr2 = $r->shuffleArray($arr2);
        }

        // TODO: HARDCODED
        $input['attempts_changes'] = $input['attempts'];
        $input['bundles'] = 3;

        // 1. Берем аккаунты по стране и их юниты
        if ($input['country'] == 'any') {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->get();
        } else {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $input['country'])->get();
        }

        // 2. Получаем все юниты, чтобы назначить их на аккаунты, у которых updated_at <= 10 минут
        $b = [];
        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
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
        $unit = Unit::where('unit_id', $unit_id)->first();
        if ($unit) {
            $unit->last_seen = date('Y-m-d H:i:s');
            $unit->touch();
        }

        $task = Task::where('unit_id', $unit_id)->firstWhere('status', 'created');

        if (is_null($task)) {
            return $this->sendResponse(['task' => ['idle' => 300]], 'Task not found.');
        }

        // TODO: check for free proxy
        if ($task->proxy_id == 0) {
            $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
            // $proxy = null; // TODO: test
            if (!$proxy) {
                // $message = 'There is no free proxy... trying busy...';
                // echo $message . PHP_EOL;
                $proxy = Proxy::where('active', 0)->where('country', $task->country)->inRandomOrder()->first(['id']);
            }
            $proxy_id = $proxy->id;
            Proxy::where('id', $proxy_id)->update(['active' => 0]);

            $task->proxy_id = $proxy_id;
            $task->save();
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

        $task = Task::where('unit_id', $unit_id)->firstWhere('id', $input['task_id']);

        if (!$task) {
            return $this->sendError('No task found.');
        }

        $task->id = $input['task_id'];
        $task->status = $input['status'];

        // if ($task->proxy_id == 0) {
        //     $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);

        //     if (!$proxy) {
        //         return $this->sendError('No proxy found.');
        //     }

        //     $task->proxy_id = $proxy->id;
        //     Proxy::where('id', $task->proxy_id)->update(['active' => 0]);
        // }

        if ($input['status'] == 'done') {
            Proxy::where('id', $task->proxy_id)->update(['active' => 1]);
            Task::where('id', $task->id)->update(['status' => 'done']);

            return $this->sendResponse(['success' => true], 'Task done successfully.');
        }

        $accountTypes = [
            'install' => 'google',
            'launch' => 'google',
            'uninstall' => 'google',
            'youtube' => 'google',
            'tiktok' => 'tiktok',
        ];

        $usedAccounts = Task::where('type', $task->type);
        $usedUnits = Task::where('type', $task->type);
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
            if ($errors->count() >= 2 && in_array($error->type, $accountErrors)) {
                Account::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => $error->comment]);
            }

            Proxy::where('id', $task->proxy_id)->update(['fails' => DB::raw('fails + 1')]);

            if ($input['error']['type'] == 'nonworking_proxy') {
                Proxy::where('id', $task->proxy_id)->update(['active' => 1]);

                $proxy = Proxy::where('active', 1)->where('country', $task->country)->inRandomOrder()->first(['id']);
                if (!$proxy) {
                    // $message = 'There is no free proxy... trying busy...';
                    // echo $message . PHP_EOL;
                    $proxy = Proxy::where('active', 0)->where('country', $task->country)->inRandomOrder()->first(['id']);
                }
                $task->proxy_id = $proxy->id;
                Proxy::where('id', $task->proxy_id)->update(['active' => 0]);


                //TODO: Pending or leave as is?
                // if (!$proxy) {
                //     $task->status = 'pending';
                //     $task->proxy_id = 0;
                //     $task->attempts_changes = $task->attempts_changes - 1;
                //     $task->save();

                //     return $this->sendError('No proxy found.');
                // } else {
                //     $task->proxy_id = $proxy->id;
                //     Proxy::where('id', $task->proxy_id)->update(['active' => 0]);
                // }
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
                $accounts = Account::whereNotIn('id', $usedAccountsResponse)->where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->where('blocked', 0)->inRandomOrder()->first();
                if (!$accounts) {
                    $task->status = 'pending';
                } else {
                    if ($accounts->unit_id == 0 && $task->type == 'install') {
                        $units = Unit::whereNotIn('unit_id', $usedUnitsResponse)->where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->first();

                        if ($units) {
                            $accounts->unit_id = $units->unit_id;
                            Account::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
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
                $accounts = Account::whereNotIn('id', $usedAccountsResponse)->where('type', $accountType)->whereNot('unit_id', $unit_id)->where('country', $task->country)->where('blocked', 0)->inRandomOrder()->first();
                if (!$accounts) {
                    $task->status = 'pending';
                } else {
                    if ($accounts->unit_id == 0) {
                        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->first();

                        if ($units) {
                            $accounts->unit_id = $units->unit_id;
                            Account::where('id', $accounts->id)->update(['unit_id' => $accounts->unit_id]);
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
            if ($task->type == 'install') {
                $task->where('id', $task->id)->update([
                    'status' => $input['status'],
                ]);

                $task->where('retention_bundle_id', $task->id)->update([
                    'status' => $input['status'],
                ]);
            }

            if ($task->type == 'launch' || $task->type == 'uninstall') {
                $task->where('id', $task->retention_bundle_id)->update([
                    'status' => $input['status'],
                ]);

                $task->where('retention_bundle_id', $task->retention_bundle_id)->update([
                    'status' => $input['status'],
                ]);
            }
            // $task->where('retention_bundle_id', $task->retention_bundle_id)->update([
            //     'status' => $input['status'],
            // ]);
        }

        if ($input['status'] == 'failed' && $task->bundles == 0) {
            Proxy::where('id', $task->proxy_id)->update(['active' => 1]);
        }

        if ($input['status'] == 'needs_confirmation') {
            Account::where('id', $task->account_id)->update(['blocked' => 1, 'comment' => 'needs_confirmation']);
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
     * @return \Illuminate\Http\JsonResponse
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

        if ($input['app_bundle']) {
            $googlePage = $this->getGooglePage($input['app_bundle']);
            $input['app_company'] = $this->getGoogleDeveloperName($googlePage) ?? '';
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

                    Account::where('id', $accounts[$i]['id'])->update(['unit_id' => $input['unit_id']]);
                } else {
                    $input['unit_id'] =  $accounts[$i]['unit_id'];
                }
            }

            // if ($input['fill_type'] == 'spread' && $i > 0) {
            //     $input['status'] = 'delayed';
            //     if ($i >= $units_count) {
            //         $input['status'] = 'pending';
            //         $input['account_id'] = 0;
            //         $input['unit_id'] = 0;
            //     }
            //     $input['delayed_date'] = $datesInterval[$i];
            // }

            if ($input['fill_type'] == 'spread' && $i > 0) {
                $input['status'] = 'delayed';
                $input['account_id'] = 0;
                $input['unit_id'] = 0;
                $input['delayed_date'] = $datesInterval[$i];
            }

            if (!isset($accounts[$i]['id'])) {
                $input['account_id'] = 0;
                $input['unit_id'] = 0;
                $input['status'] = 'pending';
            }

            // if ($input['status'] == 'created') {
            //     $proxy = Proxy::where('active', 1)->where('country', $input['country'])->inRandomOrder()->first(['id']);

            //     if ($proxy) {
            //         $input['proxy_id'] = $proxy->id;
            //         Proxy::where('id', $input['proxy_id'])->update(['active' => 0]);
            //     } else {
            //         $input['status'] = 'pending';
            //         $input['proxy_id'] = 0;
            //     }
            // }

            $task = Task::create($input);

            // review
            if (isset($input['review_mask']) && $input['review_mask'][$i] == 1) {
                $input['google_title'] = $this->getGoogleAppTitle($googlePage) ?? '';
                $input['google_description'] = $this->getGoogleAppDescription($googlePage) ?? '';
                // generate review using chatGPT
                $review_text = $this->chatGptReview($input);
                $review_rate = $input['review_rate'];
                // insert review into review table
                Review::create([
                    'text' => $review_text,
                    'rate' => $review_rate,
                    'task_id' => $task->id
                ]);
                // mark review field as 1 in task table
                $task->review = 1;
                $task->save();
            }

            echo $i . ' -> INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;
        }

        return $task;
    }

    private function retentionTasks($input, $units_count, $b, $accounts)
    {
        if ($input['fill_type'] == 'spread') {
            $spreadDays = $input['spread_days'] ?? 1;
            $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
        }

        if ($input['app_bundle']) {
            $googlePage = $this->getGooglePage($input['app_bundle']);
            $input['app_company'] = $this->getGoogleDeveloperName($googlePage) ?? '';
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
                $proxy = Proxy::where('active', 1)->where('country', $input['country'])->inRandomOrder()->first(['id']);

                if ($proxy) {
                    $input['proxy_id'] = $proxy->id;
                    Proxy::where('id', $input['proxy_id'])->update(['active' => 0]);
                } else {
                    $input['status'] = 'pending';
                    $input['proxy_id'] = 0;
                }
            }

            $task = Task::create($input);
            $lastInsertedId = $task->id;
            $input['proxy_id'] = 0;

            // review
            if (isset($input['review_mask']) && $input['review_mask'][$i] == 1) {
                $input['google_title'] = $this->getGoogleAppTitle($googlePage) ?? '';
                $input['google_description'] = $this->getGoogleAppDescription($googlePage) ?? '';
                // generate review using chatGPT
                $review_text = $this->chatGptReview($input);
                $review_rate = $input['review_rate'];
                // insert review into review table
                Review::create([
                    'text' => $review_text,
                    'rate' => $review_rate,
                    'task_id' => $lastInsertedId
                ]);
                // mark review field as 1 in task table
                $task->review = 1;
                $task->save();
            }

            $retentionArray = explode(',', $input['retention']);
            foreach ($retentionArray as $item) {
                $input['type'] = 'launch';
                $input['status'] = ($i >= $units_count) ? 'pending' : 'delayed';
                $input['delayed_date'] = (new \DateTime($input['delayed_date']))->modify('+' . $item . ' day')->format('Y-m-d H:i:s');
                $input['retention_bundle_id'] = $lastInsertedId;
                echo $i . ' -> LAUNCH: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . ', last: ' . $lastInsertedId . PHP_EOL;
                $task = Task::create($input);
            }

            $lastRetentionDate = end($retentionArray) + 1;
            $input['type'] = 'uninstall';
            $input['status'] = ($i >= $units_count) ? 'pending' : 'delayed';
            $input['delayed_date'] = (new \DateTime($input['delayed_date']))->modify('+' . $lastRetentionDate . ' day')->format('Y-m-d H:i:s');
            $input['retention_bundle_id'] = $lastInsertedId;
            echo $i . ' -> UNINSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . ', last: ' . $lastInsertedId . PHP_EOL . PHP_EOL;
            $task = Task::create($input);
        }

        return $task;
    }

    private function getGooglePage($bundle)
    {
        $response = Http::get('https://play.google.com/store/apps/details?id=' . $bundle . '&hl=en');
        $status = $response->status();

        if ($status == 200) {
            $str = $response->body();

            return $str;
        }

        return false;
    }

    private function getGoogleDeveloperName($page)
    {
        if ($page) {
            preg_match('/{"@type":"Person","name":"(.*?)"+/', $page, $matches);

            return $matches[1];
        }

        return false;
    }

    private function getGoogleAppTitle($page)
    {
        if ($page) {
            preg_match('/"\@type":"SoftwareApplication","name":"(.*?)"/', $page, $matches);

            return $matches[1];
        }

        return false;
    }

    private function getGoogleAppDescription($page)
    {
        if ($page) {
            preg_match('/data-g-id="description">(.*?)<\/div>/', $page, $matches);

            return $matches[1];
        }

        return false;
    }

    private function chatGptReview(array $input)
    {
        $token = 'env('OPENAI_API_KEY')';

        $templateSearchTerm = 'Write a review for a game on Google Play. Use the following game title: [GAME TITLE] and the description: [GAME DESCRIPTION] in the review. The review must be at least 200 characters long and include the mandatory words/phrases: [GAME KEYWORD]. The tone of the review should be friendly and positive.';
        $templateNoSearchTerm = 'Write a review for a game on Google Play. Use the following game title: [GAME TITLE] and the description: [GAME DESCRIPTION] in the review. The review must be at least 200 characters long. The tone of the review should be friendly and positive.';

        $from = ['[GAME TITLE]', '[GAME DESCRIPTION]'];
        $to = [$input['google_title'], $input['google_description']];

        if (!isset($input['search_term'])) {
            $template = str_replace($from, $to, $templateNoSearchTerm);
        } else {
            array_push($from, '[GAME KEYWORD]');
            array_push($to, $input['search_term']);
            $template = str_replace($from, $to, $templateSearchTerm);
        }

        $data = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $template,
                ],
            ],
        ];
        $response = Http::withToken($token)->post('https://api.openai.com/v1/chat/completions', $data);
        $status = $response->status();

        if ($status == 200) {
            $str = $response->body();
            $array = json_decode($str);

            return $array->choices[0]->message->content;
        }

        return false;
    }
}

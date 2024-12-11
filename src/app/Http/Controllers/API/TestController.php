<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\Task;

use App\Models\Unit;

use App\Models\Error;
use App\Models\Proxy;

use App\Models\Account;
use App\Models\TestTask;
use App\Models\TestAccount;
use Illuminate\Http\Request;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UnitResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\API\BaseController as BaseController;

class TestController extends BaseController
{
    public function postTest(Request $request): JsonResponse
    {
        // echo '<pre>';

        // $start = $request->start;
        // $length = $request->length;
        // $draw = $request->draw;
        // $search = $request->search->value;
        // $order_column_id = $request->order->column;
        // $order_dir = $request->order->dir;

        $page = ($request->start / $request->length) + 1;
        $orderBy = $request->order[0]['name'];
        $orderDir = $request->order[0]['dir'] == 'asc' ? 'asc' : 'desc';
        $accounts = Account::orderBy($orderBy, $orderDir)->paginate($request->length, ['*'], 'page', $page);
        // $accounts = Account::paginate($request->length);

        if ($accounts->count() == 0) {
            $base_array = array('draw' => $request->draw, 'recordsTotal' => 0, 'recordsFiltered' => 0);
            $data_array = array('data' => array());
            $final = array_merge($base_array, $data_array);
            echo json_encode($final);
            exit;
        }

        $base_array = array('draw' => $request->draw, 'recordsTotal' => $accounts->total(), 'recordsFiltered' => $accounts->total(), 'currentPage' => $page, 'sort' => $orderBy);
        // $data_array = $accounts->toArray();
        foreach ($accounts as $item) {
            $data_array['data'][] = array_values([
                "id" => $item['id'],
                "type" => $item['type'],
                "email" => $item['email'],
                "password" => $item['password'],
                "country" => $item['country'],
                "created_at" => $item['created_at'],
                "updated_at" => $item['updated_at'],
                "backup_email" => $item['backup_email'],
                "actions" => '<button class="btn btn-info btn-edit" type="button">Edit</button>
                            <button class="btn btn-danger btn-delete" type="button">Delete</button>',
                // "blocked" => $item['blocked'],
                // "comment" => $item['comment'],
            ]);
        }
        $final = array_merge($base_array, $data_array);
        // echo json_encode($final);
        return response()->json($final);



        // foreach ($accounts as $item) {
        //     echo $item['id'] . ' -> ' . $item['email'] . PHP_EOL;
        // }
        // echo '</pre>';
    }

    public function update(Request $request, int $task_id)
    {
        $input = $request->only(['error']);

        echo $task_id . PHP_EOL;
        print_r($input);
    }

    private function otherTasks($input, $units_count, $b, $accounts)
    {
        if (isset($input['amount'])) {

            if ($input['fill_type'] == 'spread') {
                $spreadDays = $input['spread_days'] ?? 1;
                $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
            }

            for ($i = 0; $i < $input['amount']; $i++) {
                if ($input['country'] == 'any') {
                    $input['country'] = $accounts[$i]['country'];
                }
                // $input['status'] = isset($input['status']) ? $input['status'] : 'created2';
                // $input['status'] = 'created2';
                // $input['status'] = ($i >= $units_count) ? 'pending2' : 'created2';
                $input['status'] = 'created2';
                if ($i >= $units_count) {
                    $input['status'] = 'pending2';
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

                        // Account::where('id', $accounts[$i]['id'])->update(['unit_id' => $input['unit_id']]);
                    } else {
                        $input['unit_id'] =  $accounts[$i]['unit_id'];
                    }
                }

                if (!isset($accounts[$i]['id'])) {
                    $input['account_id'] = 0;
                    $input['unit_id'] = 0;
                    $input['status'] = 'pending2';
                    // $input['unit_id'] = $b->current();
                    // $b->next();
                }

                if ($input['fill_type'] == 'spread' && $i > 0) {
                    $input['status'] = 'delayed2';
                    if ($i >= $units_count) {
                        $input['status'] = 'pending2';
                        $input['account_id'] = 0;
                        $input['unit_id'] = 0;
                    }
                    // $input['status'] = ($i >= $units_count) ? 'pending2' : 'delayed2';
                    $input['delayed_date'] = $datesInterval[$i];
                }

                // $task = Task::create($input);
                echo $i . ' -> INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;
            }
        }
    }

    private function retentionTasks($input, $units_count, $b, $accounts)
    {
        if (isset($input['amount'])) {
            for ($i = 0; $i < $input['amount']; $i++) {
                $input['status'] = ($i >= $units_count) ? 'pending2' : 'created2';
                $input['account_id'] = $accounts[$i]['id'] ?? 0;

                if (isset($accounts[$i]['unit_id'])) {
                    if ($accounts[$i]['unit_id'] == 0) {
                        $input['unit_id'] = $b->current();
                        $b->next();
                    } else {
                        $input['unit_id'] =  $accounts[$i]['unit_id'];
                    }
                }

                if (!isset($accounts[$i]['id']) || $i >= $units_count) {
                    $input['account_id'] = 0;
                    $input['status'] = 'pending2';
                    $input['unit_id'] = 0;
                }

                echo $i . ' -> units_count: ' . $units_count . ', INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;
                // $task = Task::create($input);

                $input['account_id'] = 0;
                $input['delete'] = 0;
                $retentionArray = explode(',', $input['retention']);
                foreach ($retentionArray as $item) {
                    $input['type'] = 'launch';
                    $input['status'] = ($i >= $units_count) ? 'pending2' : 'delayed2';
                    $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $item . ' day')->format('Y-m-d H:i:s');
                    echo $i . ' -> LAUNCH: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;
                    // $task = Task::create($input);
                }

                $lastRetentionDate = end($retentionArray) + 1;
                $input['type'] = 'uninstall';
                $input['status'] = ($i >= $units_count) ? 'pending2' : 'delayed2';
                $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $lastRetentionDate . ' day')->format('Y-m-d H:i:s');
                echo $i . ' -> UNINSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL . PHP_EOL;
                // $task = Task::create($input);
            }
        }
    }

    private function isRetention($input)
    {
        $tasks = Task::where('app_bundle', $input['app_bundle'])->where('unit_id', $input['unit_id'])->get(['app_bundle', 'unit_id', 'type', 'status']);
        foreach ($tasks as $item) {
            echo $item['app_bundle'] . ' -> ' . $item['unit_id'] . ' -> ' . $item['type'] . ' -> ' . $item['status'] . PHP_EOL;
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        echo '<pre>';

        $input['country'] = 'us';
        $proxy = Proxy::where('active', 1)->where('country', $input['country'])->inRandomOrder()->first(['id']);
        echo $proxy->id . PHP_EOL;

        // $input['app_bundle'] = 'com.littlebitgames.idleevolution';
        // $input['unit_id'] = 28;
        // $this->isRetention($input);
        /*
        $accountErrors = [
            'captcha_unsolvable',
            'wrong_password',
            'account',
        ];
        $task['account_id'] = 201;

        DB::enableQueryLog(); //Enable query logging
        $errors = Error::where('account_id', $task['account_id'])->whereIn('type', $accountErrors)->get();
        dd(DB::getQueryLog()); //print the SQl query
        */

        /*
        DB::enableQueryLog(); //Enable query logging
        $usedAccounts = TestTask::where('type', 'install');
        $usedAccounts->where('app_bundle', 'app.test.3')->groupBy('account_id');
        $usedAccountsResponse = $usedAccounts->get(['account_id']);

        $accounts = TestAccount::whereNotIn('id', $usedAccountsResponse)->where('type', 'google')->whereNot('unit_id', 27)->where('country', 'us')->where('blocked', 0)->inRandomOrder()->first();
        dd(DB::getQueryLog()); //print the SQl query
        */

        /*
        $input['type'] = 'install';
        $input['app_bundle'] = 'com.MarkL.Plinko';
        $input['country'] = 'us';
        $input['amount'] = 150;
        $input['fill_type'] = 'fast'; // fast, spread
        $input['spread_days'] = 13;
        $input['retention'] = 0;
        // $input['retention'] = '1,3,4,6';

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

        echo 'usedAccounts: ' . $usedAccounts->count() . PHP_EOL;

        $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $input['country'])->get();
        echo 'accounts: ' . $accounts->count()  . PHP_EOL;

        $units = Unit::where('last_seen', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->whereNot('blocked', 1)->get();
        echo 'units: ' . $units->count() . PHP_EOL;

        // if ($units->count() == 0) {
        //     // if ($cc == 0) {
        //     $units = Unit::all();
        //     $input['status'] = 'pending';
        // }

        if ($units->count() > 0) {
            $b = $this->doStuff($units, 'unit_id');
        }

        echo 'units: ' . $units->count() . PHP_EOL;
        foreach ($units as $item) {
            echo $item['unit_id'] . PHP_EOL;
        }
        $units_count = $units->count();
        // $units_count = 0;

        if ($input['retention'] != 0) {
            echo 'retentionTasks:' . PHP_EOL;
            $this->retentionTasks($input, $units_count, $b, $accounts);
        } else {
            echo 'otherTasks:' . PHP_EOL;
            $this->otherTasks($input, $units_count, $b, $accounts);
        }
        */

        /*
        if (isset($input['amount'])) {

            if ($input['fill_type'] == 'spread') {
                $spreadDays = $input['spread_days'] ?? 1;
                $datesInterval = $this->intervalBreaks($input['amount'], $spreadDays);
            }

            for ($i = 0; $i < $input['amount']; $i++) {
                $input['status'] = 'created';
                $input['account_id'] = $accounts[$i]['id'] ?? 0;
                // echo $i . ': account -> ' . $input['account_id'] . ', ';

                if (isset($accounts[$i]['unit_id'])) {
                    if ($accounts[$i]['unit_id'] == 0) {
                        $input['unit_id'] = $b->current();
                        $b->next();

                        // echo 'unit_id(1) -> ' . $input['unit_id'];
                    } else {
                        $input['unit_id'] =  $accounts[$i]['unit_id'];

                        // echo 'unit_id(2) -> ' . $input['unit_id'];
                    }
                }

                if (!isset($accounts[$i]['id'])) {
                    $input['account_id'] = 0;
                    $input['status'] = 'pending';
                    $input['unit_id'] = $b->current();
                    $b->next();
                }
                // print_r($input);

                if ($input['fill_type'] == 'spread' && $i > 0) {
                    $input['account_id'] = 0;
                    $input['status'] = 'delayed';
                    $input['delayed_date'] = $datesInterval[$i];
                }
                // print_r($input);
                // echo PHP_EOL;
                echo 'INSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;

                if ($input['retention'] != 0) {
                    $input['account_id'] = 0;
                    $input['delete'] = 0;
                    $retentionArray = explode(',', $input['retention']);
                    foreach ($retentionArray as $item) {
                        $input['type'] = 'launch';
                        $input['status'] = 'delayed';
                        $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $item . ' day')->format('Y-m-d H:i:s');
                        echo 'LAUNCH: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL;
                    }

                    $lastRetentionDate = end($retentionArray) + 1;
                    $input['type'] = 'uninstall';
                    $input['status'] = 'delayed';
                    $input['delayed_date'] = (new \DateTime(date('Y-m-d H:i:s')))->modify('+' . $lastRetentionDate . ' day')->format('Y-m-d H:i:s');
                    echo 'UNINSTALL: unit_id: ' . $input['unit_id'] . ', account_id: ' . $input['account_id'] . ', status: ' . $input['status'] . PHP_EOL . PHP_EOL;
                }
            }
        }
        */

        /*
        $task['country'] = 'any';
        $type = 'tiktok';
        $app_bundle = 'com.littlebgames.shoot_it';
        $youtube_url = 'https://www.youtube.com/watch?v=k9d4PKcZ8fA';
        $tiktok_url = 'https://vm.tiktok.com/ZMhhn41gA/';

        // 1. При создании ордера, проверяем есть ли такой бандл или youtube ссылка в тасках.
        switch ($type) {
            case 'install':
                $usedAccounts = Task::where('type', $type)->where('app_bundle', $app_bundle)->get(['account_id']);
                $accountType = 'google';
                break;

            case 'youtube':
                $usedAccounts = Task::where('type', $type)->where('url', $youtube_url)->get(['account_id']);
                $accountType = 'google';
                break;

            case 'tiktok':
                $usedAccounts = Task::where('type', $type)->where('url', $tiktok_url)->get(['account_id']);
                $accountType = 'tiktok';
                break;
            default:
                $usedAccounts = [];
        }

        // Делаеми выборку аккаунтов, которых нет в этом бандле или ссылке.
        if ($task['country'] == 'any') {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->get();
        } else {
            $accounts = Account::whereNotIn('id', $usedAccounts)->where('type', $accountType)->where('blocked', 0)->where('country', $task['country'])->get();
        }

        foreach ($accounts as $item) {
            echo $item->id . PHP_EOL;
        }
        */


        /*
        echo $request->start . PHP_EOL;
        $accounts = Account::paginate(15);
        foreach ($accounts as $item) {
            echo $item['id'] . ' -> ' . $item['email'] . PHP_EOL;
        }
        */

        /*
        $accountErrors = [
            'captcha_unsolvable',
            'wrong_password',
            'account'
        ];
        $errors = Error::where('account_id', 1922)->whereIn('type', $accountErrors)->get();
        echo $errors->count() . PHP_EOL;
        */

        /*
        DB::enableQueryLog(); //Enable query logging
        $tasks = Task::where('status', 'delayed')->where(DB::raw("DATE_FORMAT(delayed_date, '%Y-%m-%d %H:%i')"), '<=', date('Y-m-d H:i'))->get(['id', 'status']);
        dd(DB::getQueryLog()); //print the SQl query
        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                echo $task->id . PHP_EOL;
            }
        }
        */

        /*
        $id = 20;
        $unit = Unit::where('id', $id)->first();
        echo $unit->unit_id . PHP_EOL;
        */
        /*
        $unit_id = 51;
        $input['task_id'] = 2919;

        // DB::enableQueryLog(); //Enable query logging
        $task = Task::where('unit_id', $unit_id)->firstWhere('id', $input['task_id']);
        // dd(DB::getQueryLog()); //print the SQl query
        if (!$task) {
            return $this->sendError('No task found.');
        }
        */



        /*
        $id = 232;
        // берем все таски, где есть этот аккаунт
        $tasks = Task::where('account_id', $id)->get();
        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                $orders[] = [
                    'task_id' => $task['id'],
                    'order_id' => $task['order_id'],
                    'unit_id' => $task['unit_id'],
                    'account_id' => $task['account_id'],
                    'country' => $task['country'],
                ];
            }
        }

        foreach ($orders as &$item) {
            // берем новый account_id, который не входит в список в данном ордере
            $newAccount = Account::whereNot('id', $item['account_id'])->where('country', $item['country'])->where('blocked', 0)->inRandomOrder()->firstOrFail();
            // назначаем новый аккаунт на таск для этого юнита
            Task::where('id', $item['task_id'])->update(['accountt_id' => $newAccount->id]);
            $item['account_id'] = $newAccount->id;
        }
        */

        /*
        // 2. Получаем все юниты, чтобы назначить их на аккаунты, у которых updated_at <= 10 минут
        $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->get();
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
                echo 'Taks id: ' . $task->id . PHP_EOL;
                // Для ордера, в котором этот таск, берем все аккаунты.
                $usedAccounts = Task::where('order_id', $task->order_id)->get(['account_id']);

                // Делаеми выборку аккаунта, которого нет в этом ордере.
                if ($task->country == 'any') {
                    $accounts = Account::whereNotIn('unit_id', $usedAccounts)->inRandomOrder()->firstOrFail();
                } else {
                    $accounts = Account::whereNotIn('unit_id', $usedAccounts)->where('country', $task->country)->inRandomOrder()->firstOrFail();
                }
                echo 'Account id: ' . $accounts->id . PHP_EOL;

                // Назначаем этот аккаунт юниту и этой таске
                if ($accounts->count() > 0) {
                    echo 'Accounts more than 0' . PHP_EOL;

                    if ($task->country == 'any') {
                        $task->country = $accounts->country;
                    }

                    if ($accounts->unit_id == 0) {
                        $task->unit_id = $b->current();
                        $b->next();

                        echo 'New unit id: ' . $task->unit_id . PHP_EOL;

                        Account::where('id', $accounts->id)->update(['unit_id' => $task->unit_id]);
                    } else {
                        echo 'Old unit id: ' . $accounts->unit_id . PHP_EOL;

                        $task->unit_id =  $accounts->unit_id;
                    }

                    Task::where('id', $task->id)->update([
                        'unit_id' => $task->unit_id,
                        'account_id' => $accounts->id,
                        'status' => 'created',
                    ]);
                    echo 'Saved' . PHP_EOL;
                    // $task->save();
                }

                // Берем юнит из таблицы Accounts у которых есть связка, т.е. unit_id != 0
                // $accounts = Account::whereNot('unit_id', 0)->whereNot('unit_id', $task->unit_id)->where('country', $task->country)->inRandomOrder()->limit(1)->get();
            }
        }
            */


        /*
        $tasks = Task::where('status', 'pending')->groupBy('order_id')->limit(5)->get();

        echo '<pre>';
        foreach ($tasks as $task) {
            echo $task->id . ', order_id: ' . $task->order_id . PHP_EOL;
        }
        echo '</pre>';
        */

        /*
        $units = Unit::all();
        $b = $this->doStuff($units, 'unit_id');

        echo '<pre>';
        for ($i = 0; $i < 10; $i++) {
            echo 'dd: ' . $i . ': ' . $b->current() . PHP_EOL;
            $b->next();
        }
        echo '</pre>';
        */

        /*
        $accountType = 'google';
        $accounts = Account::where('type', $accountType)->get();

        $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->get();
        echo 'units->count: ' . $units->count() . PHP_EOL;
        echo '<pre>';
        foreach ($units as $unit) {
            echo $unit->unit_id . PHP_EOL;
        }
        echo '</pre>';

        // $Unit = Unit::all();
        // $units = Unit::where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 10 MINUTE)'))->get();
        $units = Task::where('status', 'created')->where('updated_at', '>=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))->get('unit_id');

        echo 'units->count: ' . $units->count() . PHP_EOL;

        foreach ($units as $unit) {
            echo $unit->unit_id . PHP_EOL;
        }

        DB::enableQueryLog();
        $tasks = Task::where(function (Builder $query) {
            $query->where('status', 'created')
                ->orWhere('status', 'running');
        })
            ->where('updated_at', '<=', DB::raw('(NOW() - INTERVAL 15 MINUTE)'))
            ->get(['id', 'unit_id', 'country']);
        // $accounts = Account::where('unit_id', 0)->whereNot('unit_id', 666)->where('country', 'us')->inRandomOrder()->limit(1)->get();
        dd(DB::getQueryLog());
        exit;

        if ($accounts->count() > 0) {
            echo 'iii: ' . $accounts[0]['id'] . PHP_EOL;
        }
        // foreach ($accounts as $account) {
        //     echo $account->id . ' account: ' . $account->unit_id . PHP_EOL;
        // }

        echo '<pre>';
        print_r($units);
        echo '</pre>';

        // echo $Unit->random() . PHP_EOL;

        /*
        for ($i = 0; $i < 10; $i++) {
            if ($accounts[$i]['unit_id'] == 0) {
                $accounts[$i]['unit_id'] = $Unit->random()->id;

                Account::where('id', $accounts[$i]['id'])->update(['unit_id' => $accounts[$i]['unit_id']]);
            }
            echo $accounts[$i]['id'] . ' -> ' . $accounts[$i]['unit_id'] . PHP_EOL;
        }
        */



        // foreach ($Unit as $item) {
        //     echo $item['phone_model'] . PHP_EOL;
        // }

        // return $this->sendResponse(UnitResource::collection($Unit), 'Unit retrieved successfully.');

        echo '</pre>';
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

<?php

namespace App\Http\Controllers\API;

use App\Models\Account;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Resources\AccountResource;
use Illuminate\Http\JsonResponse;

class AccountController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Account = Account::all();

        return $this->sendResponse(AccountResource::collection($Account), 'Account retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $proxy = Account::create($input);

        return $this->sendResponse(new AccountResource($proxy), 'Account created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $task = Account::find($id);

        if (is_null($task)) {
            return $this->sendError('Task not found.');
        }

        return $this->sendResponse(new AccountResource($task), 'Account retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();

        $account = Account::where('id', $id)->first();
        $account->type = $input['type'];
        $account->email = $input['email'];
        $account->password = $input['password'];
        $account->country = $input['country'];
        $account->backup_email = $input['backup_email'];
        $account->blocked = $input['blocked'] ?? 0;
        $account->comment = $input['comment'] ?? '';

        // if ($input['comment'] == 'needs_confirmation') {
        //     $account->blocked = 1;
        // }

        $account->save();

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

        return $this->sendResponse(new AccountResource($account), 'Account updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        Account::where('id', $id)->delete();

        return $this->sendResponse([], 'Account deleted successfully.');
    }
}

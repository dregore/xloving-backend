<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Validator;
use Illuminate\Http\JsonResponse;

use App\Models\Task;
use App\Http\Resources\TaskResource;
use DB;

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
            'unit_id' => 'required',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $task = Task::create($input);

        return $this->sendResponse(new TaskResource($task), 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): JsonResponse
    {
        // DB::enableQueryLog(); //Enable query logging
        $task = Task::where('unit_id', $id)->firstWhere('status', 'created');
        // dd(DB::getQueryLog()); //print the SQl query

        if (is_null($task)) {
            // return $this->sendError('Task not found.');
            return $this->sendResponse(['task' => ['idle' => 3600]], 'Task not found.');
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
    public function update(Request $request, $id): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'task_id' => 'required',
            'status' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $task = Task::where('unit_id', $id)->firstWhere('id', $input['task_id']);
        $task->id = $input['task_id'];
        $task->status = $input['status'];
        $task->save();

        return $this->sendResponse(new TaskResource($task), 'Task updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return $this->sendResponse([], 'Task deleted successfully.');
    }
}

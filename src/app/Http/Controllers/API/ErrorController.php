<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Error;

class ErrorController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $input = $request->only(['task_id', 'unit_id', 'account_id', 'type', 'comment']);

        $error = new Error();
        $error->task_id = $input->task_id;
        $error->unit_id = $input->unit_id;
        $error->account_id = $input->account_id;
        $error->type = $input->type;
        $error->comment = $input->comment;
        $error->save();

        return $this->sendResponse(new ErrorResource($error), 'Error created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $account_id)
    {
        $accounts = Error::where('account_id', $account_id)->get();

        if ($accounts) {
            return $accounts;
        }

        return false;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

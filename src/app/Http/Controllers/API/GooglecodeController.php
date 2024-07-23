<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Googlecode;
use App\Http\Resources\GooglecodeResource;

class GooglecodeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Task = Googlecode::all();

        return $this->sendResponse(GooglecodeResource::collection($Task), 'Task retrieved successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $task = Googlecode::find($id);

        if (is_null($task)) {
            return $this->sendError('Task not found.');
        }

        return $this->sendResponse(new GooglecodeResource($task), 'Task retrieved successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $input = $request->all();

        $googlecode = Googlecode::find($id);
        $googlecode->status = $input['status'];
        $googlecode->save();

        return $this->sendResponse(new GooglecodeResource($googlecode), 'Code updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        //
    }
}

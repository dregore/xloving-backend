<?php

namespace App\Http\Controllers\API;

use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UnitResource;

use App\Models\Task;
use App\Models\Account;

class UnitController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $Unit = Unit::all();

        return $this->sendResponse(UnitResource::collection($Unit), 'Unit retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        $unit = Unit::create($input);
        // $lastInsertedId = $unit->unit_id;

        // выбираем account с unit_id = 0
        // ставим этому аккаунту этот юнит

        // выбираем pending таски
        // назначаем этому unit аккаунты
        // выставляем этот unit на таски
        // $tasks = Task::where('status', 'pending')->groupBy('order_id')->limit(5)->get();



        return $this->sendResponse(new UnitResource($unit), 'Unit created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        return $this->sendResponse($unit, 'Unit retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $input = $request->only(['unit_id', 'phone_model', 'location', 'version', 'comment', 'blocked', 'without_timestamp']);

        $unit = Unit::where('id', $id)->first();
        $old_unit_id = $unit->unit_id;
        $new_unit_id = $request->get('unit_id', $unit->unit_id);

        if (!$input) {
            $unit->last_seen = date('Y-m-d H:i:s');
            $unit->touch();
        }

        // $unit = Unit::where('id', $id)->first();
        // $unit->phone_model = $request->get('phone_model', $unit->phone_model);
        // $unit->location = $request->get('location', $unit->location);
        // $unit->version = $request->get('version', $unit->version);
        // $unit->comment = $request->get('comment', $unit->comment);
        // $unit->blocked = $request->get('blocked', $unit->blocked);
        // $unit->save();

        if ($request->get('without_timestamp') ?? false) {
            $unit->last_seen = $unit->last_seen;
        } else {
            $unit->last_seen = date('Y-m-d H:i:s');
        }

        $unit->update([
            $unit->unit_id = $new_unit_id,
            $unit->phone_model = $request->get('phone_model', $unit->phone_model),
            $unit->location = $request->get('location', $unit->location),
            $unit->version = $request->get('version', $unit->version),
            $unit->comment = $request->get('comment', $unit->comment),
            $unit->blocked = $request->get('blocked', $unit->blocked),
        ]);

        if ($old_unit_id != $new_unit_id) {
            // Changes in account with such unit_id to new unit_id
            Account::where('unit_id', $old_unit_id)->update(['unit_id' => $new_unit_id]);
            // Changes in tasks with status created, pending unit_id to new unit_id
            Task::where('unit_id', $old_unit_id)->whereIn('status', ['created', 'pending'])->update(['unit_id' => $new_unit_id]);
        }

        return $this->sendResponse(new UnitResource($unit), 'Unit updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        Unit::where('id', $id)->delete();

        return $this->sendResponse([], 'Unit deleted successfully.');
    }
}

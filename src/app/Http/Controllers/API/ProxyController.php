<?php

namespace App\Http\Controllers\API;

use App\Models\Proxy;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ProxyResource;

class ProxyController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $Proxy = Proxy::all();

        return $this->sendResponse(ProxyResource::collection($Proxy), 'Proxy retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $proxy = Proxy::create($input);

        return $this->sendResponse(new ProxyResource($proxy), 'Proxy created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $proxy = Proxy::findOrFail($id);

        if (is_null($proxy)) {
            return $this->sendError('Proxy not found.');
        }

        return $this->sendResponse(new ProxyResource($proxy), 'Proxy retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $input = $request->all();

        $proxy = Proxy::where('id', $id)->first();
        $proxy->type = $input['type'];
        $proxy->server = $input['server'];
        $proxy->port = $input['port'];
        $proxy->login = $input['login'];
        $proxy->password = $input['password'];
        $proxy->country = $input['country'];
        $proxy->save();

        return $this->sendResponse(new ProxyResource($proxy), 'Proxy updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        Proxy::where('id', $id)->delete();

        return $this->sendResponse([], 'Proxy deleted successfully.');
    }
}

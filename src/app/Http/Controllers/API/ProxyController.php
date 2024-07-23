<?php

namespace App\Http\Controllers\API;

use App\Models\Proxy;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(Proxy $proxy)
    {
        $task = Proxy::find($proxy);

        if (is_null($task)) {
            return $this->sendError('Proxy not found.');
        }

        return $this->sendResponse(new ProxyResource($task), 'Proxy retrieved successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Proxy $proxy)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Proxy $proxy)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Proxy $proxy)
    {
        //
    }
}

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\GooglecodeController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\ProxyController;
use App\Http\Controllers\API\CookieUpload;
use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\TestController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

// Route::middleware('auth:sanctum')->group(function () {
//     Route::resource('tasks', TasksController::class);
// });

Route::controller(TaskController::class)->group(function () {
    Route::resource('tasks', TaskController::class);
});

Route::controller(AccountController::class)->group(function () {
    Route::resource('accounts', AccountController::class);
});

Route::controller(GooglecodeController::class)->group(function () {
    Route::resource('codes', GooglecodeController::class);
});

Route::controller(UnitController::class)->group(function () {
    Route::resource('units', UnitController::class);
});

Route::controller(ProxyController::class)->group(function () {
    Route::resource('proxies', ProxyController::class);
});

Route::post('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileUpload'])->name('fileUpload');
Route::get('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileDownload'])->name('fileDownload');
// Route::delete('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileDelete'])->name('fileDelete');
Route::delete('orders/{order_id}', [TaskController::class, 'destoryByOrderId']);

Route::get('/test', [TestController::class, 'index']);
Route::put('/test/{unit_id}', [TestController::class, 'update']);

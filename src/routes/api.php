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
use App\Http\Controllers\API\ErrorController;

use App\Http\Controllers\TEST\TestTaskController;

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
Route::post('/tasks/serverside', [TaskController::class, 'serverside']);

Route::controller(AccountController::class)->group(function () {
    Route::resource('accounts', AccountController::class);
});
Route::post('/accounts/serverside', [AccountController::class, 'serverside']);

Route::controller(GooglecodeController::class)->group(function () {
    Route::resource('codes', GooglecodeController::class);
});

Route::controller(UnitController::class)->group(function () {
    Route::resource('units', UnitController::class);
});

Route::controller(ProxyController::class)->group(function () {
    Route::resource('proxies', ProxyController::class);
});

Route::controller(ErrorController::class)->group(function () {
    Route::resource('errors', ErrorController::class);
});

Route::post('/cookies/upload', [CookieUpload::class, 'justUpload'])->name('justUpload');
Route::post('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileUpload'])->name('fileUpload');
Route::get('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileDownload'])->name('fileDownload');
// Route::delete('/cookies/{unit_id}/{task_id}', [CookieUpload::class, 'fileDelete'])->name('fileDelete');
Route::delete('orders/{order_id}', [TaskController::class, 'destoryByOrderId']);
Route::get('/cookies', [CookieUpload::class, 'index'])->name('index');

Route::get('/test', [TestController::class, 'index']);
Route::post('/test', [TestController::class, 'postTest']);
Route::put('/test/{unit_id}', [TestController::class, 'update']);

Route::post('/file-upload', [AccountController::class, 'fileUpload']);

/**
 * TEST ROUTES
 */
Route::controller(TestTaskController::class)->group(function () {
    Route::resource('test_tasks', TestTaskController::class);
});

Route::get('/pending_job', [TestTaskController::class, 'pendingJob']);
Route::get('/delayed_job', [TestTaskController::class, 'delayedJob']);
Route::get('/frozen_job', [TestTaskController::class, 'frozenJob']);
Route::get('/running_job', [TestTaskController::class, 'runningJob']);

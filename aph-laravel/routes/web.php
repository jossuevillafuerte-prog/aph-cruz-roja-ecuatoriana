<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\LegacyController;
use App\Http\Controllers\NationalController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'form'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/activate/{ticket}', [ActivationController::class, 'show']);
Route::post('/activate/{ticket}', [ActivationController::class, 'activate'])->middleware('throttle:10,10');
Route::middleware('limits')->group(function () {
    Route::get('/session/status', [AuthController::class, 'status']);
    Route::post('/session/activity', [AuthController::class, 'activity']);
    Route::get('/security', fn () => view('security.sessions'));
    Route::post('/security/revoke', [AuthController::class, 'revoke'])->middleware('throttle:5,5');
    Route::match(['get', 'post'], '/', LegacyController::class);
    Route::post('/drafts', [DraftController::class, 'store'])->middleware('throttle:60,1');
    Route::post('/drafts/discard', [DraftController::class, 'destroy'])->middleware('throttle:30,1');
    Route::middleware('superadmin')->group(function () {
        Route::get('/national', [NationalController::class, 'index']);
        Route::post('/scope', [NationalController::class, 'scope']);
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::post('/users', [UsersController::class, 'store']);
        Route::match(['post', 'put', 'patch'], '/users/{id}', [UsersController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::post('/users/{id}/invite', [UsersController::class, 'invite'])->whereNumber('id')->middleware('throttle:10,1');
    });
});

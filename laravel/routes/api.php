<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskApiController;

Route::get('/masters', [TaskApiController::class, 'masters']);
Route::post('/task/upsert', [TaskApiController::class, 'upsert']);
Route::post('/task/delete', [TaskApiController::class, 'delete']);
Route::get('/tasks', [TaskApiController::class, 'listByDate']);

Route::post('/calendar/import', [TaskApiController::class, 'importCalendar']);
Route::post('/calendar/register', [TaskApiController::class, 'registerCalendar']);

// “リアルタイム”用（SSE）
Route::get('/stream/tasks', [TaskApiController::class, 'stream']);

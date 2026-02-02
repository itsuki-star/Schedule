<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskUiController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tasks', [TaskUiController::class, 'input']); // 各メンバー入力画面
Route::get('/tasks/board', [TaskUiController::class, 'board']); // 一覧画面
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TaskUiController extends Controller
{
    public function input(Request $request)
    {
        $userName  = $request->user()->name  ?? ($request->query('name')  ?? 'Member');
        $userEmail = $request->user()->email ?? ($request->query('email') ?? 'member@example.com');
        $members = config('members') ?? [];   // ← これが重要

        return view('tasks.input', compact('userName', 'userEmail', 'members'));
    }

    public function board(Request $request)
    {
        return view('tasks.board');
    }
}

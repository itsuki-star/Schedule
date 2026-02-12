<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GasClient;

class TaskApiController extends Controller
{
    public function __construct(private GasClient $gas) {}

    public function masters()
    {
        return response()->json(['ok'=>true, 'data'=>$this->gas->call('getMasters')]);
    }

    public function upsert(Request $request)
    {
        $payload = $request->validate([
            'id' => ['nullable','string','max:80'],
            'date' => ['required','date_format:Y-m-d'],
            'user_email' => ['required','email'],
            'user_name' => ['required','string','max:80'],
            'mode' => ['required','in:plan,actual'],
            'priority' => ['required','integer','min:1'],
            'minutes' => ['required','integer','min:1'],
            'client_name' => ['nullable','string','max:255'],
            'task_from' => ['nullable','string','max:50'],
            'task_to' => ['nullable','string','max:50'],
            'task_label' => ['required','string','max:255'],
            'count' => ['nullable','integer','min:0'],
            'start_time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'end_time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'note' => ['nullable','string','max:500'],
            'source' => ['nullable','string','max:50'],
        ]);

        $data = $this->gas->call('upsertTask', $payload);
        return response()->json(['ok'=>true, 'data'=>$data]);
    }

    public function delete(Request $request)
    {
        $payload = $request->validate([
            'id' => ['required','string']
        ]);
        $data = $this->gas->call('deleteTask', $payload);
        return response()->json(['ok'=>true, 'data'=>$data]);
    }

    public function listByDate(Request $request)
    {
        $payload = $request->validate([
            'date' => ['required','date_format:Y-m-d'],
            'mode' => ['nullable','in:plan,actual'],
            'user_email' => ['nullable','email'],
        ]);

        $data = $this->gas->call('listByDate', $payload);
        return response()->json(['ok'=>true, 'data'=>$data]);
    }

    public function importCalendar(Request $request)
{
    $payload = $request->validate([
        'date' => ['required','date_format:Y-m-d'],
        'from_time' => ['required','regex:/^\d{2}:\d{2}$/'],
        'to_time' => ['required','regex:/^\d{2}:\d{2}$/'],
        'user_email' => ['required','email'], // ← 追加
    ]);

    $data = $this->gas->call('importCalendar', $payload);
    return response()->json(['ok'=>true, 'data'=>$data]);
}

    public function registerCalendar(Request $request)
    {
        $payload = $request->validate([
            'date' => ['required','date_format:Y-m-d'],
            'items' => ['required','array','min:1'],
            'items.*.title' => ['nullable','string','max:255'],
            'items.*.task_label' => ['nullable','string','max:255'],
            'items.*.client_name' => ['nullable','string','max:255'],
            'items.*.start_time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'items.*.end_time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'items.*.minutes' => ['nullable','integer','min:1'],
            'items.*.count' => ['nullable','integer','min:0'],
            'items.*.note' => ['nullable','string','max:500'],
        ]);

        $data = $this->gas->call('registerCalendar', $payload);
        return response()->json(['ok'=>true, 'data'=>$data]);
    }

    /**
     * SSE：一覧が「リアルタイム更新」に見えるように、数秒ごとにGASを叩いて流す
     * ※WebSocketなしの最小構成
     */
    public function stream(Request $request)
    {
        $date = $request->query('date');
        if (!$date) abort(400, 'date required');

        return response()->stream(function() use ($date) {
            while (true) {
                $data = $this->gas->call('listByDate', ['date'=>$date]);
                echo "event: tasks\n";
                echo "data: " . json_encode($data) . "\n\n";
                @ob_flush(); @flush();
                sleep(3);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}

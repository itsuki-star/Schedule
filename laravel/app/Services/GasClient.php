<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GasClient
{
    public function call(string $action, array $params = [])
    {
        $url = rtrim((string) config('services.gas.webapp_url'), '/');
        $token = (string) config('services.gas.shared_token');

        if ($url === '') {
            throw new \RuntimeException('GAS_WEBAPP_URL is empty (.env)');
        }
        if ($token === '') {
            throw new \RuntimeException('GAS_SHARED_TOKEN is empty (.env)');
        }

        $payload = [
            'token'  => $token,
            'action' => $action,
            'params' => $params,
        ];

        $res = Http::timeout(20)
            ->acceptJson()
            ->asJson()          // ★重要：JSONで送る（GAS側の e.postData.contents で読める）
            ->post($url, $payload);

        if (!$res->ok()) {
            throw new \RuntimeException('GAS HTTP error: ' . $res->status() . ' ' . $res->body());
        }

        $json = $res->json();

        // ★重要：JSONで返ってきてない/形式違いを分かるようにする
        if (!is_array($json)) {
            throw new \RuntimeException('GAS non-JSON response: ' . $res->body());
        }

        if (!($json['ok'] ?? false)) {
            // ★重要：unknown にならないように、返ってきた中身を丸ごと出す
            throw new \RuntimeException('GAS error: ' . json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $json['data'] ?? null;
    }
}

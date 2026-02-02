<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GasClient
{
    public function call(string $action, array $params = [])
    {
        $url = config('services.gas.webapp_url');
        $token = config('services.gas.shared_token');

        $res = Http::timeout(20)->post($url, [
            'token' => $token,
            'action' => $action,
            'params' => $params,
        ]);

        if (!$res->ok()) {
            throw new \RuntimeException('GAS HTTP error: ' . $res->status() . ' ' . $res->body());
        }

        $json = $res->json();
        if (!($json['ok'] ?? false)) {
            throw new \RuntimeException('GAS error: ' . ($json['error'] ?? 'unknown'));
        }

        return $json['data'] ?? null;
    }
}

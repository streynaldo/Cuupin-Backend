<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class Xendit
{
    private string $base;
    private string $key;
    public function __construct()
    {
        $this->base = rtrim(config('services.xendit.base', 'https://api.xendit.co'), '/');
        $this->key = config('services.xendit.secret_key');
    }
    public function post(string $path, array $json)
    {
        return Http::withBasicAuth($this->key, '')
            ->withHeaders([
                'api-version'       => '2024-11-11',
                'X-IDEMPOTENCY-KEY' => $json['reference_id'] ?? uniqid(),
                'idempotency-key' => $json['reference_id'] ?? null
            ])
            ->acceptJson()
            ->asJson()
            ->post($this->base . $path, $json)
            ->throw()
            ->json();
    }

    public function get(string $path, array $query = [])
    {
        return Http::withBasicAuth($this->key, '')
            ->withHeaders([
                'api-version'       => '2024-11-11',
            ])
            ->acceptJson()
            ->get($this->base . $path, $query)
            ->throw()
            ->json();
    }
    public function delete(string $path, array $query = [])
    {
        return Http::withBasicAuth($this->key, '')
            ->withHeaders([
                'api-version'       => '2024-11-11',
            ])
            ->acceptJson()
            ->delete($this->base . $path, $query)
            ->throw()
            ->json();
    }

    public function refund(array $json)
    {
        try{
            $res = Http::withBasicAuth($this->key, '')
            ->withHeaders([
                'api-version'       => '2024-11-11',
                'X-Idempotency-Key' => $json['reference_id'] ?? uniqid(),
            ])
            ->acceptJson()->asJson()
            ->post($this->base . '/refunds', $json)   // <-- endpoint benar
            ->throw()->json();
        }catch (RequestException $e) {
            Log::error('HTTP Request Failed:', [
                'status' => $e->response->status(),
                'body' => $e->response->body(), // Log the full response body
                'message' => $e->getMessage(),
            ]);
            // Handle the error as needed
        }
        return $res;
        
    }

    public function balance()
    {
        return Http::withBasicAuth($this->key, '')
            ->acceptJson()
            ->get($this->base . '/balance')
            ->throw()
            ->json();
    }
}

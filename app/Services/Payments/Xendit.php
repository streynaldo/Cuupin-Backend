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
        try {
            $response = Http::withBasicAuth($this->key, '')
                ->withHeaders([
                    'api-version'       => '2024-11-11',
                    'X-Idempotency-Key' => $json['reference_id'] ?? uniqid(),
                ])
                ->acceptJson()->asJson()
                ->post($this->base . '/refunds', $json);
    
            // jika ada error HTTP, ->throw() akan melempar RequestException
            return $response->throw()->json();
        } catch (RequestException $e) {
            $resp = $e->response;
    
            // Ambil body mentah penuh (string) dan juga parsed JSON kalau bisa
            $rawBody = $resp ? $resp->body() : null;
            $jsonBody = null;
            try {
                $jsonBody = $rawBody ? json_decode($rawBody, true) : null;
            } catch (\Throwable $_) {
                $jsonBody = null;
            }
    
            // Log body lengkap sebagai string (tidak dalam array) supaya tidak dipotong
            Log::error("[XENDIT][REFUND] HTTP error {$resp?->status()} for refund request", [
                'payload' => $json,
            ]);
            Log::error('[XENDIT][REFUND] FULL RESPONSE BODY: ' . ($rawBody ?? '<<no-body>>'));
    
            // Jika mau juga simpan parsed json untuk mesin analisa
            if ($jsonBody) {
                Log::error('[XENDIT][REFUND] PARSED JSON ERROR:', $jsonBody);
            }
    
            // lempar exception baru yang memuat status + body supaya caller juga dapat info lengkap
            $status = $resp ? $resp->status() : 'n/a';
            $message = "Xendit refund failed: HTTP {$status} - " . ($rawBody ?? $e->getMessage());
    
            throw new \RuntimeException($message, $status === 'n/a' ? 0 : $status);
        } catch (\Throwable $e) {
            Log::error('[XENDIT][REFUND] unexpected error: '.$e->getMessage(), ['payload' => $json]);
            throw $e; // rethrow
        }
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

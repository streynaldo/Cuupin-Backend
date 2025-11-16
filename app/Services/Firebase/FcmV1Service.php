<?php

namespace App\Services\Firebase;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class FcmV1Service
{
    protected string $credentialsPath;
    protected array $creds;
    protected string $projectId;
    protected string $url;
    protected Client $http;

    public function __construct()
    {
        $this->credentialsPath = base_path(env('FCM_CREDENTIALS', 'storage/app/firebase/cuupin-fcm.json'));
        if (! file_exists($this->credentialsPath)) {
            throw new \RuntimeException("FCM credentials file not found at {$this->credentialsPath}");
        }

        $this->creds = json_decode(file_get_contents($this->credentialsPath), true);
        if (!is_array($this->creds) || empty($this->creds['project_id'])) {
            throw new \RuntimeException("Invalid FCM credentials JSON.");
        }

        $this->projectId = $this->creds['project_id'];
        $this->url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $this->http = new Client();
    }

    /**
     * Get OAuth2 access token (cached)
     */
    protected function accessToken(): string
    {
        $cacheKey = 'fcm_v1_access_token_' . md5($this->credentialsPath);

        $cached = Cache::get($cacheKey);
        if ($cached && !empty($cached['access_token']) && !empty($cached['expires_at']) && $cached['expires_at'] > time() + 30) {
            return $cached['access_token'];
        }

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $creds = new ServiceAccountCredentials($scopes, $this->creds);
        $tokenInfo = $creds->fetchAuthToken();

        if (empty($tokenInfo['access_token'])) {
            throw new \RuntimeException('Failed to get access token for FCM v1');
        }

        $accessToken = $tokenInfo['access_token'];
        $expiresAt = time() + intval($tokenInfo['expires_in'] ?? 3600);

        Cache::put($cacheKey, [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ], intval($tokenInfo['expires_in'] ?? 3600) - 30);

        return $accessToken;
    }

    /**
     * sendToToken - send message to single FCM token
     *
     * @param string $token
     * @param array $notification ['title' => ..., 'body' => ...]
     * @param array $data (string values)
     * @param LoggerInterface|null $logger
     * @return array ['success'=>true,'response'=>...] OR ['error'=>..., 'status'=>..., 'body'=>...]
     */
    public function sendToToken(string $token, array $notification = [], array $data = [], LoggerInterface $logger = null): array
    {
        try {
            $accessToken = $this->accessToken();

            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $notification['title'] ?? '',
                        'body' => $notification['body'] ?? '',
                    ],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'HIGH'],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['content-available' => 1]]
                    ],
                ],
            ];

            $resp = $this->http->post($this->url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
                'timeout' => 8,
            ]);

            $body = json_decode((string)$resp->getBody(), true);
            return ['success' => true, 'response' => $body];
        } catch (ClientException $e) {
            // Guzzle ClientException gives access to response body
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $rawBody = $e->getResponse() ? (string)$e->getResponse()->getBody() : null;
            $body = null;
            try { $body = $rawBody ? json_decode($rawBody, true) : null; } catch (Throwable $_) { $body = null; }

            if ($logger) $logger->warning('FCM v1 client error', ['token'=>$token,'status'=>$status,'body'=>$body]);

            return ['error' => $e->getMessage(), 'status' => $status, 'body' => $body];
        } catch (Throwable $e) {
            if ($logger) $logger->error('FCM v1 send error: '.$e->getMessage(), ['token'=>$token]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * sendToTokens - loop send (simple). For heavy loads, dispatch jobs or use topics.
     * Returns array token => result
     */
    public function sendToTokens(array $tokens, array $notification = [], array $data = [], LoggerInterface $logger = null): array
    {
        $results = [];
        foreach ($tokens as $t) {
            $results[$t] = $this->sendToToken($t, $notification, $data, $logger);
        }
        return $results;
    }
}

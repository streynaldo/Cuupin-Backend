<?php

namespace App\Services\Firebase;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
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
        $this->http = new Client(['timeout' => 8]);
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
     * @param array $notification ['title' => ..., 'body' => ...] (if empty or visible=false -> silent)
     * @param array $data (will be cast to string)
     * @param LoggerInterface|null $logger
     * @param bool $visible true = visible alert, false = silent background
     * @return array ['success'=>true,'response'=>...] OR ['error'=>..., 'status'=>..., 'body'=>...]
     */
    public function sendToToken(string $token, array $notification = [], array $data = [], LoggerInterface $logger = null, bool $visible = true): array
    {
        try {
            $accessToken = $this->accessToken();

            // Normalize data values to strings (FCM data requires string values)
            $dataPayload = [];
            if (!empty($data)) {
                $dataPayload = array_map(function ($v) {
                    // prefer scalar string, else JSON encode
                    if (is_string($v) || is_numeric($v) || is_bool($v)) {
                        return (string)$v;
                    }
                    return json_encode($v);
                }, $data);
            }

            $message = [
                'message' => [
                    'token' => $token,
                    // Android: use 'high' to try to wake device
                    'android' => ['priority' => 'high'],
                ],
            ];

            if (!empty($dataPayload)) {
                $message['message']['data'] = $dataPayload;
            }

            // If visible === true and notification provided => build alert payload
            if ($visible && !empty($notification)) {
                $title = $notification['title'] ?? '';
                $body = $notification['body'] ?? '';

                // add top-level notification (FCM cross-platform)
                $message['message']['notification'] = [
                    'title' => $title,
                    'body'  => $body,
                ];

                // APNs payload for visible notification
                $message['message']['apns'] = [
                    'headers' => [
                        'apns-push-type' => 'alert',
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body'  => $body,
                            ],
                            'sound' => 'notification_sound.wav',
                            'content-available' => 0,
                        ],
                    ],
                ];
            } else {
                // Silent/background push for iOS: DO NOT include notification/alert
                $message['message']['apns'] = [
                    'headers' => [
                        'apns-push-type' => 'background',
                        'apns-priority' => '5',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                        ],
                    ],
                ];
            }

            $resp = $this->http->post($this->url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $message,
            ]);

            $body = json_decode((string)$resp->getBody(), true);
            $logger?->info('fcm sent', ['token' => $token, 'visible' => $visible, 'resp' => $body]);

            return ['success' => true, 'response' => $body];
        } catch (ClientException $e) {
            $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $rawBody = $e->getResponse() ? (string)$e->getResponse()->getBody() : null;
            $body = null;
            try { $body = $rawBody ? json_decode($rawBody, true) : null; } catch (Throwable $_) { $body = null; }

            $logger?->warning('FCM v1 client error', ['token' => $token, 'status' => $status, 'body' => $body]);

            return ['error' => $e->getMessage(), 'status' => $status, 'body' => $body];
        } catch (Throwable $e) {
            $logger?->error('FCM v1 send error: '.$e->getMessage(), ['token' => $token]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * sendToTokens - loop send (simple). For heavy loads, dispatch jobs or use topics.
     * Returns array token => result
     *
     * @param array $tokens
     * @param array $notification
     * @param array $data
     * @param LoggerInterface|null $logger
     * @param bool $visible
     * @return array
     */
    public function sendToTokens(array $tokens, array $notification = [], array $data = [], LoggerInterface $logger = null, bool $visible = true): array
    {
        $results = [];
        foreach ($tokens as $t) {
            $results[$t] = $this->sendToToken($t, $notification, $data, $logger, $visible);
        }
        return $results;
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class MercureService
{
    public function canPublish(): bool
    {
        return WAKE_MERCURE_PUBLISH_URL !== ''
            && WAKE_MERCURE_PUBLISHER_JWT_KEY !== ''
            && WAKE_MERCURE_TOPIC_BASE !== '';
    }

    public function getHubUrl(): string
    {
        return WAKE_MERCURE_HUB_URL;
    }

    public function getDevicesTopic(): string
    {
        return WAKE_MERCURE_TOPIC_BASE . '/devices';
    }

    public function getDeviceTopic(int $deviceId): string
    {
        return $this->getDevicesTopic() . '/' . $deviceId;
    }

    public function publish(string|array $topics, array $payload, string $eventType, ?string $eventId = null): bool
    {
        if (!$this->canPublish()) {
            return false;
        }

        $topicList = array_values(array_filter(array_map('strval', is_array($topics) ? $topics : [$topics])));
        if ($topicList === []) {
            return false;
        }

        $postFields = [
            'topic' => $topicList,
            'data' => $this->encodeJson($payload),
            'type' => $eventType,
        ];

        if (WAKE_MERCURE_EVENTS_PRIVATE) {
            $postFields['private'] = 'on';
        }

        if ($eventId !== null && $eventId !== '') {
            $postFields['id'] = $eventId;
        }

        $token = $this->createJwt(
            [
                'mercure' => [
                    'publish' => ['*'],
                ],
            ],
            WAKE_MERCURE_PUBLISHER_JWT_KEY,
            300
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'ignore_errors' => true,
                'timeout' => WAKE_MERCURE_PUBLISH_TIMEOUT_SECONDS,
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/x-www-form-urlencoded',
                ]),
                'content' => $this->buildPostContent($postFields),
            ],
        ]);

        $result = @file_get_contents(WAKE_MERCURE_PUBLISH_URL, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        $statusCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? (int)$matches[1] : 0;

        if ($result === false || $statusCode < 200 || $statusCode >= 300) {
            error_log('ShinedeWake Mercure publish failed for event ' . $eventType);
            return false;
        }

        return true;
    }

    private function buildPostContent(array $fields): string
    {
        $pairs = [];

        foreach ($fields as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $entry) {
                $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$entry);
            }
        }

        return implode('&', $pairs);
    }

    private function createJwt(array $claims, string $secret, int $ttlSeconds): string
    {
        $issuedAt = time();
        $payload = $claims + [
            'iat' => $issuedAt,
            'exp' => $issuedAt + max(1, $ttlSeconds),
        ];

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode($this->encodeJson($header)),
            $this->base64UrlEncode($this->encodeJson($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

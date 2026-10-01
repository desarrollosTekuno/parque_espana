<?php

namespace App\Services\Notifications;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService {

    public function __construct(private readonly Messaging $messaging) {
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): void {
        $data = collect($data)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))
            ->toArray();

        $message = CloudMessage::new()
            ->toToken($token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withApnsConfig($this->buildApnsConfig($title, $body, $data));

        $this->messaging->send($message);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array {
        $tokens = array_values(array_filter($tokens, fn ($token) => is_string($token) && trim($token) !== ''));

        if ($tokens === []) {
            return [
                'success_count' => 0,
                'failure_count' => 0,
                'invalid_tokens' => [],
            ];
        }

        $data = collect($data)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))
            ->toArray();

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withApnsConfig($this->buildApnsConfig($title, $body, $data));

        $report = $this->messaging->sendMulticast($message, $tokens);

        $invalidTokens = [];

        foreach ($report->failures()->getItems() as $failure) {
            $error = $failure->error();

            if ($error instanceof MessagingException && $error->errors() !== []) {
                $firstError = $error->errors()[0] ?? [];

                if (($firstError['errorCode'] ?? null) === 'UNREGISTERED') {
                    $invalidTokens[] = $failure->target()->value();
                }
            }
        }

        return [
            'success_count' => $report->successes()->count(),
            'failure_count' => $report->failures()->count(),
            'invalid_tokens' => array_values(array_unique($invalidTokens)),
        ];
    }

    public function validateToken(string $token): bool {
        $this->messaging->validateRegistrationTokens([$token]);

        return true;
    }

    /**
     * Config explícita de APNs — sin esto, el push se acepta sin error pero
     * nunca llega a iOS (confirmado en vivo contra un iPhone real: Firebase
     * documenta que copia el `notification` genérico a `aps.alert`, pero en
     * la práctica no sucede). También se duplica `$data` como campos
     * sueltos del payload de APNs, porque en iOS `RemoteMessage.data` se
     * arma del payload real de APNs, no del campo `data` genérico del
     * mensaje FCM — mismo criterio que FirebaseService::buildApnsConfig.
     */
    private function buildApnsConfig(string $title, string $body, array $data): ApnsConfig {
        $apnsConfig = ApnsConfig::new()
            ->withHeader('apns-push-type', 'alert')
            ->withApsField('alert', ['title' => $title, 'body' => $body])
            ->withDefaultSound()
            ->withImmediatePriority();

        foreach ($data as $key => $value) {
            $apnsConfig = $apnsConfig->withDataField($key, (string) $value);
        }

        return $apnsConfig;
    }

}

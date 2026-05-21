<?php

namespace App\Services\Notifications;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
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
            ->withData($data);

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

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

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

}

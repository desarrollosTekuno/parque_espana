<?php

namespace App\Services\Email;

use App\Models\Notifications\EmailConfig;
use App\Models\Notifications\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailService {

    public function send(int $entityId, string|array $to, Mailable $mailable, ?int $notificationId = null): void {
        $emailConfig = $this->configEmail($entityId);

        if (!$emailConfig) {
            throw new \RuntimeException('No hay una configuracion SMTP activa para este club.');
        }

        $logData = [
            'entity_id' => $entityId,
            'email_config_id' => $emailConfig->id,
            'notification_id' => $notificationId,
            'to_email' => $this->normalize($to),
            'subject' => $this->resolveSubject($mailable),
        ];

        try {
            $mailer = Mail::build([
                'transport' => 'smtp',
                'host' => $emailConfig->host,
                'port' => (int) $emailConfig->port,
                'encryption' => $emailConfig->encryption,
                'username' => $emailConfig->username,
                'password' => $emailConfig->password,
            ]);

            $mailer->alwaysFrom($emailConfig->from_address, $emailConfig->from_name);
            $mailer->to($to)->send($mailable);

            EmailLog::create($logData + [
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            EmailLog::create($logData + [
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 65535),
            ]);

            throw $e;
        }
    }

    private function configEmail(int $entityId): ?EmailConfig {
        $emailConfig = EmailConfig::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->first();

        return $emailConfig;
    }

    private function normalize(string|array $to): string {
        if (is_string($to)) {
            return $to;
        }

        $emails = [];

        foreach ($to as $recipient) {
            if (is_string($recipient)) {
                $emails[] = $recipient;
                continue;
            }

            if (is_array($recipient) && isset($recipient['email']) && is_string($recipient['email'])) {
                $emails[] = $recipient['email'];
            }
        }

        return implode(',', $emails);
    }

    private function resolveSubject(Mailable $mailable): string {
        $subject = data_get($mailable, 'subject');

        if (is_string($subject) && $subject !== '') {
            return mb_substr($subject, 0, 255);
        }

        return mb_substr(class_basename($mailable), 0, 255);
    }
}

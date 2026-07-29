<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendTestEmailRequest;
use App\Mail\TestEmailMailable;
use App\Models\Administrator\Club;
use App\Models\Notifications\EmailConfig;
use App\Services\Email\MailService;
use Throwable;

class EmailTestController extends Controller
{
    public function send(SendTestEmailRequest $request, MailService $mailService)
    {
        try {
            $data = $request->validated();

            if (!Club::query()->whereKey($data['entity_id'])->exists()) {
                return $this->unprocessable('La entidad indicada no existe.');
            }

            $subject      = $data['subject'] ?? 'Correo de prueba SMTP';
            $message      = $data['message'] ?? 'Si llegué! Este es un correo de prueba enviado con configuración SMTP dinámica.';
            $templateName = EmailConfig::query()
                ->where('entity_id', (int) $data['entity_id'])
                ->where('is_active', true)
                ->value('template_name') ?? 'emails.email_template';

            $mailService->send(
                entityId: (int) $data['entity_id'],
                to:       $data['to'],
                mailable: new TestEmailMailable($subject, $message, (int) $data['entity_id'], $templateName),
            );

            return $this->success('Correo de prueba enviado correctamente.');
        } catch (Throwable $e) {
            report($e);
            return $this->serverError('No se pudo enviar el correo de prueba.');
        }
    }
}

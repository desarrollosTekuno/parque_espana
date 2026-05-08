<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendTestEmailRequest;
use App\Mail\TestEmailMailable;
use App\Models\Administrator\Club;
use App\Services\Email\MailService;
use Throwable;

class EmailTestController extends Controller
{
    public function send(SendTestEmailRequest $request, MailService $mailService)
    {
        try {
            $data = $request->validated();

            if (! Club::query()->whereKey($data['entity_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La entidad indicada no existe',
                ], 422);
            }

            $subject = $data['subject'] ?? 'Correo de prueba SMTP';
            $message = $data['message'] ?? 'Este es un correo de prueba enviado con configuracion SMTP dinamica.';

            $mailService->send(
                entityId: (int) $data['entity_id'],
                to: $data['to'],
                mailable: new TestEmailMailable($subject, $message, (int) $data['entity_id'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado correctamente',
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el correo de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

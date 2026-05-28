<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Memberships\Membership;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseService
{
    public function __construct(private Messaging $messaging) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Envío individual
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía una notificación push a todos los dispositivos activos de UN usuario.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->active()
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Envío masivo por club
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía una notificación a TODOS los socios de un club via FCM Topic.
     *
     * Un solo mensaje llega a todos los dispositivos suscritos al topic,
     * sin importar cuántos sean. Ideal para avisos generales del club
     * (cierres, eventos, mantenimientos, etc.)
     *
     * Requiere que los tokens estén suscritos al topic del club.
     * Esto ocurre automáticamente en DeviceTokenController::store().
     */
    public function sendToClub(int $clubId, string $title, string $body, array $data = []): void
    {
        $this->sendToTopic("club_{$clubId}", $title, $body, $data);
    }

    /**
     * Envía una notificación a un FCM Topic.
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): void
    {
        $stringData = collect($data)->map(fn($v) => (string) $v)->toArray();

        $message = CloudMessage::new()
            ->toTopic($topic)
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData);

        try {
            $this->messaging->send($message);

            Log::info('Notificación enviada a topic FCM', [
                'topic' => $topic,
                'title' => $title,
            ]);
        } catch (MessagingException $e) {
            Log::error('Error enviando notificación a topic FCM', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía una notificación a múltiples usuarios específicos (lista de user IDs).
     *
     * Carga todos sus tokens activos y los envía en lotes de 500
     * (límite de FCM para multicast).
     *
     * Ideal para segmentaciones: "titulares morosos", "socios tipo X", etc.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        if (empty($userIds)) {
            return;
        }

        DeviceToken::whereIn('user_id', $userIds)
            ->active()
            ->select('token')
            ->chunkById(500, function ($chunk) use ($title, $body, $data) {
                $tokens = $chunk->pluck('token')->toArray();
                $this->sendToTokens($tokens, $title, $body, $data);
            });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gestión de Topics
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Suscribe un token FCM a los topics de los clubs del usuario.
     * Llamar al registrar un nuevo device token.
     */
    public function subscribeTokenToUserClubs(string $token, int $userId): void
    {
        $clubIds = $this->getActiveClubIdsForUser($userId);

        foreach ($clubIds as $clubId) {
            $this->subscribeToTopic($token, "club_{$clubId}");
        }
    }

    /**
     * Desuscribe un token FCM de todos los topics de los clubs del usuario.
     * Llamar al desactivar un device token (logout).
     */
    public function unsubscribeTokenFromUserClubs(string $token, int $userId): void
    {
        $clubIds = $this->getActiveClubIdsForUser($userId);

        foreach ($clubIds as $clubId) {
            $this->unsubscribeFromTopic($token, "club_{$clubId}");
        }
    }

    /**
     * Suscribe un token a un topic específico.
     */
    public function subscribeToTopic(string $token, string $topic): void
    {
        try {
            $this->messaging->subscribeToTopic($topic, [$token]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo suscribir token a topic FCM', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Desuscribe un token de un topic específico.
     */
    public function unsubscribeFromTopic(string $token, string $topic): void
    {
        try {
            $this->messaging->unsubscribeFromTopic($topic, [$token]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo desuscribir token de topic FCM', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía una notificación a una lista de tokens FCM (máx 500 por llamada).
     * Desactiva automáticamente los tokens que FCM reporte como inválidos.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) {
            return;
        }

        $stringData = collect($data)->map(fn($v) => (string) $v)->toArray();

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $invalidTokens = collect($report->failures()->getItems())
                    ->map(fn($failure) => $failure->target()->value())
                    ->filter()
                    ->toArray();

                if (!empty($invalidTokens)) {
                    $this->deactivateTokens($invalidTokens);

                    Log::info('Tokens FCM inválidos desactivados', [
                        'count' => count($invalidTokens),
                    ]);
                }
            }
        } catch (MessagingException $e) {
            Log::error('Error enviando notificación FCM (multicast)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtiene los club_ids activos de un usuario a través de sus membresías.
     */
    private function getActiveClubIdsForUser(int $userId): array
    {
        return Membership::query()
            ->join('memberships.accounts', 'memberships.memberships.membership_account_id', '=', 'memberships.accounts.id')
            ->join('memberships.account_members', 'memberships.accounts.id', '=', 'memberships.account_members.membership_account_id')
            ->join('members.members', 'memberships.account_members.member_id', '=', 'members.members.id')
            ->where('members.members.user_id', $userId)
            ->where('memberships.memberships.status', 'active')
            ->pluck('memberships.memberships.club_id')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Marca tokens como inactivos (FCM los reportó como inválidos o expirados).
     */
    private function deactivateTokens(array $tokens): void
    {
        DeviceToken::whereIn('token', $tokens)->update(['is_active' => false]);
    }
}

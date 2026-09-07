<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\AdminClub\SystemVariable;
use App\Models\Members\Member;
use App\Services\Family\FamilyReservationGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckInController extends Controller
{
    private const MAX_DISTANCE_METERS = 5;

    /** Tolerancia (minutos) por defecto si el club no tiene configurada 'tolerancia_asistencia'. */
    private const DEFAULT_TOLERANCE_MINUTES = 10;

    /**
     * Verifica la geocerca del recurso y, de estar dentro del límite, devuelve a nombre
     * de quién se puede registrar la asistencia: el propio socio y, si es titular primario,
     * sus integrantes menores de 15 años que tengan una reservación activa hoy en este
     * recurso (dentro de la tolerancia).
     */
    public function options(Request $request, AmenityResource $resource)
    {
        try {
            $request->validate([
                'latitude'  => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $distanceError = $this->checkWithinRange(
                $resource,
                (float) $request->input('latitude'),
                (float) $request->input('longitude')
            );

            if ($distanceError) {
                return $distanceError;
            }

            $holder = Member::where('user_id', $request->user()->id)->first();

            if (!$holder) {
                return $this->notFound('No se encontró un socio asociado a este usuario.');
            }

            $resource->loadMissing('amenity');
            $toleranceMinutes = $this->toleranceMinutes($resource);

            $candidateIds = array_unique(array_merge(
                [$holder->id],
                (new FamilyReservationGuard())->familyMinorIds($holder, $resource->amenity->club_id)
            ));

            $members = collect($candidateIds)
                ->map(fn (int $memberId) => [
                    'member'      => $memberId === $holder->id ? $holder : Member::find($memberId),
                    'reservation' => $this->findReservation($memberId, $resource, $toleranceMinutes),
                ])
                ->filter(fn (array $entry) => $entry['member'] && $entry['reservation'])
                ->map(fn (array $entry) => [
                    'member_id'      => $entry['member']->id,
                    'full_name'      => $entry['member']->full_name,
                    'is_self'        => $entry['member']->id === $holder->id,
                    'reservation_id' => $entry['reservation']->id,
                    'start_time'     => $entry['reservation']->start_datetime,
                    'end_time'       => $entry['reservation']->end_datetime,
                ])
                ->values();

            return $this->ok(['members' => $members]);
        } catch (\Throwable $e) {
            Log::error('CheckIn options error', [
                'resource_id'  => $resource->id,
                'user_id'      => $request->user()?->id,
                'messageError' => $e->getMessage(),
            ]);

            return $this->serverError('No se pudo verificar la disponibilidad de asistencia.');
        }
    }

    public function store(Request $request, AmenityResource $resource)
    {
        try {
            $request->validate([
                'latitude'  => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'member_id' => 'required|integer',
            ]);

            $distanceError = $this->checkWithinRange(
                $resource,
                (float) $request->input('latitude'),
                (float) $request->input('longitude')
            );

            if ($distanceError) {
                return $distanceError;
            }

            $holder = Member::where('user_id', $request->user()->id)->first();

            if (!$holder) {
                return $this->notFound('No se encontró un socio asociado a este usuario.');
            }

            $resource->loadMissing('amenity');

            try {
                $member = (new FamilyReservationGuard())->resolveReservingMember(
                    $holder,
                    $request->integer('member_id'),
                    $resource->amenity->club_id
                );
            } catch (ReservationException $e) {
                return $this->unprocessable($e->getMessage());
            }

            $toleranceMinutes = $this->toleranceMinutes($resource);
            $reservation = $this->findReservation($member->id, $resource, $toleranceMinutes);

            if (!$reservation) {
                return $this->notFound(
                    "No se encontró una reservación activa para hoy en este recurso, o ya pasó el tiempo de tolerancia ({$toleranceMinutes} minutos) para registrar tu asistencia."
                );
            }

            $reservation->update([
                'reservation_status_id' => ReservationStatus::ASISTENCIA,
            ]);

            return $this->success('¡Asistencia registrada correctamente!', [
                'checked_in_at'  => now()->toIso8601String(),
                'resource'       => $resource->name,
                'amenity'        => $resource->amenity->name,
                'member'         => $member->full_name,
                'reservation_id' => $reservation->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CheckIn store error', [
                'resource_id'  => $resource->id,
                'user_id'      => $request->user()?->id,
                'messageError' => $e->getMessage(),
            ]);

            return $this->serverError('No se pudo registrar la asistencia.');
        }
    }

    /**
     * Busca la reservación activa de hoy del miembro para este recurso que aún esté
     * dentro de la tolerancia (minutos después de su hora de inicio).
     */
    private function findReservation(int $memberId, AmenityResource $resource, int $toleranceMinutes): ?Reservation
    {
        $now = Carbon::now();

        return Reservation::where('member_id', $memberId)
            ->where('amenity_resource_id', $resource->id)
            ->where('reservation_status_id', ReservationStatus::ACTIVA)
            ->whereDate('start_datetime', today())
            ->get()
            ->first(fn (Reservation $r) => $now->lte($r->start_datetime->copy()->addMinutes($toleranceMinutes)));
    }

    private function toleranceMinutes(AmenityResource $resource): int
    {
        return (int) (SystemVariable::where('club_id', $resource->amenity->club_id)
            ->where('name', 'tolerancia_asistencia')
            ->value('value') ?? self::DEFAULT_TOLERANCE_MINUTES);
    }

    /**
     * Valida que el usuario esté dentro de la geocerca del recurso. Devuelve un JsonResponse
     * de error si el recurso no tiene ubicaciones activas o si está fuera del rango permitido;
     * devuelve null cuando la validación pasa.
     */
    private function checkWithinRange(AmenityResource $resource, float $latitude, float $longitude)
    {
        $activeLocations = $resource->locations()->where('active', true)->get();

        if ($activeLocations->isEmpty()) {
            return $this->unprocessable('Este recurso no tiene ubicaciones activas configuradas.');
        }

        $minDistance = $activeLocations->min(function ($location) use ($latitude, $longitude) {
            return $this->haversine($latitude, $longitude, (float) $location->latitude, (float) $location->longitude);
        });

        if ($minDistance > self::MAX_DISTANCE_METERS) {
            return response()->json([
                'message'  => 'No estás dentro del área del recurso. Acércate e intenta de nuevo.',
                'distance' => round($minDistance),
            ], 422);
        }

        return null;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

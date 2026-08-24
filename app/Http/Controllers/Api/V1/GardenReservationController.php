<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGardenReservationRequest;
use App\Http\Resources\GardenReservationResource;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\Members\Member;
use App\Services\Family\FamilyReservationGuard;
use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Rules\ReservationsPerDayRule;
use App\Services\Reservation\Validators\CreateReservationValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GardenReservationController extends Controller
{
    /**
     * Asignación por cercanía, solo informativa para armar el mapa en el front.
     * No restringe qué asador puede elegirse con qué jardín.
     */
    protected const NEARBY_GRILLS = [
        1 => [1],
        2 => [2, 3],
        3 => [4, 5],
        4 => [6, 7],
    ];

    /**
     * Catálogo de jardines y asadores (amenidad "Jardines"), para el mapa de selección.
     */
    public function catalog(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'club_id' => ['required', new \App\Rules\ExistsInSchema('clubs', 'clubs', 'id')],
            'date'    => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $date = $validated['date'] ?? Carbon::now()->format('Y-m-d');

            $resources = AmenityResource::whereHas('amenity', fn ($query) => $query
                ->where('name', 'Jardines')
                ->where('club_id', $validated['club_id']))
                ->orderBy('name')
                ->get();

            $reservedResourceIds = Reservation::whereIn('amenity_resource_id', $resources->pluck('id'))
                ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
                ->where('reservation_date', $date)
                ->pluck('amenity_resource_id')
                ->unique();

            $gardens = $resources->filter(fn (AmenityResource $r) => str_starts_with($r->name, 'Jardín'))
                ->map(fn (AmenityResource $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'nearby_grills' => self::NEARBY_GRILLS[$this->resourceNumber($r->name)] ?? [],
                    'is_available' => !$reservedResourceIds->contains($r->id),
                ])
                ->values();

            $grills = $resources->filter(fn (AmenityResource $r) => str_starts_with($r->name, 'Asador'))
                ->map(fn (AmenityResource $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'is_available' => !$reservedResourceIds->contains($r->id),
                ])
                ->values();

            return $this->ok(['date' => $date, 'gardens' => $gardens, 'grills' => $grills]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener el catálogo de jardines.');
        }
    }

    public function store(StoreGardenReservationRequest $request)
    {
        try {
            $validated = $request->validated();

            $holder = Member::where('user_id', $request->user()->id)->first();

            if (!$holder) {
                return $this->notFound('No se encontró un socio asociado a este usuario.');
            }

            $member = (new FamilyReservationGuard())->resolveReservingMember(
                $holder,
                isset($validated['member_id']) ? (int) $validated['member_id'] : null,
                (int) $validated['club_id'],
            );

            $garden = null;
            $grill = null;

            if (!empty($validated['garden_resource_id'])) {
                $garden = $this->resolveGardenResource($validated['garden_resource_id'], $validated['club_id'], 'Jardín');
                if ($garden instanceof \Illuminate\Http\JsonResponse) {
                    return $garden;
                }
            }

            if (!empty($validated['grill_resource_id'])) {
                $grill = $this->resolveGardenResource($validated['grill_resource_id'], $validated['club_id'], 'Asador');
                if ($grill instanceof \Illuminate\Http\JsonResponse) {
                    return $grill;
                }
            }

            $startDatetime = Carbon::parse($validated['date'])->startOfDay();
            $endDatetime = Carbon::parse($validated['date'])->endOfDay();

            // Se valida una sola vez por solicitud: jardín + asador enlazados cuentan como
            // una sola reservación del día, no como dos (ver CreateReservationValidator).
            (new ReservationsPerDayRule())->validate(new ReservationContext(
                data: [
                    'start_datetime' => $startDatetime->format('Y-m-d H:i'),
                    'club_id'        => $validated['club_id'],
                ],
                member: $member,
            ));

            $reservations = DB::transaction(function () use (
                $validated, $member, $request, $garden, $grill, $startDatetime, $endDatetime
            ) {
                $gardenReservation = $garden
                    ? $this->createReservation($validated, $member, $request, $garden, $startDatetime, $endDatetime)
                    : null;

                $grillReservation = $grill
                    ? $this->createReservation($validated, $member, $request, $grill, $startDatetime, $endDatetime, requiresTent: false)
                    : null;

                if ($gardenReservation && $grillReservation) {
                    $gardenReservation->update(['linked_reservation_id' => $grillReservation->id]);
                    $grillReservation->update(['linked_reservation_id' => $gardenReservation->id]);
                }

                return array_filter([$gardenReservation, $grillReservation]);
            });

            foreach ($reservations as $reservation) {
                $reservation->load(['amenity', 'amenityResource', 'status', 'club']);
            }

            return $this->created(
                'Reservación de jardín creada correctamente.',
                GardenReservationResource::collection(collect($reservations)->values()),
            );
        } catch (ReservationException $e) {
            return $this->unprocessable($e->getMessage());
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al crear la reservación del jardín.');
        }
    }

    protected function resolveGardenResource(int $resourceId, int $clubId, string $expectedPrefix)
    {
        $resource = AmenityResource::with('amenity')->where('id', $resourceId)->first();
        $amenity = $resource?->amenity;

        if (!$amenity || $amenity->name !== 'Jardines' || $amenity->club_id != $clubId) {
            return $this->unprocessable('El recurso seleccionado no pertenece a los jardines de este club.');
        }

        if (!str_starts_with($resource->name, $expectedPrefix)) {
            return $this->unprocessable("El recurso seleccionado no es un {$expectedPrefix}.");
        }

        return $resource;
    }

    protected function createReservation(
        array $validated,
        Member $member,
        $request,
        AmenityResource $resource,
        Carbon $startDatetime,
        Carbon $endDatetime,
        ?bool $requiresTent = null,
    ): Reservation {
        $context = new ReservationContext(
            data: [
                'start_datetime'      => $startDatetime->format('Y-m-d H:i'),
                'end_datetime'        => $endDatetime->format('Y-m-d H:i'),
                'club_id'             => $validated['club_id'],
                'amenity_resource_id' => $resource->id,
            ],
            amenity:         $resource->amenity,
            amenityResource: $resource,
            member:          $member,
            user:            $request->user(),
        );
        (new CreateReservationValidator(includeDailyLimit: false))->validate($context);

        return Reservation::create([
            'start_datetime'        => $startDatetime,
            'end_datetime'          => $endDatetime,
            'reservation_status_id' => ReservationStatus::ACTIVA,
            'club_id'               => $validated['club_id'],
            'amenity_id'            => $resource->amenity_id,
            'amenity_resource_id'   => $resource->id,
            'member_id'             => $member->id,
            'reservation_date'      => $startDatetime->format('Y-m-d'),
            'requires_tent'         => $requiresTent ?? (bool) ($validated['requires_tent'] ?? false),
            'tables_count'          => $validated['tables_count'],
            'chairs_count'          => $validated['chairs_count'],
            'notes'                 => $validated['notes'] ?? null,
        ]);
    }

    protected function resourceNumber(?string $resourceName): int
    {
        preg_match('/(\d+)/', (string) $resourceName, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }
}

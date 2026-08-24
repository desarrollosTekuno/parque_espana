<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use App\Models\Members\Member;
use App\Rules\ExistsInSchema;
use App\Services\Family\FamilyReservationGuard;
use App\Services\Reservation\Context\ReservationContext;
use App\Services\Reservation\Validators\CancelReservationValidator;
use App\Services\Reservation\Validators\CreateReservationValidator;
use App\Support\SpanishDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_datetime'      => 'required|date_format:Y-m-d H:i',
                'end_datetime'        => 'required|date_format:Y-m-d H:i|after:start_datetime',
                'club_id'             => ['required', new ExistsInSchema('clubs', 'clubs', 'id')],
                'amenity_resource_id' => ['required', new ExistsInSchema('amenities', 'resources', 'id')],
                'is_class'            => ['nullable', 'boolean'],
                'coach_id'            => ['nullable', new ExistsInSchema('classes', 'coaches', 'id')],
                'member_id'           => ['nullable', new ExistsInSchema('members', 'members', 'id')],
            ]);

            $holder = Member::where('user_id', $request->user()->id)->first();

            if (!$holder) {
                return $this->notFound('No se encontró un socio asociado a este usuario.');
            }

            $member = (new FamilyReservationGuard())->resolveReservingMember(
                $holder,
                isset($validated['member_id']) ? (int) $validated['member_id'] : null,
                (int) $validated['club_id'],
            );

            $amenityResource = AmenityResource::with('amenity')
                ->where('id', $validated['amenity_resource_id'])->first();
            $amenity = $amenityResource->amenity;

            $context = new ReservationContext(
                data:            $validated,
                amenity:         $amenity,
                amenityResource: $amenityResource,
                member:          $member,
                user:            $request->user(),
            );
            (new CreateReservationValidator())->validate($context);

            $reservation = Reservation::create([
                'start_datetime'        => $validated['start_datetime'],
                'end_datetime'          => $validated['end_datetime'],
                'reservation_status_id' => ReservationStatus::ACTIVA,
                'club_id'               => $validated['club_id'],
                'amenity_id'            => $amenity->id,
                'amenity_resource_id'   => $validated['amenity_resource_id'],
                'member_id'             => $member->id,
                'reservation_date'      => $amenity->reservation_type === 'daily'
                    ? Carbon::parse($validated['start_datetime'])->format('Y-m-d')
                    : null,
                'is_class'              => (bool) ($validated['is_class'] ?? false),
                'coach_id'              => $validated['coach_id'] ?? null,
            ]);

            $reservation->load(['amenity', 'amenityResource', 'status', 'coach']);

            return $this->created(
                'Reservación creada correctamente.',
                new ReservationResource($reservation),
            );
        } catch (ReservationException $e) {
            return $this->unprocessable($e->getMessage());
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al crear la reservación.');
        }
    }

    public function update(Request $request, Reservation $reservation)
    {
        try {
            $context = new ReservationContext(
                user:        $request->user(),
                reservation: $reservation,
            );
            (new CancelReservationValidator())->validate($context);

            DB::transaction(function () use ($reservation) {
                $reservation->update([
                    'cancelled_at'          => now(),
                    'reservation_status_id' => ReservationStatus::CANCELADA,
                ]);

                if ($reservation->linked_reservation_id) {
                    Reservation::where('id', $reservation->linked_reservation_id)
                        ->where('reservation_status_id', '!=', ReservationStatus::CANCELADA)
                        ->update([
                            'cancelled_at'          => now(),
                            'reservation_status_id' => ReservationStatus::CANCELADA,
                        ]);
                }
            });

            return $this->success('Reservación cancelada correctamente.');
        } catch (ReservationException $e) {
            return $this->unprocessable($e->getMessage());
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al cancelar la reservación.');
        }
    }

    public function destroy(Reservation $reservation)
    {
        try {
            $reservation->delete();

            return $this->success('Reservación eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al eliminar la reservación.');
        }
    }

    public function myReservations(Request $request)
    {
        try {
            $member = Member::where('user_id', $request->user()->id)->first();

            $validated = $request->validate([
                'club_id'    => ['nullable', new ExistsInSchema('clubs', 'clubs', 'id')],
                'status_id'  => ['nullable', 'string'],
                'amenity_id' => ['nullable', new ExistsInSchema('amenities', 'amenities', 'id')],
                'date_from'  => ['nullable', 'date_format:Y-m-d'],
                'date_to'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
                'sort'       => ['nullable', 'in:asc,desc'],
                'per_page'   => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $perPage = (int) ($validated['per_page'] ?? 15);

            if (!$member) {
                return $this->ok([
                    'groups'     => [],
                    'pagination' => [
                        'current_page'   => 1,
                        'per_page'       => $perPage,
                        'total'          => 0,
                        'last_page'      => 1,
                        'has_more_pages' => false,
                    ],
                ]);
            }

            $query = Reservation::with(['amenity', 'amenityResource', 'status', 'club', 'coach'])
                ->where('member_id', $member->id);

            if (!empty($validated['club_id'])) {
                $query->where('club_id', $validated['club_id']);
            }

            if (!empty($validated['status_id'])) {
                $statusIds = array_filter(explode(',', $validated['status_id']), 'is_numeric');
                if ($statusIds) {
                    $query->whereIn('reservation_status_id', $statusIds);
                }
            }

            if (!empty($validated['amenity_id'])) {
                $query->where('amenity_id', $validated['amenity_id']);
            }

            if (!empty($validated['date_from'])) {
                $query->whereDate('start_datetime', '>=', $validated['date_from']);
            }

            if (!empty($validated['date_to'])) {
                $query->whereDate('start_datetime', '<=', $validated['date_to']);
            }

            $sort      = $validated['sort'] ?? 'asc';
            $paginator = $query->orderBy('start_datetime', $sort)->paginate($perPage);

            $today   = Carbon::now()->startOfDay();
            $grouped = collect($paginator->items())
                ->groupBy(fn (Reservation $r) => $r->start_datetime->format('Y-m-d'))
                ->map(function ($items, $date) use ($today) {
                    $date = Carbon::parse($date);
                    return [
                        'label' => SpanishDate::relativeFullLabel($date, $today),
                        'date'  => $date->format('Y-m-d'),
                        'items' => ReservationResource::collection($items)->values(),
                    ];
                })
                ->values();

            return $this->ok([
                'groups'     => $grouped,
                'pagination' => [
                    'current_page'   => $paginator->currentPage(),
                    'per_page'       => $paginator->perPage(),
                    'total'          => $paginator->total(),
                    'last_page'      => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->serverError('Ocurrió un error al obtener las reservaciones.');
        }
    }
}

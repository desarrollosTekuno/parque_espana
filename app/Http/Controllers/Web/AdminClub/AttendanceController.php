<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:attendance.index')->only('index');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $driver = DB::getDriverName();
        $prefix = 'attendance';

        $filterDate = $request->input("{$prefix}_filter_date") ?: now()->format('Y-m-d');
        $filterAmenity = $request->input("{$prefix}_filter_amenity");
        $filterResource = $request->input("{$prefix}_filter_resource");
        $filterStatus = $request->input("{$prefix}_filter_status");

        $query = Reservation::with(['member', 'amenity', 'amenityResource', 'status', 'coach'])
            ->where('club_id', $clubId)
            ->whereDate('start_datetime', $filterDate);

        if ($search = $request->input("{$prefix}_search")) {
            $query->whereHas('member', function ($q) use ($driver, $search) {
                $q->where('first_name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('last_name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhere('second_last_name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }

        if ($filterAmenity) {
            $query->where('amenity_id', $filterAmenity);
        }

        if ($filterResource) {
            $query->where('amenity_resource_id', $filterResource);
        }

        if ($filterStatus) {
            $query->where('reservation_status_id', $filterStatus);
        } else {
            $query->whereIn('reservation_status_id', [
                ReservationStatus::ACTIVA,
                ReservationStatus::ASISTENCIA,
                ReservationStatus::INASISTENCIA,
            ]);
        }

        $sort = $request->input("{$prefix}_sort", 'start_datetime');
        $order = $request->input("{$prefix}_order", 'asc');

        $query->orderBy($sort, $order);

        $reservations = $query->paginate(
            $request->input("{$prefix}_per_page", 25),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        $baseQuery = Reservation::where('club_id', $clubId)->whereDate('start_datetime', $filterDate);
        $summary = [
            'activa' => (clone $baseQuery)->where('reservation_status_id', ReservationStatus::ACTIVA)->count(),
            'asistencia' => (clone $baseQuery)->where('reservation_status_id', ReservationStatus::ASISTENCIA)->count(),
            'inasistencia' => (clone $baseQuery)->where('reservation_status_id', ReservationStatus::INASISTENCIA)->count(),
        ];

        $amenities = Amenity::where('club_id', $clubId)
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $resources = AmenityResource::where('is_active', true)
            ->select('id', 'name', 'amenity_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('AdminClubs/Attendance/Index', [
            'attendance' => $reservations,
            'reservationStatus' => ReservationStatus::catalogo(),
            'amenities' => $amenities,
            'resources' => $resources,
            'summary' => $summary,
            'filterDate' => $filterDate,
        ]);
    }
}

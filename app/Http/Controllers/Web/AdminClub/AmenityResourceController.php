<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\AmenityResource;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class AmenityResourceController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:amenityResource.index')->only('index');
        $this->middleware('permission:amenityResource.store')->only('store');
        $this->middleware('permission:amenityResource.update')->only('update');
        $this->middleware('permission:amenityResource.destroy')->only('destroy');
        $this->middleware('permission:amenityResource.calendar')->only('calendar');
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();
            $query = AmenityResource::with('amenity')
            ->whereHas('amenity', function ($q) use ($clubId) {
                $q->where('club_id', $clubId);
            });
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search, $driver) {
                    $q->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                    ->orWhereHas('amenity', function ($q2) use ($search, $driver) {
                        $q2->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                    });
                });
            }
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'desc');

            if (in_array($sort, ['id', 'name', 'capacity'])) {
                $query->orderBy($sort, $order);
            }

            $resources = $query->paginate(
                $request->input('per_page', 10)
            );
            $resources->getCollection()->transform(function ($item) {
                $item->amenity_name = $item->amenity?->name;

                return $item;
            });
            return response()->json($resources);
            
            } catch (\Throwable $e) {
                    Log::error('AmenityResource index error', [
                    'messageError'=>$e->getMessage(),
                    'trace'=>$e->getTraceAsString()
                ]);

                return response()->json([
                    'messageError'=>'Error al obtener recursos'
                ],500);
            }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {    
            AmenityResource::create([
                'amenity_id'=>$request->amenity_id,
                'name'=>$request->name,
                'capacity'=>$request->capacity,
                'slot_duration_minutes'=>$request->slot_duration_minutes,
                'is_active'=>$request->is_active
            ]);
            DB::commit();
            return back()->with('success','Recurso creado');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmenityResource store error',[
                'messageError'=>$e->getMessage(),
                'data'=>$request->all()
            ]);
            return back()->with('messageError','No se pudo crear el recurso');
        }
    }

    public function update(Request $request, AmenityResource $amenityResource)
    {
        DB::beginTransaction();
        try {
            $amenityResource->update($request->only('name','capacity','slot_duration_minutes','is_active'));
            DB::commit();
            return back()->with('success','Recurso actualizado');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmenityResource update error',[
                'messageError'=>$e->getMessage(),
                'resource_id'=>$amenityResource->id
            ]);
            return back()->with('messageError','No se pudo actualizar el recurso');
        }
    }

    public function destroy(AmenityResource $amenityResource)
    {
        DB::beginTransaction();
        try {
            $amenityResource->delete();
            DB::commit();
            return back()->with('success','Recurso eliminado');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmenityResource delete error',[
                'messageError'=>$e->getMessage(),
                'resource_id'=>$amenityResource->id
            ]);
            return back()->with('messageError','No se pudo eliminar el recurso');

        }

    }

    public function calendar(AmenityResource $resource)
    {
        $reservations = Reservation::with(['user', 'amenityResource'])
            ->where('amenity_resource_id', $resource->id)
            ->get();

        $statusMap = [
                1 => 'Activa',
                2 => 'Cancelada',
                3 => 'Finalizada',
                4 => 'Inasistencia',
        ];
        $colorMap = [
                1 => '#42a5f5', 
                2 => '#ef5350', 
                3 => '#66bb6a', 
                4 => '#ffa726', 
        ];
    
        return $reservations->map(function ($reservation) use ($statusMap, $colorMap){
            $userName = $reservation->user->name ?? 'Usuario';
            $statusId = $reservation->reservation_status_id;
            /*$start = $reservation->start_datetime;
            $end = $reservation->end_datetime;*/
            return [
                'title' => $userName,
                'start' => $reservation->start_datetime->format('Y-m-d\TH:i:s'),
                'end'   => $reservation->end_datetime->format('Y-m-d\TH:i:s'),
                'color' => $colorMap[$statusId] ?? '#9e9e9e',
                'extendedProps' => [
                    'status' => $statusMap[$statusId] ?? 'Desconocido',
                    'start_time' => $reservation->start_datetime->format('H:i'),
                    'end_time' => $reservation->end_datetime->format('H:i'),
                ]
            ];
        });
    }
    
}

<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminClub\BlockedPeriod;
use App\Models\AdminClub\AmenityResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Carbon\Carbon;

class BlockedPeriodController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:blockedPeriods.index')->only('index');
        $this->middleware('permission:blockedPeriods.store')->only('store');
        $this->middleware('permission:blockedPeriods.update')->only('update');
        $this->middleware('permission:blockedPeriods.destroy')->only('destroy');
    }

    private function validateResourceAvailability($resourceId, $start, $end, $ignoreId = null)
    {
        $start = Carbon::parse($start);
        $end   = Carbon::parse($end);

        /* revisar reservaciones */
        $reservation = DB::table('reservations.reservations')
            ->where('amenity_resource_id', $resourceId)
            ->where('reservation_status_id', '1')
            ->where(function($q) use ($start,$end){
                $q->where('start_datetime','<',$end)
                ->where('end_datetime','>',$start);
            })->first();

        if ($reservation){
            throw ValidationException::withMessages([
                'starts_at' =>
                    'El recurso ya tiene una reservación de '
                    . Carbon::parse(
                        $reservation->start_datetime
                    )->format('d/m/Y H:i')
                    .' a '
                    . Carbon::parse(
                        $reservation->end_datetime
                    )->format('d/m/Y H:i')
            ]);
        }

        /*  revisar eventos   */
        $event = DB::table('announcements.details')
            ->where('resource_id', $resourceId)
            ->whereNull('deleted_at')
            ->where(function($q) use ($start,$end){
                $q->where('starts_at','<',$end)
                ->where('ends_at','>',$start);
            })->first();

        if ($event){
            throw ValidationException::withMessages([
                'starts_at' =>
                    'El recurso ya tiene un evento programado desde '
                    . Carbon::parse($event->starts_at)->format('d/m/Y H:i')
                    .' hasta '
                    . Carbon::parse($event->ends_at)->format('d/m/Y H:i')
            ]);
        }

        /*   revisar bloqueos administrativos     */
        $block = DB::table('amenities.blocked_periods')
            ->where('resource_id',$resourceId)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn($q)=>$q->where('id','!=',$ignoreId))
            ->where(function($q) use ($start,$end){
                $q->where('start_time','<',$end)
                ->where('end_time','>',$start);
            })->first();

        if ($block){
            throw ValidationException::withMessages([
                'starts_at' =>
                    'El recurso está bloqueado por '
                    . $block->reason
                    .' de '
                    . Carbon::parse($block->start_time)->format('H:i')
                    .' a '
                    . Carbon::parse($block->end_time)->format('H:i')
            ]);
        }
    }

    public function index(Request $request){
        try {
            $clubId = session('club_id');
            $prefix = 'blocked_periods';
            $driver = DB::getDriverName();

            $query = BlockedPeriod::with(['resource.amenity'])->whereHas('resource.amenity',fn($q) => $q->where('club_id', $clubId));

            if ($search = $request->input("{$prefix}_search")) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(
                    function ($q)
                    use ($search, $operator) {
                        $q->where('reason', $operator, "%{$search}%")
                          ->orWhereHas('resource', fn($r) 
                          => $r->where('name', $operator, "%{$search}%"
                                    )
                            )->orWhereHas('resource.amenity',fn($a) =>
                             $a->where('name', $operator, "%{$search}%")
                            );
                    }
                );
            }

            $sort = $request->input("{$prefix}_sort", 'id');
            $order = $request->input("{$prefix}_order",'desc');

            $query->orderBy($sort, $order);

            $blockedPeriods = $query->paginate($request->input("{$prefix}_per_page",10))
                                    ->appends($request->except('club_id'));

            $resources = AmenityResource::with('amenity')
                ->whereHas('amenity',fn($q) =>
              $q->where('club_id', $clubId))->get();

            return Inertia::render('AdminClubs/BlockedPeriods/Index',[
                    'blockedPeriods' => $blockedPeriods,
                    'resources' => $resources
                ]
            );
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/BlockedPeriods/Index',[
                    'blockedPeriods' => [
                        'data' => [],
                        'total' => 0
                    ],
                    'resources' => [],
                    'messageError' => $e->getMessage()
                ]
            );
        }
    }

    public function store(Request $request){
        try {
            DB::beginTransaction();
            $this->validateResourceAvailability(
                $request->resource_id,
                $request->start_time,
                $request->end_time
            );

            BlockedPeriod::create([
                'resource_id' => $request->resource_id,
                'reason' => $request->reason,
                'start_time' => $request->start_time
                    ? Carbon::parse($request->start_time) : null,
                'end_time' => $request->end_time
                    ? Carbon::parse($request->end_time) : null,
            ]);

            DB::commit();
            return redirect()
                ->route('blockedPeriods.index')
                ->with('success',
                       'Bloqueo creado correctamente'
                );
        }

        catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()
                ->withErrors([
                    'messageError' => $e->getMessage()
                ])
                ->withInput();      
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BlockedPeriod $blockedPeriod)
    {
        try {
            DB::beginTransaction();
            $this->validateResourceAvailability(
                $request->resource_id,
                $request->start_time,
                $request->end_time,
                $blockedPeriod->id // ignorar el mismo registro
            );
            $blockedPeriod->update([
                'resource_id' => $request->resource_id,
                'reason' => $request->reason,
                'start_time' =>
                    $request->start_time
                    ? Carbon::parse($request->start_time)
                    : null,
                'end_time' =>
                    $request->end_time
                    ? Carbon::parse($request->end_time)
                    : null,
            ]);
            DB::commit();
            return redirect()
                ->route('blockedPeriods.index')
                ->with('success',
                    'Bloqueo actualizado correctamente'
                );
        }
        catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()
                ->withErrors([
                    'messageError' => $e->getMessage()
                ])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
   public function destroy(BlockedPeriod $blockedPeriod){
        try {
            $blockedPeriod->delete();
            return redirect()
                ->route('blockedPeriods.index')
                ->with('success',
                        'Bloqueo eliminado correctamente'
                );
        }
        catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()
                ->withErrors([
                    'messageError' => $e->getMessage()
                ])
                ->withInput();
        }
    }
}

<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AdminClub\Reservation;
use App\Models\AdminClub\BlockedPeriod;
use App\Models\AdminClub\AmenityResource;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminClub\AmenityResourceLocation;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;


class AmenityResourceController extends Controller {

    public function __construct()
    {
        $this->middleware('permission:amenityResource.index')->only('index');
        $this->middleware('permission:amenityResource.store')->only('store');
        $this->middleware('permission:amenityResource.update')->only('update');
        $this->middleware('permission:amenityResource.destroy')->only('destroy');
        $this->middleware('permission:amenityResource.calendar')->only('calendar');
        $this->middleware('permission:amenityResource.generateQr')->only('generateQr');
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();
            $query = AmenityResource::with('amenity','locations')
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
                $item->latitude = $item->location?->latitude;
                $item->longitude = $item->location?->longitude;
                return $item;
            });
            return response()->json($resources);
            
            } catch (\Throwable $e) {
                    Log::error('AmenityResource index error', [
                    'messageError'=>$e->getMessage(),
                    'trace'=>$e->getTraceAsString()
                ]);

                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], 500);
            }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $resource = AmenityResource::create([
                'amenity_id' => $request->amenity_id,
                'name' => $request->name,
                'capacity' => $request->capacity,
                'slot_duration_minutes' => $request->slot_duration_minutes,
                'is_active' => $request->is_active,
                'created_by' => Auth::id(),
            ]);
            foreach ($request->locations ?? [] as $location) {
                if (
                    empty($location['latitude']) ||
                    empty($location['longitude'])
                ) {
                    continue;
                }
                $resource->locations()->create([
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'active' => true,
                ]);
            }
            DB::commit();
            return back()->with(
                'success',
                'Recurso creado'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmenityResource store error', [
                'messageError' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->with(
                'messageError',
                'No se pudo crear el recurso'
            );
        }
    }

    public function update(Request $request, AmenityResource $amenityResource)
    {
        DB::beginTransaction();

        try {
            $amenityResource->update([
                'name' => $request->name,
                'capacity' => $request->capacity,
                'slot_duration_minutes' => $request->slot_duration_minutes,
                'is_active' => $request->is_active,
                'updated_by' => Auth::id(),
            ]);
            $amenityResource
                ->locations()
                ->delete();
            foreach ($request->locations ?? [] as $location) {

                if (
                    empty($location['latitude']) ||
                    empty($location['longitude'])
                ) {
                    continue;
                }
                $amenityResource->locations()->create([
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'active' => true,
                ]);
            }
            DB::commit();
            return back()->with(
                'success',
                'Recurso actualizado'
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AmenityResource update error', [
                'messageError' => $e->getMessage(),
                'resource_id' => $amenityResource->id,
                'data' => $request->all(),
            ]);

            return back()->with(
                'messageError',
                'No se pudo actualizar el recurso'
            );
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
        
        $club_id = session('club_id');
        $reservations = Reservation::with(['user', 'amenityResource'])
            ->where('amenity_resource_id', $resource->id)
            ->where('club_id', $club_id)
            ->get();

        $statusMap = [
                1 => 'Activa',
                2 => 'Cancelada',
                3 => 'Finalizada',
                4 => 'Inasistencia',
                5 => 'Asistencia',
        ];
        $colorMap = [
                1 => '#42a5f5', 
                2 => '#ef5350', 
                3 => '#66bb6a', 
                4 => '#ffa726', 
                5 => '#66bb6a',
        ];
         $blocked = BlockedPeriod::where('club_id', $club_id)->get();
        $reservationEvents = $reservations->map(function ($reservation) use ($statusMap){
            $userName = $reservation->member->full_name ?? 'Usuario';
            $statusId = $reservation->reservation_status_id;

            return [
                'id' => 'res-'.$reservation->id,
                'title' => $userName,
                'start' => $reservation->start_datetime->format('Y-m-d\TH:i:sP'),
                'end'   => $reservation->end_datetime->format('Y-m-d\TH:i:sP'),
                'status' => $statusMap[$statusId] ?? 'Desconocido',
                'reservation_status_id' => $statusId,
            ];
        });
        $blockedEvents = $blocked->map(function ($block) {
            return [
                'id' => 'block-'.$block->id,
                'title' => $block->reason ?? 'Bloqueo',
                'start' => $block->start_time->format('Y-m-d\TH:i:sP'),
                'end'   => $block->end_time->format('Y-m-d\TH:i:sP'),
                'status' => 'Bloqueado',
                'calendarId' => 'blocked',
            ];
        });
        return $reservationEvents->concat($blockedEvents)->values();
    }

    public function generateQr(AmenityResource $amenityResource)
    {
        try {
            $amenityResource->load('amenity.club');

            $club = $amenityResource->amenity->club;

            $url = url("/check-in/resource/{$amenityResource->id}");

            $qrSize = 500;
            $renderer = new ImageRenderer(
                new RendererStyle($qrSize),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svgContent = $writer->writeString(
                $url,
                'UTF-8',
                \BaconQrCode\Common\ErrorCorrectionLevel::H()
            );

            $logoContent = null;
            if ($club?->logo_path && Storage::disk('spaces')->exists($club->logo_path)) {
                $logoContent = Storage::disk('spaces')->get($club->logo_path);
            }

            if ($logoContent) {
                $logoData    = base64_encode($logoContent);
                $logoDataUri = "data:image/png;base64,{$logoData}";
                $logoSize    = (int) round($qrSize * 0.20);
                $logoX       = (int) round(($qrSize - $logoSize) / 2);
                $logoY       = (int) round(($qrSize - $logoSize) / 2);
                $padding     = 6;

                $logoSvg = "<rect"
                    . " x=\"" . ($logoX - $padding) . "\""
                    . " y=\"" . ($logoY - $padding) . "\""
                    . " width=\"" . ($logoSize + $padding * 2) . "\""
                    . " height=\"" . ($logoSize + $padding * 2) . "\""
                    . " fill=\"white\" rx=\"4\"/>"
                    . "<image"
                    . " x=\"{$logoX}\" y=\"{$logoY}\""
                    . " width=\"{$logoSize}\" height=\"{$logoSize}\""
                    . " href=\"{$logoDataUri}\""
                    . " preserveAspectRatio=\"xMidYMid meet\"/>";

                $svgContent = str_replace('</svg>', $logoSvg . '</svg>', $svgContent);
            }

            $filename = 'qr-' . ($club?->name ?? 'club') . '-' . $amenityResource->amenity->name . '-' . $amenityResource->name . '.svg';
            $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $filename);

            return response($svgContent, 200)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Throwable $e) {
            Log::error('Generate QR error', [
                'messageError' => $e->getMessage(),
                'resource_id'  => $amenityResource->id,
            ]);
            abort(500, 'No se pudo generar el QR');
        }
    }
}

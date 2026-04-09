<?php

namespace App\Http\Controllers\Web\AdminClub;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\AdminClub\Amenity;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use App\Models\AdminClub\Announcement;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\AnnouncementDetail;
use App\Models\AdminClub\AnnouncementImage;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{   
    public function __construct()
    {
        $this->middleware('permission:announcements.index')->only('index');
        $this->middleware('permission:announcements.store')->only('store');
        $this->middleware('permission:announcements.update')->only('update');
        $this->middleware('permission:announcements.destroy')->only('destroy');
    }

    private function validateResourceAvailability($resourceId, $start, $end, $ignoreDetailId = null)
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
            ->when($ignoreDetailId,
                fn($q)=>$q->where('id','!=',$ignoreDetailId)
            )->where(function($q) use ($start,$end){
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

    public function index(Request $request)
    { 
        try {
            $clubId = session('club_id');
            $prefix = 'announcements';
            $driver = DB::getDriverName();
 
            $query = Announcement::with(['detail.resource.amenity'])->where('club_id', $clubId);
            if ($search = $request->input("{$prefix}_search")) {
                $operator = $driver == 'pgsql'
                    ? 'ilike'
                    : 'like';
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('title', $operator, "%{$search}%")
                        ->orWhere('summary', $operator, "%{$search}%")
                        ->orWhere('type', $operator, "%{$search}%");
                });
            }

            $sort = $request->input("{$prefix}_sort",'id');
            $order = $request->input("{$prefix}_order",'desc');
            $query->orderBy($sort,$order);
            $announcements = $query->paginate(
                    $request->input(
                        "{$prefix}_per_page",
                        10
                    )
                )->appends($request->except('club_id'));
            $amenities = Amenity::where('club_id',$clubId)->select('id','name')->orderBy('name')->get();
            $resources = AmenityResource::with('amenity')->whereHas('amenity', function ($q) use ($clubId) {$q->where('club_id', $clubId);})->get();

            return Inertia::render(
                'AdminClubs/Announcements/Index',
                [
                    'announcements' => $announcements,
                    'amenities' => $amenities,
                    'resources' => $resources
                ]
            );
        } catch (\Exception $e) {
            report($e);
            return Inertia::render(
                'AdminClubs/Announcements/Index',
                [
                    'announcements' => [
                        'data' => [],
                        'total' => 0
                    ],
                    'amenities' => [],
                    'messageError' => $e->getMessage()
                ]
            );
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $clubId = $request->club_id ?? session('club_id');
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')
                    ->store('announcements', 'public');
            }
            /*if (in_array($request->type, ['torneo','evento'])) {
                $this->validateResourceAvailability(
                       $request->resource_id,
                       $request->starts_at,
                       $request->ends_at,
                );
            }*/
            $announcement = Announcement::create([
                'club_id' => $clubId,
                'title' => $request->title,
                'summary' => $request->summary,
                'content' => $request->content,
                'type' => $request->type,
                'image' => $imagePath,
                'is_active' => $request->boolean('is_active', true),
                'publish_at' => $request->publish_at ? Carbon::parse($request->publish_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
            ]);

           /*if ($request->type === 'torneo' || $request->type === 'evento') {
                AnnouncementDetail::create([
                    'announcement_id' => $announcement->id,
                    'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : null,
                    'ends_at' => $request->ends_at ? Carbon::parse($request->ends_at) : null,
                    'resource_id' => $request->resource_id,
                    'capacity' => $request->capacity
                ]);
            }*/

            DB::commit();
            return redirect()
                ->route('announcements.index')
                ->with('success', 'Anuncio creado correctamente');
        } catch (\Exception $e) {
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
   public function update(Request $request, Announcement $announcement)
    {
        try {
            DB::beginTransaction();
            $clubId = $request->club_id ?? session('club_id');
            if (in_array($request->type, ['torneo','evento'])) {

                /*$this->validateResourceAvailability(
                    $request->resource_id,
                    $request->starts_at,
                    $request->ends_at,
                    AnnouncementDetail::where('announcement_id', $announcement->id)->value('id')
                );*/
            }
            $imagePath = $announcement->image;
            if ($request->boolean('remove_image')) {
                if (
                    $announcement->image
                    && Storage::disk('public')->exists($announcement->image)
                ) {
                    Storage::disk('public')->delete($announcement->image);
                }

                $imagePath = null;
            }
            if ($request->hasFile('image')) {
                if ($announcement->image && Storage::disk('public')->exists($announcement->image)) {
                    Storage::disk('public')->delete($announcement->image);
                }

                $imagePath = $request->file('image')
                    ->store('announcements', 'public');
            }
            $announcement->update([
                'club_id' => $clubId,
                'title' => $request->title,
                'summary' => $request->summary,
                'content' => $request->content,
                'type' => $request->type,
                'image' => $imagePath,
                'is_active' => $request->boolean('is_active', true),
                'publish_at' => $request->publish_at ? Carbon::parse($request->publish_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
            ]);
           /* if ($request->type === 'torneo' || $request->type === 'evento') {
                AnnouncementDetail::updateOrCreate(
                    ['announcement_id' => $announcement->id],
                    [
                        'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : null,
                        'ends_at' => $request->ends_at ? Carbon::parse($request->ends_at) : null,
                        'resource_id' => $request->resource_id,
                        'capacity' => $request->capacity
                    ]
                );
            } else {
                AnnouncementDetail::where('announcement_id', $announcement->id)->delete();
            }*/
            DB::commit();
            return redirect()
                ->route('announcements.index')
                ->with('success', 'Anuncio actualizado correctamente');
        } catch (\Exception $e) {
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
    public function destroy(Announcement $announcement)
    {
        try {

            DB::beginTransaction();
            AnnouncementDetail::where('announcement_id', $announcement->id)->delete();
            if ($announcement->image && Storage::disk('public')->exists($announcement->image)) {
                Storage::disk('public')->delete($announcement->image);
            }
            $announcement->delete();

            DB::commit();
            return redirect()
                ->route('announcements.index')
                ->with('success', 'Anuncio eliminado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()
                ->with('error', $e->getMessage());
        }
    }

    public function storeGallery(Request $request){
        //dd($request->all(), $request->file('images'));
        try {
            DB::beginTransaction();
            /*$request->validate([
                'announcement_id' => [
                'required',
                Rule::exists((new Announcement)->getTable(), 'id')
                    ->whereNull('deleted_at')
                ],
                'images.*' => 'nullable|image|max:5120'
            ]);*/
            $announcementId = $request->announcement_id;

            /*    eliminar imágenes seleccionadas   */
            if($request->remove_images){
                $imagesToDelete = AnnouncementImage::whereIn('id', $request->remove_images)->get();
                foreach($imagesToDelete as $img){
                    if($img->image){
                        Storage::disk('public')->delete($img->image);
                    }
                    $img->delete();
                }
            }

            /*   guardar nuevas imágenes   */
            if($request->hasFile('images')){
                foreach($request->file('images') as $index => $file){
                    $path = $file->store(
                        'announcements',
                        'public'
                    );
                    AnnouncementImage::create([
                        'announcement_id' => $announcementId,
                        'image' => $path
                    ]);
                }
            }
            DB::commit();
            return back()->with(
                'success',
                'Galería actualizada correctamente'
            );
        }

        catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
    public function getGallery($id){
        $announcement = Announcement::with('images')
            ->findOrFail($id);
        return response()->json([
            'images' => $announcement->images
        ]);
    }
    public function destroyGalleryImage(AnnouncementImage $image){
        try {
            if($image->image){
                Storage::disk('public')->delete(
                    $image->image
                );
            }
            $image->delete();
            return back()->with(
                'success',
                'Imagen eliminada'
            );
        }
        catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'messageError' => $e->getMessage()
            ]);
        }
    }
}

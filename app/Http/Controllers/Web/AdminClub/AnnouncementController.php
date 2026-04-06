<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Models\AdminClub\Amenity;
use App\Models\AdminClub\Announcement;
use App\Models\AdminClub\AmenityResource;
use App\Models\AdminClub\AnnouncementDetail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

class AnnouncementController extends Controller
{   

    public function __construct()
    {
        $this->middleware('permission:announcement.index')->only('index');
        $this->middleware('permission:announcement.store')->only('store');
        $this->middleware('permission:announcement.update')->only('update');
        $this->middleware('permission:announcement.destroy')->only('destroy');
    }

    private function validateResourceAvailability($resourceId, $start, $end, $ignoreDetailId = null)
    {
        $start = Carbon::parse($start);
        $end   = Carbon::parse($end);

        /*  revisar eventos   */
        $event = DB::table('announcements.details')
            ->where('resource_id', $resourceId)
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
                    'error' => $e->getMessage()
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
            if (in_array($request->type, ['torneo','evento'])) {

                /*$start = Carbon::parse($request->starts_at);
                $end = Carbon::parse($request->ends_at);

                $conflict = AnnouncementDetail::where('resource_id', $request->resource_id)
                    ->whereHas('announcement', function ($q) {
                        $q->whereIn('type', ['torneo','evento']);
                    })->where(function ($q) use ($start, $end) {
                        $q->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                    })->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'El recurso está ocupado desde '
                            . $conflict->starts_at->format('d/m/Y H:i')
                            .' hasta '
                            . $conflict->ends_at->format('d/m/Y H:i')
                    ]);
                }*/
                $this->validateResourceAvailability(
                       $request->resource_id,
                       $request->starts_at,
                       $request->ends_at,
                );
            }
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

            if (
                $request->type === 'torneo'
                || $request->type === 'evento'
            ) {
                AnnouncementDetail::create([
                    'announcement_id' => $announcement->id,
                    'starts_at' => $request->starts_at ? Carbon::parse($request->starts_at) : null,
                    'ends_at' => $request->ends_at ? Carbon::parse($request->ends_at) : null,
                    'resource_id' => $request->resource_id,
                    'capacity' => $request->capacity
                ]);
            }

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

                /*$start = Carbon::parse($request->starts_at);
                $end = Carbon::parse($request->ends_at);

                $conflict = AnnouncementDetail::where('resource_id', $request->resource_id)
                    ->where('announcement_id', '!=', $announcement->id)
                    ->whereHas('announcement', function ($q) {
                        $q->whereIn('type', ['torneo','evento']);
                    })->where(function ($q) use ($start, $end) {
                        $q->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                    })->first();
                if ($conflict) {
                    throw ValidationException::withMessages([
                        'starts_at' =>
                            'El recurso está ocupado desde '
                            . $conflict->starts_at->format('d/m/Y H:i')
                            .' hasta '
                            . $conflict->ends_at->format('d/m/Y H:i')
                    ]);
                }*/
                $this->validateResourceAvailability(
                    $request->resource_id,
                    $request->starts_at,
                    $request->ends_at,
                    AnnouncementDetail::where('announcement_id', $announcement->id)->value('id')
                );
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
            if ($request->type === 'torneo' || $request->type === 'evento') {
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
            }
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
}

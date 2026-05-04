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
                        ->orWhere('content', $operator, "%{$search}%")
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
            $announcements->getCollection()->transform(function ($item) {
            $item->publish_at_formatted = $item->publish_at?->format('Y-m-d H:i:s');
            $item->expires_at_formatted = $item->expires_at?->format('Y-m-d H:i:s');
            return $item;
        });
            return Inertia::render(
                'AdminClubs/Announcements/Index',
                [
                    'announcements' => $announcements
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
                    'messageError' => $e->getMessage()
                ]
            );
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
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
                'content' => $request->content,
                'type' => $request->type,
                'image' => $imagePath,
                'is_active' => $request->boolean('is_active', true),
                'publish_at' => $request->publish_at ? Carbon::parse($request->publish_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
            ]);

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
        $request->validate([
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        try {
            DB::beginTransaction();
            $clubId = $request->club_id ?? session('club_id');
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
                'content' => $request->content,
                'type' => $request->type,
                'image' => $imagePath,
                'is_active' => $request->boolean('is_active', true),
                'publish_at' => $request->publish_at ? Carbon::parse($request->publish_at) : null,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null
            ]);
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

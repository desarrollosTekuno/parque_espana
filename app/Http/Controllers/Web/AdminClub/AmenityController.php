<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\AdminClub\Reservation;

class AmenityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:amenities.index')->only('index');
        $this->middleware('permission:amenities.store')->only('store');
        $this->middleware('permission:amenities.update')->only('update');
        $this->middleware('permission:amenities.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $prefix = 'amenities';
            $driver = DB::getDriverName();
            $query = Amenity::with('schedules')->where('club_id', $clubId);
            $members = DB::table('members.members')->select('id', 'first_name', 'last_name', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"))->orderBy('full_name')->get();

             if ($search = $request->input("{$prefix}_search")) {
                $query->where(function ($q) use ($driver, $search) {
                    $q->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                });
            }

            if ($search = $request->input("{$prefix}_search")) {
                $query->where(function ($q) use ($search, $driver) {
                    $q->where('name', $driver === 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                });
            }

            $sort  = $request->input("{$prefix}_sort", 'id');
            $order = $request->input("{$prefix}_order", 'desc');
            $query->orderBy($sort, $order);

            $amenities = $query->paginate(
                $request->input("{$prefix}_per_page", 10)
            )->appends($request->all());

            return Inertia::render('AdminClubs/Amenities/Index', [
                'amenities' => $amenities,
                'members'   => $members,
            ]);
        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/Amenities/Index', [
                'amenities'    => ['data' => [], 'total' => 0],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $clubId = session('club_id');
            $data   = $request->all();

            if ($request->hasFile('icon')) {
                $data['icon'] = $this->uploadAmenityImage(
                    $request->file('icon'),
                    $clubId,
                    'icons',
                );
            }

            if ($request->hasFile('background_image')) {
                $data['background_image'] = $this->uploadAmenityImage(
                    $request->file('background_image'),
                    $clubId,
                    'backgrounds',
                );
            }

            if ($request->hasFile('regulation_file')) {
                $data['regulation_file'] = $this->uploadAmenityFile(
                    $request->file('regulation_file'),
                    $clubId,
                    'regulations',
                );
            }

            Amenity::create(array_merge($data, ['club_id' => $clubId]));

            return redirect()->back()->with('success', 'Amenidad creada correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('messageError', $e->getMessage());
        }
    }

    public function update(Request $request, Amenity $amenity)
    {
        try {
            $clubId = session('club_id');
            $data   = $request->except(['icon', 'background_image', 'regulation_file']);

            if ($request->hasFile('icon')) {
                $this->deleteAmenityImage($amenity->icon);
                $data['icon'] = $this->uploadAmenityImage($request->file('icon'), $clubId, 'icons');
            } elseif ($request->boolean('remove_icon')) {
                $this->deleteAmenityImage($amenity->icon);
                $data['icon'] = null;
            }

            if ($request->hasFile('background_image')) {
                $this->deleteAmenityImage($amenity->background_image);
                $data['background_image'] = $this->uploadAmenityImage($request->file('background_image'), $clubId, 'backgrounds');
            } elseif ($request->boolean('remove_background_image')) {
                $this->deleteAmenityImage($amenity->background_image);
                $data['background_image'] = null;
            }

            if ($request->hasFile('regulation_file')) {
                $this->deleteAmenityImage($amenity->regulation_file);
                $data['regulation_file'] = $this->uploadAmenityFile($request->file('regulation_file'), $clubId, 'regulations');
            } elseif ($request->boolean('remove_regulation_file')) {
                $this->deleteAmenityImage($amenity->regulation_file);
                $data['regulation_file'] = null;
            }

            $amenity->update($data);

            return redirect()->back()->with('success', 'Amenidad actualizada correctamente');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors([
                'messageError' => 'Error al actualizar la amenidad',
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    public function destroy(Amenity $amenity)
    {
        try {
            $this->deleteAmenityImage($amenity->icon);
            $this->deleteAmenityImage($amenity->background_image);
            $this->deleteAmenityImage($amenity->regulation_file);

            $amenity->delete();

            return redirect()->back()->with('success', 'Amenidad eliminada correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('messageError', $e->getMessage()); 
        }
    }

    private function uploadAmenityImage(\Illuminate\Http\UploadedFile $file, int|string $clubId, string $type): string
    {
        $clubCode  = \App\Models\Administrator\Club::find($clubId)?->code ?? $clubId;
        $directory = "clubs/{$clubCode}/amenities/{$type}";
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('spaces')->putFileAs($directory, $file, $filename, 'public');

        return "{$directory}/{$filename}";
    }

    private function uploadAmenityFile(\Illuminate\Http\UploadedFile $file, int|string $clubId, string $type): string
    {
        $clubCode  = \App\Models\Administrator\Club::find($clubId)?->code ?? $clubId;
        $directory = "clubs/{$clubCode}/amenities/{$type}";
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('spaces')->putFileAs($directory, $file, $filename, 'public');

        return "{$directory}/{$filename}";
    }

    private function deleteAmenityImage(?string $path): void
    {
        if ($path && Storage::disk('spaces')->exists($path)) {
            Storage::disk('spaces')->delete($path);
        }
    }
}

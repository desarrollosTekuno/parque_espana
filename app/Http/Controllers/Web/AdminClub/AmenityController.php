<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
        if ($search = $request->input("{$prefix}_search")) {
            $query->where(function ($q) use ($search, $driver) {
                $q->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
            });
        }
        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $amenities = $query->paginate(
            $request->input("{$prefix}_per_page", 10)
        )->appends( $request->except('club_id'));

        return Inertia::render('AdminClubs/Amenities/Index', [
            'amenities' => $amenities,
        ]);

    } catch (\Exception $e) {
        report($e);
        return Inertia::render('AdminClubs/Amenities/Index', [
            'amenities' => [
                'data' => [],
                'total' => 0
            ],
            'error' => $e->getMessage()
        ]);
    }
}

    public function store(Request $request)
    {
        try {

            $data = $request->all();
            if ($request->hasFile('icon')) {
                $data['icon'] = $request->file('icon')->store('amenities/icons', 'public');
            }
            if ($request->hasFile('background_image')) {
                $data['background_image'] = $request->file('background_image')->store('amenities/backgrounds', 'public');
            }
            
            Amenity::create(array_merge($data, ['club_id' => session('club_id')]));
            return redirect()->back()->with('success', 'Amenidad creada correctamente');

        } catch (\Exception $e) {

            return redirect()->back()->with('error', $e->getMessage());

        }
    }


    public function update(Request $request, Amenity $amenity)
    {
        try {

            $data = $request->except(['icon', 'background_image']);

            if ($request->hasFile('icon')) {

                if ($amenity->icon) {
                    Storage::disk('public')->delete($amenity->icon);
                }

                $data['icon'] = $request->file('icon')->store('amenities/icons', 'public');

            } elseif ($request->boolean('remove_icon')) {

                if ($amenity->icon) {
                    Storage::disk('public')->delete($amenity->icon);
                }

                $data['icon'] = null;
            }

            if ($request->hasFile('background_image')) {

                if ($amenity->background_image) {
                    Storage::disk('public')->delete($amenity->background_image);
                }

                $data['background_image'] = $request->file('background_image')->store('amenities/backgrounds', 'public');

            } elseif ($request->boolean('remove_background_image')) {

                if ($amenity->background_image) {
                    Storage::disk('public')->delete($amenity->background_image);
                }

                $data['background_image'] = null;
            }
            $amenity->update($data);
            return redirect()->back()->with('success', 'Amenidad actualizada correctamente');

        } catch (\Throwable $e) {

            return redirect()->back()->withErrors([
                'messageError' => 'Error al actualizar la amenidad',
                'exception' => $e->getMessage(),
            ]);

        }
    }

    public function destroy(Amenity $amenity)
    {
        try {

            //$amenity = Amenity::findOrFail($id);

            if ($amenity->icon) {
                Storage::disk('public')->delete($amenity->icon);
            }

            if ($amenity->background_image) {
                Storage::disk('public')->delete($amenity->background_image);
            }

            $amenity->delete();
            return redirect()->back()->with('success', 'Amenidad eliminada correctamente');

        } catch (\Exception $e) {

            return redirect()->back()->with('error', $e->getMessage());

        }
    }
}
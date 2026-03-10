<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\Amenity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
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
        $prefix = 'amenities';
        $driver = DB::getDriverName();

        $query = Amenity::query();

        if ($search = $request->input("{$prefix}_search")) {
            $query->where('name', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%")
                ->orWhere('description', $driver == 'pgsql' ? 'ilike' : 'like', "%{$search}%");
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $query->orderBy($sort, $order);

        $amenities = $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/Amenities/Index', [
            'amenities' => $amenities,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store('amenities/icons', 'public');
        }
        if ($request->hasFile('background_image')) {
            $data['background_image'] = $request->file('background_image')->store('amenities/backgrounds', 'public');
        }
        
        Amenity::create(array_merge($data, ['club_id' => 1]));
        return redirect()->back()->with('success', 'Amenidad creada correctamente');
    }


    public function update(Request $request, string $id)
    {
        $amenity = Amenity::findOrFail($id);

        $data = $request->all();
        if ($request->hasFile('icon')) {
            if ($amenity->icon) {
                Storage::disk('public')->delete($amenity->icon);
            }
            $data['icon'] = $request->file('icon')->store('amenities/icons', 'public');
        }
        if ($request->hasFile('background_image')) {

            if ($amenity->background_image) {
                Storage::disk('public')->delete($amenity->background_image);
            }

            $data['background_image'] = $request->file('background_image')->store('amenities/backgrounds', 'public');
        }

        $amenity->update($data);
        return redirect()->back()->with('success', 'Amenidad actualizada correctamente');
    }

    public function destroy(string $id)
    {
        $amenity = Amenity::findOrFail($id);

        if ($amenity->icon) {
            Storage::disk('public')->delete($amenity->icon);
        }

        if ($amenity->background_image) {
            Storage::disk('public')->delete($amenity->background_image);
        }

        $amenity->delete();
        return redirect()->back()->with('success', 'Amenidad eliminada correctamente');
    }
}
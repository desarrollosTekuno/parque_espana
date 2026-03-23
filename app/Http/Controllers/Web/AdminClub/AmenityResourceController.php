<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
    }

    public function index(Request $request)
{
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
}

    public function store(Request $request)
    {

    AmenityResource::create([
        'amenity_id'=>$request->amenity_id,
        'name'=>$request->name,
        'capacity'=>$request->capacity,
        'is_active'=>$request->is_active
    ]);

    return back()->with('success','Recurso creado');

    }
    public function update(Request $request,$id)
{

$resource = AmenityResource::findOrFail($id);

$resource->update([
'name'=>$request->name,
'capacity'=>$request->capacity,
'is_active'=>$request->is_active
]);

return back()->with('success','Recurso actualizado');

}
public function destroy($id)
{

AmenityResource::destroy($id);

return back()->with('success','Recurso eliminado');

}
}

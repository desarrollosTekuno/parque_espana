<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\AdminClub\AmenityResource;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class AmenityResourceController extends Controller {

    public function index()
    {
        $resources = AmenityResource::with('amenity')
        ->paginate(10);

        return inertia('Amenities/Index',[
            'resources'=>$resources
        ]);
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

<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\AdminClub\PhysicalAdSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PhysicalAdSizeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:physical-ad-sizes.index')->only('index');
        $this->middleware('permission:physical-ad-sizes.store')->only('store');
        $this->middleware('permission:physical-ad-sizes.update')->only('update');
        $this->middleware('permission:physical-ad-sizes.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        $clubId = $request->club_id ?? session('club_id');
        $prefix = 'physicalAdSizes';
        $driver = DB::getDriverName();
        $like   = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = PhysicalAdSize::where('club_id', $clubId);

        if ($search = $request->input("{$prefix}_search")) {
            $query->where('label', $like, "%{$search}%");
        }

        $sort  = $request->input("{$prefix}_sort", 'display_order');
        $order = $request->input("{$prefix}_order", 'asc');
        $query->orderBy($sort, $order)->orderBy('id', 'asc');

        $sizes = $query->paginate(
            $request->input("{$prefix}_per_page", 25),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());

        return Inertia::render('AdminClubs/PhysicalAdSizes/Index', [
            'physicalAdSizes' => $sizes,
        ]);
    }

    public function store(Request $request)
    {
        $clubId = (int) session('club_id');

        $request->validate([
            'label'         => 'required|string|max:100',
            'price'         => 'required|numeric|min:0',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
        ]);

        try {
            PhysicalAdSize::create([
                'club_id'       => $clubId,
                'label'         => $request->label,
                'price'         => $request->price,
                'is_active'     => $request->boolean('is_active', true),
                'display_order' => $request->input('display_order', 0),
            ]);

            return back()->with('success', 'Tamaño creado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function update(Request $request, PhysicalAdSize $physicalAdSize)
    {
        $request->validate([
            'label'         => 'required|string|max:100',
            'price'         => 'required|numeric|min:0',
            'is_active'     => 'boolean',
            'display_order' => 'integer|min:0',
        ]);

        try {
            $physicalAdSize->update([
                'label'         => $request->label,
                'price'         => $request->price,
                'is_active'     => $request->boolean('is_active', true),
                'display_order' => $request->input('display_order', 0),
            ]);

            return back()->with('success', 'Tamaño actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }

    public function destroy(PhysicalAdSize $physicalAdSize)
    {
        try {
            $physicalAdSize->delete();
            return back()->with('success', 'Tamaño eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['messageError' => $e->getMessage()]);
        }
    }
}

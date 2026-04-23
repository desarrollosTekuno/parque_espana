<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\AdminClub\Survey;

class SurveyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:surveys.index')->only('index');
        $this->middleware('permission:surveys.store')->only('store');
        $this->middleware('permission:surveys.update')->only('update');
        $this->middleware('permission:surveys.delete')->only('delete');

    }
    public function index(Request $request)
    {
        try {
            $clubId = $request->club_id ?? session('club_id');
            $driver = DB::getDriverName();

            $query = Survey::where('club_id', $clubId);

            if ($search = trim($request->input("search"))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                      ->orWhere('type', $operator, "%{$search}%");
                });
            }

            $surveys = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input("per_page", 10))
                ->through(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'link' => $item->link,
                        'type' => $item->type,
                        'is_active' => $item->is_active,
                    ];
                })
                ->withQueryString();

            return Inertia::render('AdminClubs/Surveys/Index', [
                'surveys' => $surveys
            ]);

        } catch (\Exception $e) {
            report($e);
            return Inertia::render('AdminClubs/Surveys/Index', [
                'surveys' => [
                    'data' => [],
                    'total' => 0
                ],
                'messageError' => $e->getMessage()
            ]);
        }
    }

    public function store(Request $request)
    {  
        $request->validate([
            'name' => 'required|string|max:255',
            'link' => 'required|url',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'link.required' => 'Debes ingresar un enlace.',
            'link.url' => 'El enlace debe ser válido.',
        ]);

        try {

            Survey::create([
                'club_id' => session('club_id'),
                'name' => $request->name,
                'link' => $request->link,
                'is_active' => true
            ]);

            return back()->with('success', 'Encuesta creada correctamente');

        } catch (\Exception $e) {
            report($e);
            return $e;
            return back()->withErrors([
                'messageError' => 'Error al crear la encuesta',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, Survey $survey)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'link' => 'required|url',
            'is_active' => 'required|boolean',
        ], [
            'name.required' => 'Debes ingresar un nombre.',
            'link.required' => 'Debes ingresar un enlace.',
            'link.url' => 'El enlace debe ser válido.',
        ]);

        try {
            $survey->update([
                'name' => $request->name,
                'link' => $request->link,
                'is_active' => $request->is_active
            ]);

            return back()->with('success', 'Encuesta actualizada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar la encuesta',
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function destroy(Survey $survey)
    {
        try {
            
            $survey->delete();
            return back()->with('success', 'Encuesta eliminada');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar la encuesta',
                'exception' => $e->getMessage()
            ]);
        }
    }
}
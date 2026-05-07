<?php

namespace App\Http\Controllers\Web\AdminClub\Feedback;

use App\Models\Feedback\TicketType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedbackTicketTypeController extends Controller {

    public function __construct() {
        $this->middleware('permission:feedback-ticket-types.index')->only('index');
        $this->middleware('permission:feedback-ticket-types.store')->only('store');
        $this->middleware('permission:feedback-ticket-types.update')->only('update');
        $this->middleware('permission:feedback-ticket-types.destroy')->only('destroy');
    }

    public function index(Request $request) {
        try {
            $driver = DB::getDriverName();

            $query = TicketType::query();

            if ($search = trim($request->input('search'))) {
                $operator = $driver == 'pgsql' ? 'ilike' : 'like';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('code', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%");
                });
            }

            $ticketTypes = $query
                ->orderBy('id', 'desc')
                ->paginate($request->input('per_page', 10))
                ->withQueryString();

            return Inertia::render('AdminClubs/FeedbackTicketTypes/Index', [
                'ticketTypes' => $ticketTypes,
            ]);

        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/FeedbackTicketTypes/Index', [
                'ticketTypes' => [
                    'data' => [],
                    'total' => 0,
                ],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:65',
            'code' => 'required|string|max:30',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        try {
            TicketType::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return back()->with('success', 'Tipo de ticket creado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al crear el tipo de ticket',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, $id) {
        $request->validate([
            'name' => 'required|string|max:65',
            'code' => 'required|string|max:60',
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        try {
            $ticketType = TicketType::findOrFail($id);

            $ticketType->update([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active,
            ]);

            return back()->with('success', 'Tipo de ticket actualizado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al actualizar el tipo de ticket',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        try {
            $ticketType = TicketType::findOrFail($id);
            $ticketType->delete();

            return back()->with('success', 'Tipo de ticket eliminado correctamente');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'Error al eliminar el tipo de ticket',
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

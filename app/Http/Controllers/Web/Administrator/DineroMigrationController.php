<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Services\Migration\Dinero\DineroMigrationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DineroMigrationController extends Controller
{
    public function __construct(private DineroMigrationService $migrationService)
    {
        $this->middleware('permission:dinero-migration.index')->only('index');
        $this->middleware('permission:dinero-migration.preview')->only('preview');
        $this->middleware('permission:dinero-migration.import')->only('import');
    }

    public function index()
    {
        return Inertia::render('Administrator/DineroMigration/Index');
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);

        $previous = $request->session()->get('dinero_migration_preview');
        if ($previous && Storage::disk('local')->exists($previous['path'])) {
            Storage::disk('local')->delete($previous['path']);
        }
        $request->session()->forget('dinero_migration_preview');

        $path = $request->file('file')->store('migration-previews', 'local');
        $fullPath = Storage::disk('local')->path($path);
        try {
            $report = $this->migrationService->review($fullPath);
            if ($report['errors'] === []) {
                try {
                    $this->migrationService->run($fullPath, true);
                } catch (\Throwable $e) {
                    $report['errors'][] = [
                        'sheet' => 'Validación de carga',
                        'row' => null,
                        'field' => 'ARCHIVO',
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => 'No se pudo revisar el archivo: ' . $e->getMessage()], 422);
        }

        $token = Str::random(40);
        $request->session()->put('dinero_migration_preview', ['token' => $token, 'path' => $path]);
        return response()->json(['token' => $token, 'report' => $report]);
    }

    public function import(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);
        $preview = $request->session()->get('dinero_migration_preview');
        if (!$preview || !hash_equals($preview['token'], $request->input('token'))
            || !Storage::disk('local')->exists($preview['path'])) {
            return response()->json(['message' => 'La revisión expiró. Vuelva a subir el archivo.'], 422);
        }

        $fullPath = Storage::disk('local')->path($preview['path']);
        try {
            $counts = $this->migrationService->run($fullPath);
            Storage::disk('local')->delete($preview['path']);
            $request->session()->forget('dinero_migration_preview');
            return response()->json(['message' => 'Cargos y pagos históricos cargados.', 'counts' => $counts]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No se pudo cargar el archivo: ' . $e->getMessage()], 422);
        }
    }
}

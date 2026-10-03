<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Services\Migration\Socios\SociosMigrationService;
use App\Services\Migration\Socios\SociosTemplateReviewService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SociosMigrationController extends Controller
{
    public function __construct(
        private SociosTemplateReviewService $reviewService,
        private SociosMigrationService $migrationService
    ) {
        $this->middleware('permission:socios-migration.index')->only('index');
        $this->middleware('permission:socios-migration.preview')->only('preview');
        $this->middleware('permission:socios-migration.import')->only('import');
    }

    public function index()
    {
        return Inertia::render('Administrator/SociosMigration/Index');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'sin_personal' => ['required', 'boolean'],
        ]);

        $previous = $request->session()->get('socios_migration_preview');
        if ($previous && Storage::disk('local')->exists($previous['path'])) {
            Storage::disk('local')->delete($previous['path']);
        }
        $request->session()->forget('socios_migration_preview');

        $path = $request->file('file')->store('migration-previews', 'local');
        $fullPath = Storage::disk('local')->path($path);
        try {
            $report = $this->reviewService->review($fullPath);
            try {
                $this->migrationService->run($fullPath, true, $request->boolean('sin_personal'));
            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'sheet' => 'Validación de carga',
                    'row' => null,
                    'field' => 'ARCHIVO',
                    'message' => $e->getMessage(),
                    'origin' => 'Validación técnica',
                ];
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => 'No se pudo revisar el archivo: ' . $e->getMessage()], 422);
        }

        $token = Str::random(40);
        $request->session()->put('socios_migration_preview', [
            'token' => $token,
            'path' => $path,
            'sin_personal' => $request->boolean('sin_personal'),
        ]);

        return response()->json(['token' => $token, 'report' => $report]);
    }

    public function import(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);
        $preview = $request->session()->get('socios_migration_preview');
        if (!$preview || !hash_equals($preview['token'], $request->input('token'))
            || !Storage::disk('local')->exists($preview['path'])) {
            return response()->json(['message' => 'La revisión expiró. Vuelva a subir el archivo.'], 422);
        }

        $fullPath = Storage::disk('local')->path($preview['path']);
        try {
            $report = $this->reviewService->review($fullPath);
            if ($report['errors'] !== []) {
                return response()->json(['message' => 'El archivo tiene errores que deben corregirse.', 'report' => $report], 422);
            }
            $counts = $this->migrationService->run($fullPath, false, $preview['sin_personal']);
            Storage::disk('local')->delete($preview['path']);
            $request->session()->forget('socios_migration_preview');

            return response()->json(['message' => 'Primera fase cargada correctamente.', 'counts' => $counts]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'No se pudo cargar el archivo: ' . $e->getMessage()], 422);
        }
    }
}

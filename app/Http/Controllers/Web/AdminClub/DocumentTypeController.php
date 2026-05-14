<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Rules\ExistsInSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DocumentTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:document-types.index')->only('index');
        $this->middleware('permission:document-types.store')->only('store');
        $this->middleware('permission:document-types.update')->only('update');
        $this->middleware('permission:document-types.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $driver = DB::getDriverName();
            $prefix = 'documentTypes';

            $query = DocumentType::query();

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('code', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            }

            $sortMap = [
                'id' => 'id',
                'code' => 'code',
                'name' => 'name',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'name');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'name';

            $documentTypes = $query
                ->with('relationships')
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(fn (DocumentType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'allowed_extensions' => $type->allowed_extensions,
                    'relationship_ids' => $type->relationships->pluck('id')->values(),
                    'relationships' => $type->relationships->map(fn (Relationship $r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                    ])->values(),
                ])
                ->appends($request->all());

            $allRelationships = Relationship::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (Relationship $r) => ['id' => $r->id, 'name' => $r->name]);

            return Inertia::render('AdminClubs/DocumentTypes/Index', [
                'documentTypes' => $documentTypes,
                'allRelationships' => $allRelationships,
                'filters' => [
                    'search' => $request->input("{$prefix}_search"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/DocumentTypes/Index', [
                'documentTypes' => ['data' => [], 'total' => 0],
                'allRelationships' => [],
                'filters' => ['search' => $request->input('documentTypes_search')],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateType($request);

            DB::transaction(function () use ($validated) {
                $type = DocumentType::create($this->typeAttributes($validated));
                $type->relationships()->sync($validated['relationship_ids'] ?? []);
            });

            return redirect()->back()->with('success', 'Tipo de documento creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el tipo de documento.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, DocumentType $documentType)
    {
        try {
            $validated = $this->validateType($request, $documentType);

            DB::transaction(function () use ($documentType, $validated) {
                $documentType->update($this->typeAttributes($validated));
                $documentType->relationships()->sync($validated['relationship_ids'] ?? []);
            });

            return redirect()->back()->with('success', 'Tipo de documento actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el tipo de documento.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(DocumentType $documentType)
    {
        try {
            $documentType->delete();

            return redirect()->back()->with('success', 'Tipo de documento eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el tipo de documento.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateType(Request $request, ?DocumentType $type = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(DocumentType::class, 'code')->ignore($type?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'allowed_extensions' => ['nullable', 'string', 'max:255'],
            'relationship_ids' => ['nullable', 'array'],
            'relationship_ids.*' => ['integer', new ExistsInSchema('catalogs', 'relationships')],
        ]);
    }

    protected function typeAttributes(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'allowed_extensions' => isset($validated['allowed_extensions'])
                ? strtolower(trim((string) $validated['allowed_extensions']))
                : null,
        ];
    }
}

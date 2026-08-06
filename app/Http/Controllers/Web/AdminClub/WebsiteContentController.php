<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Administrator\Club;
use App\Models\Website\CarouselImage;
use App\Models\Website\HomeCard;
use App\Models\Website\VirtualTourCategory;
use App\Models\Website\VirtualTourImage;
use App\Models\Website\WebsiteEvent;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WebsiteContentController extends Controller {

    private const CARD_CATEGORIES = [
        'Gimnasio',
        'Alberca',
        'Tenis',
        'Jardines',
        'Cafetería',
    ];

    private const VIRTUAL_TOUR_CATEGORIES = [
        'Interior',
        'Exterior',
        'Servicios',
        'Actividad física',
        'Estacionamiento',
    ];

    private const EVENT_TYPES = [
        ['value' => 'activity', 'title' => 'Actividad', 'color' => '#0097A7'],
        ['value' => 'celebration', 'title' => 'Celebración', 'color' => '#EC659C'],
        ['value' => 'holiday', 'title' => 'Día festivo', 'color' => '#F4B400'],
    ];

    public function __construct() {
        $this->middleware('permission:website-content.index')->only('index');
        $this->middleware('permission:website-content.store')->only([
            'store',
            'storeCard',
            'storeVirtualTourCategory',
            'storeVirtualTourImages',
            'saveEvent',
        ]);
        $this->middleware('permission:website-content.destroy')->only([
            'destroy',
            'destroyCard',
            'destroyVirtualTourCategory',
            'destroyVirtualTourImage',
            'destroyEvent',
        ]);
    }

    public function index() {
        $clubId = (int) session('club_id');

        foreach (self::VIRTUAL_TOUR_CATEGORIES as $category) {
            VirtualTourCategory::firstOrCreate([
                'club_id' => $clubId,
                'name' => $category,
            ]);
        }

        $images = CarouselImage::where('club_id', session('club_id'))
            ->orderBy('id')
            ->get();

        $homeCards = HomeCard::where('club_id', session('club_id'))
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $virtualTourCategories = VirtualTourCategory::where('club_id', $clubId)
            ->with('images')
            ->orderBy('id')
            ->get();

        $events = WebsiteEvent::where('club_id', $clubId)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('AdminClubs/WebsiteContent/Index', [
            'carouselImages' => $images,
            'homeCards' => $homeCards,
            'cardCategories' => self::CARD_CATEGORIES,
            'virtualTourCategories' => $virtualTourCategories,
            'events' => $events,
            'eventTypes' => self::EVENT_TYPES,
        ]);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:20480',
                'dimensions:min_width=1200,min_height=800',
            ],
            'descriptions' => ['nullable', 'array', 'max:20'],
            'descriptions.*' => ['nullable', 'string', 'max:100'],
        ], [
            'images.required' => 'Selecciona al menos una imagen.',
            'images.max' => 'Puedes subir hasta 20 imágenes por carga.',
            'images.*.image' => 'Uno de los archivos no es una imagen válida.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada imagen debe pesar máximo 20 MB.',
            'images.*.dimensions' => 'Cada imagen debe medir al menos 1200 × 800 px.',
            'descriptions.*.max' => 'Cada descripción debe tener máximo 100 caracteres.',
        ]);

        $clubId = (int) session('club_id');
        $club = Club::findOrFail($clubId);
        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('images') as $index => $image) {
                $path = $this->uploadImage($image, $club->code, 'carousel');
                $uploadedPaths[] = $path;

                CarouselImage::create([
                    'club_id' => $clubId,
                    'image_path' => $path,
                    'description' => $validated['descriptions'][$index] ?? null,
                ]);
            }

            DB::commit();

            return back()->with('success', 'Imágenes guardadas correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                Storage::disk('spaces')->delete($path);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudieron guardar las imágenes.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function storeCard(Request $request) {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', self::CARD_CATEGORIES)],
            'images' => ['required', 'array', 'min:1', 'max:2'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:20480',
                'dimensions:min_width=1000,min_height=1000',
            ],
        ], [
            'category.required' => 'Selecciona una categoría.',
            'category.in' => 'La categoría seleccionada no es válida.',
            'images.required' => 'Selecciona al menos una imagen.',
            'images.max' => 'Cada categoría permite máximo 2 imágenes.',
            'images.*.image' => 'Uno de los archivos no es una imagen válida.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada imagen debe pesar máximo 20 MB.',
            'images.*.dimensions' => 'Cada imagen debe medir al menos 1000 × 1000 px.',
        ]);

        $clubId = (int) session('club_id');
        $currentCount = HomeCard::where('club_id', $clubId)
            ->where('category', $validated['category'])
            ->count();

        if ($currentCount + count($request->file('images')) > 2) {
            return back()->withErrors([
                'images' => 'Esta categoría permite máximo 2 imágenes.',
            ]);
        }

        $club = Club::findOrFail($clubId);
        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('images') as $image) {
                $path = $this->uploadImage($image, $club->code, 'home-cards', 1000, 1000);
                $uploadedPaths[] = $path;

                HomeCard::create([
                    'club_id' => $clubId,
                    'category' => $validated['category'],
                    'image_path' => $path,
                ]);
            }

            DB::commit();

            return back()->with('success', 'Cards guardadas correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                Storage::disk('spaces')->delete($path);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudieron guardar las cards.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyCard(int $id) {
        try {
            $card = HomeCard::where('club_id', session('club_id'))->findOrFail($id);

            if (Storage::disk('spaces')->exists($card->image_path)) {
                Storage::disk('spaces')->delete($card->image_path);
            }

            $card->delete();

            return back()->with('success', 'Card eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la card.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function storeVirtualTourCategory(Request $request) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ], [
            'name.required' => 'Escribe el nombre de la categoría.',
            'name.max' => 'El nombre debe tener máximo 60 caracteres.',
        ]);

        VirtualTourCategory::firstOrCreate([
            'club_id' => (int) session('club_id'),
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Categoría guardada correctamente.');
    }

    public function storeVirtualTourImages(Request $request) {
        $validated = $request->validate([
            'category_id' => ['required', 'integer'],
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:20480',
                'dimensions:min_width=1200,min_height=800',
            ],
            'titles' => ['required', 'array', 'max:6'],
            'titles.*' => ['required', 'string', 'max:100'],
        ], [
            'category_id.required' => 'Selecciona una categoría.',
            'images.required' => 'Selecciona al menos una imagen.',
            'images.max' => 'Cada categoría permite máximo 6 imágenes.',
            'images.*.image' => 'Uno de los archivos no es una imagen válida.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada imagen debe pesar máximo 20 MB.',
            'images.*.dimensions' => 'Cada imagen debe medir al menos 1200 × 800 px.',
            'titles.*.required' => 'Escribe el título de cada imagen.',
            'titles.*.max' => 'Cada título debe tener máximo 100 caracteres.',
        ]);

        $clubId = (int) session('club_id');
        $category = VirtualTourCategory::where('club_id', $clubId)
            ->findOrFail($validated['category_id']);

        if ($category->images()->count() + count($request->file('images')) > 6) {
            return back()->withErrors([
                'images' => 'Esta categoría permite máximo 6 imágenes.',
            ]);
        }

        $club = Club::findOrFail($clubId);
        $uploadedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($request->file('images') as $index => $image) {
                $path = $this->uploadImage($image, $club->code, 'virtual-tour');
                $uploadedPaths[] = $path;

                VirtualTourImage::create([
                    'category_id' => $category->id,
                    'title' => $validated['titles'][$index],
                    'image_path' => $path,
                ]);
            }

            DB::commit();

            return back()->with('success', 'Imágenes guardadas correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                Storage::disk('spaces')->delete($path);
            }

            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudieron guardar las imágenes.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyVirtualTourImage(int $id) {
        try {
            $image = VirtualTourImage::whereHas('category', function ($query) {
                $query->where('club_id', session('club_id'));
            })->findOrFail($id);

            if (Storage::disk('spaces')->exists($image->image_path)) {
                Storage::disk('spaces')->delete($image->image_path);
            }

            $image->delete();

            return back()->with('success', 'Imagen eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la imagen.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroyVirtualTourCategory(int $id) {
        try {
            $category = VirtualTourCategory::where('club_id', session('club_id'))
                ->with('images')
                ->findOrFail($id);

            if (in_array($category->name, self::VIRTUAL_TOUR_CATEGORIES)) {
                return back()->withErrors([
                    'messageError' => 'Las categorías predeterminadas no se pueden eliminar.',
                ]);
            }

            foreach ($category->images as $image) {
                if (Storage::disk('spaces')->exists($image->image_path)) {
                    Storage::disk('spaces')->delete($image->image_path);
                }
            }

            $category->delete();

            return back()->with('success', 'Categoría eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la categoría.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function saveEvent(Request $request) {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date'],
            'type' => ['required', 'in:activity,celebration,holiday'],
        ], [
            'title.required' => 'Escribe el título del evento.',
            'title.max' => 'El título debe tener máximo 100 caracteres.',
            'event_date.required' => 'Selecciona la fecha del evento.',
            'type.required' => 'Selecciona el tipo de evento.',
        ]);

        $clubId = (int) session('club_id');

        if (! empty($validated['id'])) {
            $event = WebsiteEvent::where('club_id', $clubId)->findOrFail($validated['id']);
            $event->update([
                'title' => $validated['title'],
                'event_date' => $validated['event_date'],
                'type' => $validated['type'],
            ]);

            return back()->with('success', 'Evento actualizado correctamente.');
        }

        WebsiteEvent::create([
            'club_id' => $clubId,
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'type' => $validated['type'],
        ]);

        return back()->with('success', 'Evento guardado correctamente.');
    }

    public function destroyEvent(int $id)
    {
        try {
            $event = WebsiteEvent::where('club_id', session('club_id'))->findOrFail($id);
            $event->delete();

            return back()->with('success', 'Evento eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar el evento.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(int $id)
    {
        try {
            $image = CarouselImage::where('club_id', session('club_id'))->findOrFail($id);

            if (Storage::disk('spaces')->exists($image->image_path)) {
                Storage::disk('spaces')->delete($image->image_path);
            }

            $image->delete();

            return back()->with('success', 'Imagen eliminada correctamente.');
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'messageError' => 'No se pudo eliminar la imagen.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function uploadImage(
        $file,
        string $clubCode,
        string $folder,
        int $targetWidth = 1200,
        int $targetHeight = 800
    ): string {
        $source = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $source) {
            throw new \Exception('La imagen no es válida.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $sourceX = (int) round(($sourceWidth - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $sourceX = 0;
            $sourceY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);

        imagecopyresampled(
            $destination,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        $temporaryPath = tempnam(sys_get_temp_dir(), 'carousel-');

        if (! $temporaryPath || ! imagewebp($destination, $temporaryPath, 82)) {
            imagedestroy($source);
            imagedestroy($destination);
            throw new \Exception('No se pudo convertir la imagen a WebP.');
        }

        $directory = "clubs/{$clubCode}/website/{$folder}";
        $filename = Str::uuid().'.webp';

        $stored = Storage::disk('spaces')->putFileAs(
            $directory,
            new File($temporaryPath),
            $filename,
            'public'
        );

        if (! $stored) {
            @unlink($temporaryPath);
            imagedestroy($source);
            imagedestroy($destination);
            throw new \Exception('No se pudo guardar la imagen.');
        }

        @unlink($temporaryPath);
        imagedestroy($source);
        imagedestroy($destination);

        return "{$directory}/{$filename}";
    }
}

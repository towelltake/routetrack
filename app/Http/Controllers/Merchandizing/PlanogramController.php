<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\VisualDetail;
use App\Models\VisualDetailTemp;
use App\Models\VisualHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PlanogramController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($this->hasTables()) {
            $records = VisualHeader::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('visualcode', 'like', '%' . $searchTerm . '%')
                            ->orWhere('visualdescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbvisualdescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('remarks', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('visualcode')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (VisualHeader $record) => $this->transformRow($record));
        } else {
            $records = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/planogram/Index', [
            'available' => $this->hasTables(),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'records' => $records,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->hasTables(), 404);

        $tempcode = request()->integer('tempcode');
        $visualcode = request()->integer('code') ?: $this->nextCode();

        return Inertia::render('merchandizing/planogram/FormPage', [
            'mode' => 'create',
            'formMeta' => $this->formMeta(),
            'planogramData' => [
                'visualcode' => $visualcode,
                'visualdescription' => '',
                'arbvisualdescription' => '',
                'remarks' => '',
                'tempcode' => $tempcode ?: $this->newTempCode(),
                'images' => $tempcode
                    ? VisualDetailTemp::query()
                        ->where('visualcode', $tempcode)
                        ->orderBy('visualdetail_id')
                        ->get()
                        ->map(fn (VisualDetailTemp $detail) => $this->transformImageRow($detail))
                        ->all()
                    : [],
            ],
        ]);
    }

    public function show(VisualHeader $planogram): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/planogram/FormPage', [
            'mode' => 'view',
            'formMeta' => $this->formMeta(),
            'planogramData' => $this->recordData($planogram),
        ]);
    }

    public function edit(VisualHeader $planogram): Response
    {
        abort_unless($this->hasTables(), 404);

        return Inertia::render('merchandizing/planogram/FormPage', [
            'mode' => 'edit',
            'formMeta' => $this->formMeta(),
            'planogramData' => $this->recordData($planogram),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $this->validatedData($request);
        $tempcode = (int) $request->validate([
            'tempcode' => ['required', 'integer'],
        ])['tempcode'];

        $record = DB::transaction(function () use ($data, $tempcode) {
            $record = VisualHeader::query()->create($data);

            VisualDetailTemp::query()
                ->where('visualcode', $tempcode)
                ->get()
                ->each(function (VisualDetailTemp $detail) use ($record) {
                    VisualDetail::query()->create([
                        'visualcode' => (int) $record->visualcode,
                        'imagename' => $detail->imagename,
                        'imagepath' => $detail->imagepath,
                        'imagedescription' => $detail->imagedescription,
                    ]);
                });

            VisualDetailTemp::query()->where('visualcode', $tempcode)->delete();

            return $record;
        });

        return redirect($this->formMeta()['indexUrl'])->with('success', 'Planogram created.');
    }

    public function update(Request $request, VisualHeader $planogram): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $planogram->update($this->validatedData($request));

        return redirect($this->formMeta()['indexUrl'])->with('success', 'Planogram updated.');
    }

    public function destroy(VisualHeader $planogram): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        DB::transaction(function () use ($planogram) {
            $details = VisualDetail::query()
                ->where('visualcode', $planogram->visualcode)
                ->get();

            foreach ($details as $detail) {
                $this->deleteImageFile($detail->imagename);
                $detail->delete();
            }

            $planogram->delete();
        });

        return back()->with('success', 'Planogram deleted.');
    }

    public function uploadImage(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $data = $request->validate([
            'visualcode' => ['required', 'integer'],
            'tempcode' => ['nullable', 'integer'],
            'imagedescription' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $filename = $this->storeUploadedFile($request, (int) $data['visualcode']);
        $imagePath = '/visualimages/';
        $description = $data['imagedescription'] === '' ? null : $data['imagedescription'];

        if (!empty($data['tempcode'])) {
            VisualDetailTemp::query()->create([
                'visualcode' => (int) $data['tempcode'],
                'imagename' => $filename,
                'imagepath' => $imagePath,
                'imagedescription' => $description,
            ]);

            return redirect("/merchandizing/planogram/create?tempcode={$data['tempcode']}&code={$data['visualcode']}")
                ->with('success', 'Planogram image added.');
        }

        VisualDetail::query()->create([
            'visualcode' => (int) $data['visualcode'],
            'imagename' => $filename,
            'imagepath' => $imagePath,
            'imagedescription' => $description,
        ]);

        return back()->with('success', 'Planogram image added.');
    }

    public function deleteImage(Request $request, ?VisualDetail $detail = null): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $tempcode = $request->integer('tempcode');

        if ($detail) {
            $this->deleteImageFile($detail->imagename);
            $detail->delete();

            return back()->with('success', 'Planogram image removed.');
        }

        $detailId = $request->validate([
            'detail_id' => ['required', 'integer'],
        ])['detail_id'];
        $visualcode = $request->integer('visualcode');

        $tempDetail = VisualDetailTemp::query()
            ->when($tempcode, fn ($query) => $query->where('visualcode', $tempcode))
            ->findOrFail($detailId);

        $this->deleteImageFile($tempDetail->imagename);
        $tempDetail->delete();

        return redirect("/merchandizing/planogram/create?tempcode={$tempcode}&code={$visualcode}")
            ->with('success', 'Planogram image removed.');
    }

    public function cleanupTemp(Request $request): RedirectResponse
    {
        abort_unless($this->hasTables(), 404);

        $tempcode = (int) $request->validate([
            'tempcode' => ['required', 'integer'],
        ])['tempcode'];

        $details = VisualDetailTemp::query()->where('visualcode', $tempcode)->get();

        foreach ($details as $detail) {
            $this->deleteImageFile($detail->imagename);
            $detail->delete();
        }

        return redirect($this->formMeta()['indexUrl']);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'visualdescription' => ['required', 'string', 'max:255'],
            'arbvisualdescription' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $data['arbvisualdescription'] = $data['arbvisualdescription'] === '' ? null : $data['arbvisualdescription'];
        $data['remarks'] = $data['remarks'] === '' ? null : $data['remarks'];

        return $data;
    }

    private function recordData(VisualHeader $record): array
    {
        return [
            'visualcode' => (int) $record->visualcode,
            'visualdescription' => $record->visualdescription ?? '',
            'arbvisualdescription' => $record->arbvisualdescription ?? '',
            'remarks' => $record->remarks ?? '',
            'tempcode' => null,
            'images' => VisualDetail::query()
                ->where('visualcode', $record->visualcode)
                ->orderBy('visualdetail_id')
                ->get()
                ->map(fn (VisualDetail $detail) => $this->transformImageRow($detail))
                ->all(),
        ];
    }

    private function transformRow(VisualHeader $record): array
    {
        return [
            'visualcode' => (int) $record->visualcode,
            'visualdescription' => $record->visualdescription ?? '',
            'arbvisualdescription' => $record->arbvisualdescription ?? '',
            'remarks' => $record->remarks ?? '',
            'image_count' => Schema::hasTable('visualdetail')
                ? VisualDetail::query()->where('visualcode', $record->visualcode)->count()
                : 0,
        ];
    }

    private function transformImageRow(VisualDetail|VisualDetailTemp $detail): array
    {
        $imagePath = trim((string) $detail->imagepath, '/');
        $imageUrl = $detail->imagename ? asset($imagePath . '/' . $detail->imagename) : '';

        return [
            'visualdetail_id' => (int) $detail->visualdetail_id,
            'imagedescription' => $detail->imagedescription ?? '',
            'imagename' => $detail->imagename ?? '',
            'imageurl' => $imageUrl,
        ];
    }

    private function hasTables(): bool
    {
        return Schema::hasTable('visualheader')
            && Schema::hasTable('visualdetail')
            && Schema::hasTable('visualdetail_temp');
    }

    private function nextCode(): int
    {
        return ((int) VisualHeader::query()->max('visualcode')) + 1;
    }

    private function newTempCode(): int
    {
        do {
            $tempcode = random_int(100000, 999999999);
        } while (VisualDetailTemp::query()->where('visualcode', $tempcode)->exists());

        return $tempcode;
    }

    private function imageDirectory(): string
    {
        return public_path('visualimages');
    }

    private function ensureImageDirectory(): void
    {
        File::ensureDirectoryExists($this->imageDirectory());
    }

    private function storeUploadedFile(Request $request, int $visualcode): string
    {
        $this->ensureImageDirectory();

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = $visualcode . '_visual_' . time() . '_' . substr(md5(uniqid((string) $visualcode, true)), 0, 8) . '.' . $extension;

        $file->move($this->imageDirectory(), $filename);

        return $filename;
    }

    private function deleteImageFile(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $path = $this->imageDirectory() . DIRECTORY_SEPARATOR . $filename;

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/planogram',
            'baseUrl' => '/merchandizing/planogram',
            'subtitle' => 'Manage planograms and merchandising images',
        ];
    }
}

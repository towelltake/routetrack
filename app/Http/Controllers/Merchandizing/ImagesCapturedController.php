<?php

namespace App\Http\Controllers\Merchandizing;

use App\Http\Controllers\Controller;
use App\Models\CustomerImage;
use App\Models\CustomerMaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ImagesCapturedController extends Controller
{
    public function index(): Response
    {
        $date = request('date');
        $routeCode = request('routecode');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $customerImageAlias = DB::getTablePrefix() . 'customerimages';

        if ($this->hasBaseTables()) {
            $query = CustomerImage::query()
                ->join('customermaster as customer', 'customer.customercode', '=', 'customerimages.customercode')
                ->select([
                    'customerimages.customercode',
                    'customer.customername',
                    'customer.alternatecode',
                    DB::raw('COUNT(*) AS image_count'),
                    DB::raw("MAX({$customerImageAlias}.transactiondate) AS last_transactiondate"),
                ])
                ->when($date, fn ($query) => $query->whereDate('customerimages.transactiondate', $date))
                ->when($routeCode, fn ($query) => $query->where('customerimages.routecode', $routeCode))
                ->groupBy('customerimages.customercode', 'customer.customername', 'customer.alternatecode')
                ->orderByDesc(DB::raw("MAX({$customerImageAlias}.transactiondate)"));

            $records = $query
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn ($record) => $this->transformRow($record));
        } else {
            $records = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        return Inertia::render('merchandizing/images-captured/Index', [
            'available' => $this->hasBaseTables(),
            'filters' => [
                'date' => $date,
                'routecode' => $routeCode ? (int) $routeCode : '',
                'per_page' => $perPage,
            ],
            'records' => $records,
            'formMeta' => $this->formMeta(),
        ]);
    }

    public function show(int $customerCode): Response
    {
        abort_unless($this->hasBaseTables(), 404);

        $customer = CustomerMaster::query()->findOrFail($customerCode);

        return Inertia::render('merchandizing/images-captured/FormPage', [
            'formMeta' => $this->formMeta(),
            'imageData' => [
                'customercode' => (int) $customer->customercode,
                'customername' => $customer->customername ?? '',
                'alternatecode' => $customer->alternatecode ?? '',
                'customerImages' => $this->customerImages((int) $customer->customercode),
                'planogramImages' => $this->planogramImages((int) $customer->customercode),
                'posImages' => $this->posImages((int) $customer->customercode),
                'surveyImages' => $this->surveyImages((int) $customer->customercode),
                'legacyMissing' => $this->legacyMissingFlags(),
            ],
        ]);
    }

    private function transformRow($record): array
    {
        return [
            'customercode' => (int) $record->customercode,
            'customername' => $record->customername ?? '',
            'alternatecode' => $record->alternatecode ?? '',
            'image_count' => (int) ($record->image_count ?? 0),
            'last_transactiondate' => $record->last_transactiondate
                ? date('d-m-Y', strtotime((string) $record->last_transactiondate))
                : '',
        ];
    }

    private function customerImages(int $customerCode): array
    {
        return CustomerImage::query()
            ->where('customercode', $customerCode)
            ->orderBy('transactiondate')
            ->orderBy('imageno')
            ->get()
            ->map(function (CustomerImage $image) {
                $url = $this->fileUrl('customerimage', $image->imagename);

                return [
                    'table_id' => (int) $image->table_id,
                    'imagename' => $image->imagename ?? '',
                    'remarks' => $image->remarks ?? '',
                    'imageurl' => $url,
                    'exists' => $this->fileExists('customerimage', $image->imagename),
                ];
            })
            ->all();
    }

    private function planogramImages(int $customerCode): array
    {
        if (!File::exists(public_path('visualcaptureimages'))) {
            return [];
        }

        return [];
    }

    private function posImages(int $customerCode): array
    {
        if (!File::exists(public_path('posimages'))) {
            return [];
        }

        return [];
    }

    private function surveyImages(int $customerCode): array
    {
        if (!File::exists(public_path('surveyimages'))) {
            return [];
        }

        return [];
    }

    private function hasBaseTables(): bool
    {
        return Schema::hasTable('customerimages') && Schema::hasTable('customermaster');
    }

    private function fileExists(string $folder, ?string $filename): bool
    {
        if (!$filename) {
            return false;
        }

        return File::exists(public_path($folder . DIRECTORY_SEPARATOR . $filename));
    }

    private function fileUrl(string $folder, ?string $filename): string
    {
        if (!$filename || !$this->fileExists($folder, $filename)) {
            return '';
        }

        return asset(trim($folder, '/') . '/' . $filename);
    }

    private function legacyMissingFlags(): array
    {
        return [
            'planogramCapturedFolder' => !File::exists(public_path('visualcaptureimages')),
            'posFolder' => !File::exists(public_path('posimages')),
            'surveyFolder' => !File::exists(public_path('surveyimages')),
            'visitControlTable' => !Schema::hasTable('customeroperationscontrol'),
        ];
    }

    private function formMeta(): array
    {
        return [
            'indexUrl' => '/merchandizing/images-captured',
            'baseUrl' => '/merchandizing/images-captured',
            'subtitle' => 'Review customer, planogram, POS, and survey images captured in the field',
            'routeOptions' => Schema::hasTable('routemaster')
                ? DB::table('routemaster')
                    ->orderBy('routecode')
                    ->get(['routecode', 'routename'])
                    ->map(fn ($route) => [
                        'id' => (int) $route->routecode,
                        'label' => trim(collect([$route->routecode, $route->routename])->filter()->implode(' - ')),
                    ])
                    ->all()
                : [],
        ];
    }
}

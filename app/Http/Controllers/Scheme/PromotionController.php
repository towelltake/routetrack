<?php

namespace App\Http\Controllers\Scheme;

use App\Http\Controllers\Controller;
use App\Models\PromotionControl;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(): Response
    {
        $search = request('search');
        $perPage = (int) request('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;
        $available = Schema::hasTable('promotioncontrol');

        if ($available) {
            $promotions = PromotionControl::query()
                ->when($search, function ($query, $searchTerm) {
                    $query->where(function ($inner) use ($searchTerm) {
                        $inner->where('promotiondescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('arbpromotiondescription', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promotionkey', 'like', '%' . $searchTerm . '%')
                            ->orWhere('promotionplannumber', 'like', '%' . $searchTerm . '%');
                    });
                })
                ->orderBy('promotionplannumber')
                ->paginate($perPage, [
                    'promotionplannumber',
                    'promotionkey',
                    'promotiondescription',
                    'arbpromotiondescription',
                    'promotiontypecode',
                    'startdate',
                    'enddate',
                    'status',
                ])
                ->withQueryString();
        } else {
            $promotions = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ],
            );
        }

        return Inertia::render('scheme/promotion/Index', [
            'available' => $available,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'promotions' => $promotions,
            'optionSets' => [
                'promotionTypeLabels' => [
                    1 => 'Standard Promotion',
                    2 => 'Fixed Promotion',
                ],
                'statusLabels' => [
                    0 => 'Inactive',
                    1 => 'Active',
                ],
            ],
        ]);
    }
}

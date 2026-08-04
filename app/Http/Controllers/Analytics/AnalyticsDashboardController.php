<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsDashboardService $service
    ) {
    }

    public function home(Request $request): Response|RedirectResponse
    {
        $boards = $this->boards($request);
        $firstAccessible = collect($boards)->firstWhere('canView', true);

        if ($firstAccessible) {
            return redirect()->to($firstAccessible['href'] . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
        }

        return Inertia::render('analytics/Dashboard', array_merge(
            $this->service->emptyState('overview'),
            ['boards' => $boards]
        ));
    }

    public function overview(Request $request): Response
    {
        return $this->renderBoard($request, 'overview');
    }

    public function sales(Request $request): Response
    {
        return $this->renderBoard($request, 'sales');
    }

    public function collections(Request $request): Response
    {
        return $this->renderBoard($request, 'collections');
    }

    public function inventory(Request $request): Response
    {
        return $this->renderBoard($request, 'inventory');
    }

    private function renderBoard(Request $request, string $board): Response
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'cmpycode' => ['nullable', 'integer'],
            'regionmstcode' => ['nullable', 'integer'],
            'depotcode' => ['nullable', 'integer'],
            'areacode' => ['nullable', 'integer'],
            'subareacode' => ['nullable', 'integer'],
            'routecode' => ['nullable', 'integer'],
        ]);

        return Inertia::render('analytics/Dashboard', array_merge(
            $this->service->build($request->user(), $board, $validated),
            ['boards' => $this->boards($request)]
        ));
    }

    private function boards(Request $request): array
    {
        $user = $request->user();

        return [
            [
                'key' => 'overview',
                'label' => __('ui.overview'),
                'href' => route('analytics.overview.index'),
                'canView' => $user?->hasFormPermission('analytics overview', 'view') ?? false,
            ],
            [
                'key' => 'sales',
                'label' => __('ui.sales'),
                'href' => route('analytics.sales.index'),
                'canView' => $user?->hasFormPermission('sales analytics', 'view') ?? false,
            ],
            [
                'key' => 'collections',
                'label' => __('ui.collections'),
                'href' => route('analytics.collections.index'),
                'canView' => $user?->hasFormPermission('collection analytics', 'view') ?? false,
            ],
            [
                'key' => 'inventory',
                'label' => __('ui.inventory'),
                'href' => route('analytics.inventory.index'),
                'canView' => $user?->hasFormPermission('inventory analytics', 'view') ?? false,
            ],
        ];
    }
}

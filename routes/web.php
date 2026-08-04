<?php

use App\Http\Controllers\Basic\BankController;
use App\Http\Controllers\Basic\AreaManagerController;
use App\Http\Controllers\Basic\BranchManagerController;
use App\Http\Controllers\Basic\CashDescriptionController;
use App\Http\Controllers\Basic\CompanyController as BasicCompanyController;
use App\Http\Controllers\Basic\CurrencyController;
use App\Http\Controllers\Account\MessageController as AccountMessageController;
use App\Http\Controllers\Account\CustomerChannelController as AccountCustomerChannelController;
use App\Http\Controllers\Account\CustomerCategoryController as AccountCustomerCategoryController;
use App\Http\Controllers\Account\CustomerController as AccountCustomerController;
use App\Http\Controllers\Account\CustomerAuthorizeGroupController as AccountCustomerAuthorizeGroupController;
use App\Http\Controllers\Account\AutoJpManagementController as AccountAutoJpManagementController;
use App\Http\Controllers\Account\SalesmanOtpController as AccountSalesmanOtpController;
use App\Http\Controllers\Account\CustomerTemplateController as AccountCustomerTemplateController;
use App\Http\Controllers\Account\CustomerSequenceController as AccountCustomerSequenceController;
use App\Http\Controllers\Account\SalesmanController as AccountSalesmanController;
use App\Http\Controllers\Account\SalesmanMessageController as AccountSalesmanMessageController;
use App\Http\Controllers\Account\SettlementController as AccountSettlementController;
use App\Http\Controllers\Account\TaxController as AccountTaxController;
use App\Http\Controllers\Account\TransactionController as AccountTransactionController;
use App\Http\Controllers\Basic\InventoryLocationController;
use App\Http\Controllers\Basic\NationalSalesManagerController;
use App\Http\Controllers\Merchandizing\CustomerPosLimitController as MerchandizingCustomerPosLimitController;
use App\Http\Controllers\Merchandizing\ImagesCapturedController as MerchandizingImagesCapturedController;
use App\Http\Controllers\Merchandizing\PlanogramController as MerchandizingPlanogramController;
use App\Http\Controllers\Merchandizing\PosInstructionController as MerchandizingPosInstructionController;
use App\Http\Controllers\Merchandizing\PosMasterController as MerchandizingPosMasterController;
use App\Http\Controllers\Merchandizing\SurveyController as MerchandizingSurveyController;
use App\Http\Controllers\Merchandizing\SurveyKeyController as MerchandizingSurveyKeyController;
use App\Http\Controllers\Merchandizing\SurveyPlanController as MerchandizingSurveyPlanController;
use App\Http\Controllers\Links\CategoryKeyController as LinksCategoryKeyController;
use App\Http\Controllers\Links\ActiveInactiveItemsController as LinksActiveInactiveItemsController;
use App\Http\Controllers\Links\OutletProductCodeController as LinksOutletProductCodeController;
use App\Http\Controllers\Links\ItemsGroupController as LinksItemsGroupController;
use App\Http\Controllers\Links\PlanogramKeyController as LinksPlanogramKeyController;
use App\Http\Controllers\Links\PromotionController as LinksPromotionController;
use App\Http\Controllers\Links\RouteItemGroupController as LinksRouteItemGroupController;
use App\Http\Controllers\Links\SpecialPriceController as LinksSpecialPriceController;
use App\Http\Controllers\Links\SurveyController as LinksSurveyController;
use App\Http\Controllers\Basic\RegionManagerController;
use App\Http\Controllers\Basic\ReasonController;
use App\Http\Controllers\Basic\SupervisorController;
use App\Http\Controllers\Inventory\CompanyGroupController;
use App\Http\Controllers\Inventory\DailySalesmanLoadController;
use App\Http\Controllers\Inventory\CompanyItemgroupController;
use App\Http\Controllers\Inventory\DeliveryController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\ItemgroupController;
use App\Http\Controllers\Inventory\MajorCategoryController;
use App\Http\Controllers\Inventory\SubMajorCategoryController;
use App\Http\Controllers\Inventory\TargetCommissionController;
use App\Http\Controllers\Inventory\TargetGroupController;
use App\Http\Controllers\Inventory\UomController;
use App\Http\Controllers\Operation\AreaController;
use App\Http\Controllers\Operation\CompanyController;
use App\Http\Controllers\Operation\DepotController;
use App\Http\Controllers\Operation\DeviceController;
use App\Http\Controllers\Operation\RegionController;
use App\Http\Controllers\Operation\RouteGroupController;
use App\Http\Controllers\Operation\RouteSettingTemplateController;
use App\Http\Controllers\Operation\RoutesController;
use App\Http\Controllers\Operation\SalesmanController;
use App\Http\Controllers\Operation\SubAreaController;
// ==================== IGNORE ====================
use App\Http\Controllers\Operation\VehicleController;
use App\Http\Controllers\Organisation\CategoryController;
use App\Http\Controllers\Organisation\ChannelController;
use App\Http\Controllers\Organisation\AreaController as OrganisationAreaController;
use App\Http\Controllers\Organisation\CountryController as OrganisationCountryController;
use App\Http\Controllers\Organisation\DepotController as OrganisationDepotController;
use App\Http\Controllers\Organisation\DeviceRegistrationController;
use App\Http\Controllers\Organisation\RegionController as OrganisationRegionController;
use App\Http\Controllers\Organisation\RouteController as OrganisationRouteController;
use App\Http\Controllers\Organisation\RouteCategoryController as OrganisationRouteCategoryController;
use App\Http\Controllers\Organisation\RouteTemplateController as OrganisationRouteTemplateController;
use App\Http\Controllers\Organisation\SubAreaController as OrganisationSubAreaController;
use App\Http\Controllers\Organisation\VanController as OrganisationVanController;
use App\Http\Controllers\Usermanagement\RoleController;
use App\Http\Controllers\Usermanagement\UserController;
use App\Http\Controllers\Usermanagement\UserTypeController;
use App\Http\Controllers\Usermanagement\UserPermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\BasicSetupController;
use App\Http\Controllers\Admin\ControlPanelController;
use App\Http\Controllers\Admin\EmailConfigurationController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Analytics\AnalyticsDashboardController;
use App\Http\Controllers\Api\DeviceBackupUploadController;
use App\Http\Controllers\Reports\RouteActivityController;
use App\Http\Controllers\Reports\RouteInventoryController;
use App\Http\Controllers\Reports\RouteDepositSummaryController;
use App\Http\Controllers\Reports\DepositSummaryController;
use App\Http\Controllers\Reports\FinalDepositController;
use App\Http\Controllers\Reports\ItemHistoryController;
use App\Http\Controllers\Reports\CollectionSummaryController;
use App\Http\Controllers\Reports\PaymentSummaryController;
use App\Http\Controllers\Reports\RouteVisitSummaryController;
use App\Http\Controllers\Reports\BadReturnSummaryController;
use App\Http\Controllers\Reports\DiscountSummaryController;
use App\Http\Controllers\Reports\PricingSummaryController;
use App\Http\Controllers\Reports\OrderSummaryController;
use App\Http\Controllers\Reports\PosTrackingController;
use App\Http\Controllers\Reports\SalesSummaryController;
use App\Http\Controllers\Reports\SurveyTrackingController;
use App\Http\Controllers\Reports\WasteStockController;
use App\Http\Controllers\Reports\AssetsAvailabilityController;
use App\Http\Controllers\Reports\MerchandizedStockController;
use App\Http\Controllers\Reports\RouteAgeingController;
use App\Http\Controllers\Reports\CustomerAgeingController;
use App\Http\Controllers\Reports\RoutePendingBalanceController;
use App\Http\Controllers\Reports\CustomerPendingBalanceController;
use App\Http\Controllers\Reports\RouteMonthlyRevenueController;
use App\Http\Controllers\Reports\SalesFreeSummaryController;
use App\Http\Controllers\Reports\ItemSalesSummaryController;
use App\Http\Controllers\Reports\ItemGroupWiseSalesController;
use App\Http\Controllers\Reports\RouteSummaryController;
use App\Http\Controllers\Reports\RouteTripAnalysisController;
use App\Http\Controllers\Inventory\RouteitemgroupController;
use App\Http\Controllers\Inventory\RouteItemGroupItemController;
use App\Http\Controllers\Scheme\PromoKeyController as SchemePromoKeyController;
use App\Http\Controllers\Scheme\PromoPlanController as SchemePromoPlanController;
use App\Http\Controllers\Scheme\LoyaltyGroupController as SchemeLoyaltyGroupController;
use App\Http\Controllers\Scheme\LoyaltyKeyController as SchemeLoyaltyKeyController;
use App\Http\Controllers\Scheme\LoyaltyPlanController as SchemeLoyaltyPlanController;
use App\Http\Controllers\Scheme\SpecialPriceKeyController as SchemeSpecialPriceKeyController;
use App\Http\Controllers\Scheme\SpecialPricePlanController as SchemeSpecialPricePlanController;
use App\Http\Controllers\Scheme\PromotionGroupController as SchemePromotionGroupController;
use App\Http\Controllers\Scheme\PromotionController as SchemePromotionController;
use App\Http\Controllers\Scheme\SupervisorFreeContractController as SchemeSupervisorFreeContractController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::post('/set-locale', function (\Illuminate\Http\Request $request) {
//     $request->validate([
//         'locale' => 'required|in:en,ar',
//     ]);

//     session(['locale' => $request->locale]);
//     app()->setLocale($request->locale);

//     return back();
// })->name('locale.set');

Route::post('/set-locale', function (\Illuminate\Http\Request $request) {
	$request->validate([
		'locale' => 'required|in:en,ar',
	]);

	session(['locale' => $request->locale]);

	return back();
});

Route::match(['GET', 'POST'], '/upload/upload.php', [DeviceBackupUploadController::class, 'upload'])
	->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

/*
 * |--------------------------------------------------------------------------
 * | Application Routes
 * |--------------------------------------------------------------------------
 */

Route::redirect('/', '/login')->name('welcome');

// Dashboard (Authenticated + Verified)
Route::get('/dashboard', [AnalyticsDashboardController::class, 'home'])
	->middleware(['auth'])
	->name('dashboard');

Route::middleware(['auth', 'form.access'])->prefix('analytics')->name('analytics.')->group(function () {
	Route::get('/overview', [AnalyticsDashboardController::class, 'overview'])->name('overview.index');
	Route::get('/sales', [AnalyticsDashboardController::class, 'sales'])->name('sales.index');
	Route::get('/collections', [AnalyticsDashboardController::class, 'collections'])->name('collections.index');
	Route::get('/inventory', [AnalyticsDashboardController::class, 'inventory'])->name('inventory.index');
});

// Dashboard - Profile (Authenticated)
Route::middleware('auth')->group(function () {
	Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
	Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
	Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
	Route::get('/', function () {
		abort_unless(auth()->user()?->hasFormPermission('reports'), 403);
		return Inertia::render('reports/Index');
	})->name('index');

	Route::get('/daily-report', function () {
		abort_unless(auth()->user()?->hasFormPermission('reports'), 403);
		return Inertia::render('reports/daily-report/Index');
	})->name('daily-report.index');

	Route::get('/merchandizing-report', function () {
		abort_unless(auth()->user()?->hasFormPermission('reports'), 403);
		return Inertia::render('reports/merchandizing-report/Index');
	})->name('merchandizing-report.index');

	Route::get('/accounts-report', function () {
		abort_unless(auth()->user()?->hasFormPermission('reports'), 403);
		return Inertia::render('reports/accounts-report/Index');
	})->name('accounts-report.index');

	Route::get('/data-analysis', function () {
		abort_unless(auth()->user()?->hasFormPermission('reports'), 403);
		return Inertia::render('reports/data-analysis/Index');
	})->name('data-analysis.index');

	Route::get('/daily-report/route-activity', RouteActivityController::class)->name('daily-report.route-activity');
	Route::get('/daily-report/route-activity/export/excel', [RouteActivityController::class, 'exportExcel'])->name('daily-report.route-activity.export.excel');
	Route::get('/daily-report/route-activity/export/pdf', [RouteActivityController::class, 'exportPdf'])->name('daily-report.route-activity.export.pdf');
	Route::get('/daily-report/route-inventory', RouteInventoryController::class)->name('daily-report.route-inventory');
	Route::get('/daily-report/route-inventory/export/excel', [RouteInventoryController::class, 'exportExcel'])->name('daily-report.route-inventory.export.excel');
	Route::get('/daily-report/route-inventory/export/pdf', [RouteInventoryController::class, 'exportPdf'])->name('daily-report.route-inventory.export.pdf');
	Route::get('/daily-report/route-deposit-summary', RouteDepositSummaryController::class)->name('daily-report.route-deposit-summary');
	Route::get('/daily-report/route-deposit-summary/export/excel', [RouteDepositSummaryController::class, 'exportExcel'])->name('daily-report.route-deposit-summary.export.excel');
	Route::get('/daily-report/route-deposit-summary/export/pdf', [RouteDepositSummaryController::class, 'exportPdf'])->name('daily-report.route-deposit-summary.export.pdf');
	Route::get('/daily-report/discount-summary', DiscountSummaryController::class)->name('daily-report.discount-summary');
	Route::get('/daily-report/discount-summary/export/excel', [DiscountSummaryController::class, 'exportExcel'])->name('daily-report.discount-summary.export.excel');
	Route::get('/daily-report/discount-summary/export/pdf', [DiscountSummaryController::class, 'exportPdf'])->name('daily-report.discount-summary.export.pdf');
	Route::get('/daily-report/pricing-summary', PricingSummaryController::class)->name('daily-report.pricing-summary');
	Route::get('/daily-report/pricing-summary/export/excel', [PricingSummaryController::class, 'exportExcel'])->name('daily-report.pricing-summary.export.excel');
	Route::get('/daily-report/pricing-summary/export/pdf', [PricingSummaryController::class, 'exportPdf'])->name('daily-report.pricing-summary.export.pdf');
	Route::get('/daily-report/route-trip-analysis', RouteTripAnalysisController::class)->name('daily-report.route-trip-analysis');
	Route::get('/daily-report/route-trip-analysis/export/excel', [RouteTripAnalysisController::class, 'exportExcel'])->name('daily-report.route-trip-analysis.export.excel');
	Route::get('/daily-report/route-trip-analysis/export/pdf', [RouteTripAnalysisController::class, 'exportPdf'])->name('daily-report.route-trip-analysis.export.pdf');

	Route::get('/daily-report/route-summary', RouteSummaryController::class)->name('daily-report.route-summary');
	Route::get('/daily-report/route-summary/export/excel', [RouteSummaryController::class, 'exportExcel'])->name('daily-report.route-summary.export.excel');
	Route::get('/daily-report/route-summary/export/pdf', [RouteSummaryController::class, 'exportPdf'])->name('daily-report.route-summary.export.pdf');

	Route::get('/transaction-report/sales-summary', SalesSummaryController::class)->name('transaction-report.sales-summary');
	Route::get('/transaction-report/sales-summary/export/excel', [SalesSummaryController::class, 'exportExcel'])->name('transaction-report.sales-summary.export.excel');
	Route::get('/transaction-report/sales-summary/export/pdf', [SalesSummaryController::class, 'exportPdf'])->name('transaction-report.sales-summary.export.pdf');
	Route::get('/transaction-report/deposit-summary', DepositSummaryController::class)->name('transaction-report.deposit-summary');
	Route::get('/transaction-report/deposit-summary/export/excel', [DepositSummaryController::class, 'exportExcel'])->name('transaction-report.deposit-summary.export.excel');
	Route::get('/transaction-report/deposit-summary/export/pdf', [DepositSummaryController::class, 'exportPdf'])->name('transaction-report.deposit-summary.export.pdf');
	Route::get('/transaction-report/order-summary', OrderSummaryController::class)->name('transaction-report.order-summary');
	Route::get('/transaction-report/order-summary/export/excel', [OrderSummaryController::class, 'exportExcel'])->name('transaction-report.order-summary.export.excel');
	Route::get('/transaction-report/order-summary/export/pdf', [OrderSummaryController::class, 'exportPdf'])->name('transaction-report.order-summary.export.pdf');
	Route::get('/transaction-report/collection-summary', CollectionSummaryController::class)->name('transaction-report.collection-summary');
	Route::get('/transaction-report/collection-summary/export/excel', [CollectionSummaryController::class, 'exportExcel'])->name('transaction-report.collection-summary.export.excel');
	Route::get('/transaction-report/collection-summary/export/pdf', [CollectionSummaryController::class, 'exportPdf'])->name('transaction-report.collection-summary.export.pdf');
	Route::get('/transaction-report/payment-summary', PaymentSummaryController::class)->name('transaction-report.payment-summary');
	Route::get('/transaction-report/payment-summary/export/excel', [PaymentSummaryController::class, 'exportExcel'])->name('transaction-report.payment-summary.export.excel');
	Route::get('/transaction-report/payment-summary/export/pdf', [PaymentSummaryController::class, 'exportPdf'])->name('transaction-report.payment-summary.export.pdf');
	Route::get('/transaction-report/final-deposit', FinalDepositController::class)->name('transaction-report.final-deposit');
	Route::get('/transaction-report/final-deposit/export/excel', [FinalDepositController::class, 'exportExcel'])->name('transaction-report.final-deposit.export.excel');
	Route::get('/transaction-report/final-deposit/export/pdf', [FinalDepositController::class, 'exportPdf'])->name('transaction-report.final-deposit.export.pdf');
	Route::get('/transaction-report/item-history', ItemHistoryController::class)->name('transaction-report.item-history');
	Route::get('/transaction-report/item-history/export/excel', [ItemHistoryController::class, 'exportExcel'])->name('transaction-report.item-history.export.excel');
	Route::get('/transaction-report/item-history/export/pdf', [ItemHistoryController::class, 'exportPdf'])->name('transaction-report.item-history.export.pdf');
	Route::get('/transaction-report/route-visit-summary', RouteVisitSummaryController::class)->name('transaction-report.route-visit-summary');
	Route::get('/transaction-report/route-visit-summary/export/excel', [RouteVisitSummaryController::class, 'exportExcel'])->name('transaction-report.route-visit-summary.export.excel');
	Route::get('/transaction-report/route-visit-summary/export/pdf', [RouteVisitSummaryController::class, 'exportPdf'])->name('transaction-report.route-visit-summary.export.pdf');
	Route::get('/transaction-report/bad-return-summary', BadReturnSummaryController::class)->name('transaction-report.bad-return-summary');
	Route::get('/transaction-report/bad-return-summary/export/excel', [BadReturnSummaryController::class, 'exportExcel'])->name('transaction-report.bad-return-summary.export.excel');
	Route::get('/transaction-report/bad-return-summary/export/pdf', [BadReturnSummaryController::class, 'exportPdf'])->name('transaction-report.bad-return-summary.export.pdf');
	Route::get('/merchandizing-report/pos-tracking', PosTrackingController::class)->name('merchandizing-report.pos-tracking');
	Route::get('/merchandizing-report/pos-tracking/export/excel', [PosTrackingController::class, 'exportExcel'])->name('merchandizing-report.pos-tracking.export.excel');
	Route::get('/merchandizing-report/pos-tracking/export/pdf', [PosTrackingController::class, 'exportPdf'])->name('merchandizing-report.pos-tracking.export.pdf');
	Route::get('/merchandizing-report/survey-tracking', SurveyTrackingController::class)->name('merchandizing-report.survey-tracking');
	Route::get('/merchandizing-report/survey-tracking/export/excel', [SurveyTrackingController::class, 'exportExcel'])->name('merchandizing-report.survey-tracking.export.excel');
	Route::get('/merchandizing-report/survey-tracking/export/pdf', [SurveyTrackingController::class, 'exportPdf'])->name('merchandizing-report.survey-tracking.export.pdf');
	Route::get('/merchandizing-report/waste-stock', WasteStockController::class)->name('merchandizing-report.waste-stock');
	Route::get('/merchandizing-report/waste-stock/export/excel', [WasteStockController::class, 'exportExcel'])->name('merchandizing-report.waste-stock.export.excel');
	Route::get('/merchandizing-report/waste-stock/export/pdf', [WasteStockController::class, 'exportPdf'])->name('merchandizing-report.waste-stock.export.pdf');
	Route::get('/merchandizing-report/assets-availability', AssetsAvailabilityController::class)->name('merchandizing-report.assets-availability');
	Route::get('/merchandizing-report/assets-availability/export/excel', [AssetsAvailabilityController::class, 'exportExcel'])->name('merchandizing-report.assets-availability.export.excel');
	Route::get('/merchandizing-report/assets-availability/export/pdf', [AssetsAvailabilityController::class, 'exportPdf'])->name('merchandizing-report.assets-availability.export.pdf');
	Route::get('/merchandizing-report/merchandized-stock', MerchandizedStockController::class)->name('merchandizing-report.merchandized-stock');
	Route::get('/merchandizing-report/merchandized-stock/export/excel', [MerchandizedStockController::class, 'exportExcel'])->name('merchandizing-report.merchandized-stock.export.excel');
	Route::get('/merchandizing-report/merchandized-stock/export/pdf', [MerchandizedStockController::class, 'exportPdf'])->name('merchandizing-report.merchandized-stock.export.pdf');
	Route::get('/accounts-report/route-ageing', RouteAgeingController::class)->name('accounts-report.route-ageing');
	Route::get('/accounts-report/route-ageing/export/excel', [RouteAgeingController::class, 'exportExcel'])->name('accounts-report.route-ageing.export.excel');
	Route::get('/accounts-report/route-ageing/export/pdf', [RouteAgeingController::class, 'exportPdf'])->name('accounts-report.route-ageing.export.pdf');
	Route::get('/accounts-report/customer-ageing', CustomerAgeingController::class)->name('accounts-report.customer-ageing');
	Route::get('/accounts-report/customer-ageing/export/excel', [CustomerAgeingController::class, 'exportExcel'])->name('accounts-report.customer-ageing.export.excel');
	Route::get('/accounts-report/customer-ageing/export/pdf', [CustomerAgeingController::class, 'exportPdf'])->name('accounts-report.customer-ageing.export.pdf');
	Route::get('/accounts-report/route-pending-balance', RoutePendingBalanceController::class)->name('accounts-report.route-pending-balance');
	Route::get('/accounts-report/route-pending-balance/export/excel', [RoutePendingBalanceController::class, 'exportExcel'])->name('accounts-report.route-pending-balance.export.excel');
	Route::get('/accounts-report/route-pending-balance/export/pdf', [RoutePendingBalanceController::class, 'exportPdf'])->name('accounts-report.route-pending-balance.export.pdf');
	Route::get('/accounts-report/customer-pending-balance', CustomerPendingBalanceController::class)->name('accounts-report.customer-pending-balance');
	Route::get('/accounts-report/customer-pending-balance/export/excel', [CustomerPendingBalanceController::class, 'exportExcel'])->name('accounts-report.customer-pending-balance.export.excel');
	Route::get('/accounts-report/customer-pending-balance/export/pdf', [CustomerPendingBalanceController::class, 'exportPdf'])->name('accounts-report.customer-pending-balance.export.pdf');
	Route::get('/data-analysis/route-monthly-revenue', RouteMonthlyRevenueController::class)->name('data-analysis.route-monthly-revenue');
	Route::get('/data-analysis/route-monthly-revenue/export/excel', [RouteMonthlyRevenueController::class, 'exportExcel'])->name('data-analysis.route-monthly-revenue.export.excel');
	Route::get('/data-analysis/route-monthly-revenue/export/pdf', [RouteMonthlyRevenueController::class, 'exportPdf'])->name('data-analysis.route-monthly-revenue.export.pdf');
	Route::get('/data-analysis/sales-free-summary', SalesFreeSummaryController::class)->name('data-analysis.sales-free-summary');
	Route::get('/data-analysis/sales-free-summary/export/excel', [SalesFreeSummaryController::class, 'exportExcel'])->name('data-analysis.sales-free-summary.export.excel');
	Route::get('/data-analysis/sales-free-summary/export/pdf', [SalesFreeSummaryController::class, 'exportPdf'])->name('data-analysis.sales-free-summary.export.pdf');
	Route::get('/data-analysis/item-sales-summary', ItemSalesSummaryController::class)->name('data-analysis.item-sales-summary');
	Route::get('/data-analysis/item-sales-summary/export/excel', [ItemSalesSummaryController::class, 'exportExcel'])->name('data-analysis.item-sales-summary.export.excel');
	Route::get('/data-analysis/item-sales-summary/export/pdf', [ItemSalesSummaryController::class, 'exportPdf'])->name('data-analysis.item-sales-summary.export.pdf');
	Route::get('/data-analysis/item-group-wise-sales', ItemGroupWiseSalesController::class)->name('data-analysis.item-group-wise-sales');
	Route::get('/data-analysis/item-group-wise-sales/export/excel', [ItemGroupWiseSalesController::class, 'exportExcel'])->name('data-analysis.item-group-wise-sales.export.excel');
	Route::get('/data-analysis/item-group-wise-sales/export/pdf', [ItemGroupWiseSalesController::class, 'exportPdf'])->name('data-analysis.item-group-wise-sales.export.pdf');
});

Route::middleware(['auth', 'form.access'])->prefix('settings')->name('settings.')->group(function () {
	Route::get('/basic-setup', [BasicSetupController::class, 'index'])->name('basic-setup');
	Route::put('/basic-setup', [BasicSetupController::class, 'update'])->name('basic-setup.update');
	Route::get('/control-panel', [ControlPanelController::class, 'index'])->name('control-panel');
	Route::put('/control-panel', [ControlPanelController::class, 'update'])->name('control-panel.update');
	Route::get('/email-configuration', [EmailConfigurationController::class, 'index'])->name('email-configuration');
	Route::put('/email-configuration', [EmailConfigurationController::class, 'update'])->name('email-configuration.update');
	Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates');
	Route::post('/email-templates', [EmailTemplateController::class, 'store'])->name('email-templates.store');
	Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
	Route::delete('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
});

Route::middleware(['auth', 'form.access'])->prefix('usermanagement')->name('usermanagement.')->group(function () {
	Route::get('user-master', [UserController::class, 'index'])->name('user-master.index');
	Route::post('user-master', [UserController::class, 'store'])->name('user-master.store');
	Route::put('user-master/{user}', [UserController::class, 'update'])->name('user-master.update');
	Route::delete('user-master/{user}', [UserController::class, 'destroy'])->name('user-master.destroy');

	// User Type
	Route::resource('user-type', UserTypeController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['user-type' => 'userType']);

	// User Permission
	Route::get('user-permission',         [UserPermissionController::class, 'index'])->name('user-permission.index');
	Route::get('user-permission/load',    [UserPermissionController::class, 'load'])->name('user-permission.load');
	Route::post('user-permission/save',   [UserPermissionController::class, 'save'])->name('user-permission.save');
});



Route::middleware(['auth', 'form.access'])->prefix('basic')->name('masters.')->group(function () {
	Route::resource('currency', CurrencyController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('company', BasicCompanyController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('bank', BankController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('areamanager', AreaManagerController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('branchmanager', BranchManagerController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('supervisor', SupervisorController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('cashdescription', CashDescriptionController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('inventorylocation', InventoryLocationController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('nationalsalesmanager', NationalSalesManagerController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('regionmanager', RegionManagerController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('reason', ReasonController::class)
		->only(['index', 'store', 'update', 'destroy']);

	// Route::resource('company', \App\Http\Controllers\Masters\CompanyController::class)
	//     ->only(['index','store','update','destroy']);
});

Route::middleware(['auth', 'form.access'])->prefix('operation')->name('operation.')->group(function () {
	Route::resource('region', RegionController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('device', DeviceController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('vehicle', VehicleController::class)
		->only(['index', 'store', 'update', 'destroy']);
	Route::resource('salesman', SalesmanController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('depot', DepotController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('area', AreaController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('subarea', SubAreaController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['subarea' => 'subarea']);

	Route::resource('routegroup', RouteGroupController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('routesettingtemplate', RouteSettingTemplateController::class)
		->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
	
	Route::resource('routes', RoutesController::class)
		->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});





Route::middleware(['auth', 'form.access'])->prefix('organisation')->name('organisation.')->group(function () {
	Route::resource('country', OrganisationCountryController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('region', OrganisationRegionController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('depot', OrganisationDepotController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('area', OrganisationAreaController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('subarea', OrganisationSubAreaController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('van', OrganisationVanController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('device-registration', DeviceRegistrationController::class)
		->only(['index', 'store', 'destroy'])
		->parameters(['device-registration' => 'deviceRegistration']);

	Route::resource('routecategory', OrganisationRouteCategoryController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::get('route/bulk-import/template', [OrganisationRouteController::class, 'downloadBulkImportTemplate'])
		->name('route-bulk-import.template');
	Route::post('route/bulk-import', [OrganisationRouteController::class, 'bulkImport'])
		->name('route-bulk-import.store');

	Route::resource('route', OrganisationRouteController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['route' => 'routeMaster']);

	Route::resource('routetemplate', OrganisationRouteTemplateController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['routetemplate' => 'routeTemplate']);

	Route::resource('channel', ChannelController::class)
		->only(['index', 'store', 'update', 'destroy']);

	Route::resource('category', CategoryController::class)
		->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'form.access'])->prefix('inventory')->name('inventory.')->group(function () {
	Route::get('routeitemgroup/item-options', [RouteitemgroupController::class, 'itemOptions'])
		->name('routeitemgroup.item-options');
	Route::get('delivery/route-meta', [DeliveryController::class, 'routeMeta'])
		->name('delivery.route-meta');
	Route::get('delivery/item-meta', [DeliveryController::class, 'itemMeta'])
		->name('delivery.item-meta');
	Route::get('delivery/number-status', [DeliveryController::class, 'deliveryNumberStatus'])
		->name('delivery.number-status');
	Route::post('delivery/lines', [DeliveryController::class, 'storeLine'])
		->name('delivery.lines.store');
	Route::put('delivery/lines/{line}', [DeliveryController::class, 'updateLine'])
		->whereNumber('line')
		->name('delivery.lines.update');
	Route::delete('delivery/lines/{line}', [DeliveryController::class, 'destroyLine'])
		->whereNumber('line')
		->name('delivery.lines.destroy');
	Route::get('dailysalesmanload/creation-meta', [DailySalesmanLoadController::class, 'creationMeta'])
		->name('dailysalesmanload.creation-meta');
	Route::get('dailysalesmanload/item-meta', [DailySalesmanLoadController::class, 'itemMeta'])
		->name('dailysalesmanload.item-meta');
	Route::post('dailysalesmanload/lines', [DailySalesmanLoadController::class, 'storeLine'])
		->name('dailysalesmanload.lines.store');
	Route::delete('dailysalesmanload/lines/{line}', [DailySalesmanLoadController::class, 'destroyLine'])
		->whereNumber('line')
		->name('dailysalesmanload.lines.destroy');
	Route::post('dailysalesmanload/populate', [DailySalesmanLoadController::class, 'populateLines'])
		->name('dailysalesmanload.populate');
	Route::get('dailysalesmanload/route-items', [DailySalesmanLoadController::class, 'routeItems'])
		->name('dailysalesmanload.route-items');

	Route::resource('companygroup', CompanyGroupController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['companygroup' => 'companygroup']);

	Route::resource('majorcategory', MajorCategoryController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['majorcategory' => 'majorcategory']);

	Route::resource('submajorcategory', SubMajorCategoryController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['submajorcategory' => 'submajorcategory']);

	Route::resource('itemgroup', ItemgroupController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['itemgroup' => 'itemgroup']);

	Route::get('item/bulk-import/template', [ItemController::class, 'downloadBulkImportTemplate'])
		->name('item-bulk-import.template');
	Route::post('item/bulk-import', [ItemController::class, 'bulkImport'])
		->name('item-bulk-import.store');

	Route::resource('item', ItemController::class)
		->only(['index', 'create', 'show', 'edit', 'store', 'update'])
		->parameters(['item' => 'item']);

	Route::resource('routeitemgroup', RouteitemgroupController::class)
		->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])
		->parameters(['routeitemgroup' => 'routeitemgroup']);

	Route::resource('dailysalesmanload', DailySalesmanLoadController::class)
		->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])
		->parameters(['dailysalesmanload' => 'document']);

	Route::resource('delivery', DeliveryController::class)
		->only(['index', 'create', 'show', 'edit', 'destroy'])
		->parameters(['delivery' => 'delivery']);

	Route::get('targetcommission/salesman-meta/{salesman}', [TargetCommissionController::class, 'salesmanMeta'])
		->whereNumber('salesman')
		->name('targetcommission.salesman-meta');
	Route::get('targetcommission/package-upc-status/{package}', [TargetCommissionController::class, 'packageUpcStatus'])
		->whereNumber('package')
		->name('targetcommission.package-upc-status');
	Route::post('targetcommission/line', [TargetCommissionController::class, 'storeLine'])->name('targetcommission.line.store');
	Route::put('targetcommission/line/{targetcommission}', [TargetCommissionController::class, 'updateLine'])
		->whereNumber('targetcommission')
		->name('targetcommission.line.update');
	Route::delete('targetcommission/line/{targetcommission}', [TargetCommissionController::class, 'destroyLine'])
		->whereNumber('targetcommission')
		->name('targetcommission.line.destroy');

	Route::resource('targetcommission', TargetCommissionController::class)
		->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])
		->parameters(['targetcommission' => 'targetcommission']);

	Route::resource('targetgroup', TargetGroupController::class)
		->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])
		->parameters(['targetgroup' => 'targetgroup']);
});

Route::middleware(['auth', 'form.access'])->prefix('account')->name('account.')->group(function () {
	Route::get('customer/bulk-import/template', [AccountCustomerController::class, 'downloadBulkImportTemplate'])
		->name('customer-bulk-import.template');
	Route::post('customer/bulk-import', [AccountCustomerController::class, 'bulkImport'])
		->name('customer-bulk-import.store');
	Route::resource('customer', AccountCustomerController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['customer' => 'customer']);

	Route::resource('customer-template', AccountCustomerTemplateController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['customer-template' => 'customerTemplate']);

	Route::get('customer-authorize-group/item-group-items', [AccountCustomerAuthorizeGroupController::class, 'itemGroupItems'])->name('customer-authorize-group.item-group-items');
	Route::resource('customer-authorize-group', AccountCustomerAuthorizeGroupController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['customer-authorize-group' => 'customerAuthorizeGroup']);

	Route::resource('tax', AccountTaxController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['tax' => 'tax']);

	Route::get('transaction', [AccountTransactionController::class, 'index'])->name('transaction.index');
	Route::get('transaction/month-close', [AccountTransactionController::class, 'monthClose'])->name('transaction.month-close');
	Route::post('transaction/month-close', [AccountTransactionController::class, 'storeMonthClose'])->name('transaction.month-close.store');
	Route::get('transaction/month-close/{routecode}/{year}/{month}', [AccountTransactionController::class, 'monthCloseView'])
		->whereNumber('routecode')
		->whereNumber('year')
		->whereNumber('month')
		->name('transaction.month-close.view');
	Route::get('transaction/gc-collection', [AccountTransactionController::class, 'gcCollection'])->name('transaction.gc-collection');
	Route::get('transaction/gc-collection/create', [AccountTransactionController::class, 'createGcCollection'])->name('transaction.gc-collection.create');
	Route::get('transaction/gc-collection/route-meta', [AccountTransactionController::class, 'gcCollectionRouteMeta'])->name('transaction.gc-collection.route-meta');
	Route::get('transaction/gc-collection/invoices', [AccountTransactionController::class, 'gcCollectionInvoices'])->name('transaction.gc-collection.invoices');
	Route::post('transaction/gc-collection', [AccountTransactionController::class, 'storeGcCollection'])->name('transaction.gc-collection.store');
	Route::get('transaction/gc-collection/{transactionkey}', [AccountTransactionController::class, 'showGcCollection'])
		->whereNumber('transactionkey')
		->name('transaction.gc-collection.show');
	Route::delete('transaction/gc-collection/{transactionkey}', [AccountTransactionController::class, 'destroyGcCollection'])
		->whereNumber('transactionkey')
		->name('transaction.gc-collection.destroy');
	Route::get('transaction/ho-collection', [AccountTransactionController::class, 'hoCollection'])->name('transaction.ho-collection');
	Route::get('transaction/ho-collection/create', [AccountTransactionController::class, 'createHoCollection'])->name('transaction.ho-collection.create');
	Route::get('transaction/ho-collection/route-meta', [AccountTransactionController::class, 'hoCollectionRouteMeta'])->name('transaction.ho-collection.route-meta');
	Route::get('transaction/ho-collection/invoices', [AccountTransactionController::class, 'hoCollectionInvoices'])->name('transaction.ho-collection.invoices');
	Route::post('transaction/ho-collection', [AccountTransactionController::class, 'storeHoCollection'])->name('transaction.ho-collection.store');
	Route::get('transaction/ho-collection/{transactionkey}', [AccountTransactionController::class, 'showHoCollection'])
		->whereNumber('transactionkey')
		->name('transaction.ho-collection.show');
	Route::delete('transaction/ho-collection/{transactionkey}', [AccountTransactionController::class, 'destroyHoCollection'])
		->whereNumber('transactionkey')
		->name('transaction.ho-collection.destroy');
	Route::get('transaction/debit-note/customer', [AccountTransactionController::class, 'debitNoteCustomer'])->name('transaction.debit-note.customer');
	Route::get('transaction/debit-note/customer/create', [AccountTransactionController::class, 'createDebitNoteCustomer'])->name('transaction.debit-note.customer.create');
	Route::get('transaction/debit-note/customer/route-meta', [AccountTransactionController::class, 'debitNoteCustomerRouteMeta'])->name('transaction.debit-note.customer.route-meta');
	Route::get('transaction/debit-note/customer/invoices', [AccountTransactionController::class, 'debitNoteCustomerInvoices'])->name('transaction.debit-note.customer.invoices');
	Route::get('transaction/debit-note/customer/invoice-detail', [AccountTransactionController::class, 'debitNoteCustomerInvoiceDetail'])->name('transaction.debit-note.customer.invoice-detail');
	Route::post('transaction/debit-note/customer', [AccountTransactionController::class, 'storeDebitNoteCustomer'])->name('transaction.debit-note.customer.store');
	Route::get('transaction/debit-note/customer/{transactionkey}', [AccountTransactionController::class, 'showDebitNoteCustomer'])
		->whereNumber('transactionkey')
		->name('transaction.debit-note.customer.show');
	Route::delete('transaction/debit-note/customer/{transactionkey}', [AccountTransactionController::class, 'destroyDebitNoteCustomer'])
		->whereNumber('transactionkey')
		->name('transaction.debit-note.customer.destroy');
	Route::get('transaction/debit-note/route', [AccountTransactionController::class, 'debitNoteRoute'])->name('transaction.debit-note.route');
	Route::get('transaction/debit-note/route/create', [AccountTransactionController::class, 'createDebitNoteRoute'])->name('transaction.debit-note.route.create');
	Route::get('transaction/debit-note/route/route-meta', [AccountTransactionController::class, 'debitNoteRouteMeta'])->name('transaction.debit-note.route.route-meta');
	Route::post('transaction/debit-note/route', [AccountTransactionController::class, 'storeDebitNoteRoute'])->name('transaction.debit-note.route.store');
	Route::get('transaction/debit-note/route/{transactionkey}', [AccountTransactionController::class, 'showDebitNoteRoute'])
		->whereNumber('transactionkey')
		->name('transaction.debit-note.route.show');
	Route::delete('transaction/debit-note/route/{transactionkey}', [AccountTransactionController::class, 'destroyDebitNoteRoute'])
		->whereNumber('transactionkey')
		->name('transaction.debit-note.route.destroy');
	Route::get('transaction/credit-note/customer', [AccountTransactionController::class, 'creditNoteCustomer'])->name('transaction.credit-note.customer');
	Route::get('transaction/credit-note/customer/create', [AccountTransactionController::class, 'createCreditNoteCustomer'])->name('transaction.credit-note.customer.create');
	Route::get('transaction/credit-note/customer/route-meta', [AccountTransactionController::class, 'creditNoteCustomerRouteMeta'])->name('transaction.credit-note.customer.route-meta');
	Route::get('transaction/credit-note/customer/invoices', [AccountTransactionController::class, 'creditNoteCustomerInvoices'])->name('transaction.credit-note.customer.invoices');
	Route::post('transaction/credit-note/customer', [AccountTransactionController::class, 'storeCreditNoteCustomer'])->name('transaction.credit-note.customer.store');
	Route::get('transaction/credit-note/customer/{transactionkey}', [AccountTransactionController::class, 'showCreditNoteCustomer'])
		->whereNumber('transactionkey')
		->name('transaction.credit-note.customer.show');
	Route::delete('transaction/credit-note/customer/{transactionkey}', [AccountTransactionController::class, 'destroyCreditNoteCustomer'])
		->whereNumber('transactionkey')
		->name('transaction.credit-note.customer.destroy');
	Route::get('transaction/credit-note/route', [AccountTransactionController::class, 'creditNoteRoute'])->name('transaction.credit-note.route');
	Route::get('transaction/credit-note/route/create', [AccountTransactionController::class, 'createCreditNoteRoute'])->name('transaction.credit-note.route.create');
	Route::get('transaction/credit-note/route/route-meta', [AccountTransactionController::class, 'creditNoteRouteMeta'])->name('transaction.credit-note.route.route-meta');
	Route::post('transaction/credit-note/route', [AccountTransactionController::class, 'storeCreditNoteRoute'])->name('transaction.credit-note.route.store');
	Route::get('transaction/credit-note/route/{transactionkey}', [AccountTransactionController::class, 'showCreditNoteRoute'])
		->whereNumber('transactionkey')
		->name('transaction.credit-note.route.show');
	Route::delete('transaction/credit-note/route/{transactionkey}', [AccountTransactionController::class, 'destroyCreditNoteRoute'])
		->whereNumber('transactionkey')
		->name('transaction.credit-note.route.destroy');
	Route::get('transaction/opening-balance', [AccountTransactionController::class, 'openingBalance'])->name('transaction.opening-balance');
	Route::get('transaction/opening-balance/create', [AccountTransactionController::class, 'createOpeningBalance'])->name('transaction.opening-balance.create');
	Route::get('transaction/opening-balance/route-meta', [AccountTransactionController::class, 'openingBalanceRouteMeta'])->name('transaction.opening-balance.route-meta');
	Route::post('transaction/opening-balance', [AccountTransactionController::class, 'storeOpeningBalance'])->name('transaction.opening-balance.store');
	Route::get('transaction/opening-balance/{transactionkey}', [AccountTransactionController::class, 'showOpeningBalance'])
		->whereNumber('transactionkey')
		->name('transaction.opening-balance.show');
	Route::delete('transaction/opening-balance/{transactionkey}', [AccountTransactionController::class, 'destroyOpeningBalance'])
		->whereNumber('transactionkey')
		->name('transaction.opening-balance.destroy');
	Route::get('settlement/cash-receipt', [AccountSettlementController::class, 'cashReceipt'])->name('settlement.cash-receipt');
	Route::get('settlement/cash-receipt/create', [AccountSettlementController::class, 'createCashReceipt'])->name('settlement.cash-receipt.create');
	Route::get('settlement/cash-receipt/route-meta', [AccountSettlementController::class, 'cashReceiptRouteMeta'])->name('settlement.cash-receipt.route-meta');
	Route::get('settlement/cash-receipt/populate', [AccountSettlementController::class, 'cashReceiptPopulate'])->name('settlement.cash-receipt.populate');
	Route::post('settlement/cash-receipt', [AccountSettlementController::class, 'storeCashReceipt'])->name('settlement.cash-receipt.store');
	Route::get('settlement/cash-receipt/{documentnumber}', [AccountSettlementController::class, 'showCashReceipt'])
		->whereNumber('documentnumber')
		->name('settlement.cash-receipt.show');
	Route::delete('settlement/cash-receipt/{documentnumber}', [AccountSettlementController::class, 'destroyCashReceipt'])
		->whereNumber('documentnumber')
		->name('settlement.cash-receipt.destroy');
	Route::get('settlement/pdc-clearance', [AccountSettlementController::class, 'pdcClearance'])->name('settlement.pdc-clearance');
	Route::get('settlement/pdc-clearance/create', [AccountSettlementController::class, 'createPdcClearance'])->name('settlement.pdc-clearance.create');
	Route::get('settlement/pdc-clearance/route-meta', [AccountSettlementController::class, 'pdcClearanceRouteMeta'])->name('settlement.pdc-clearance.route-meta');
	Route::get('settlement/pdc-clearance/populate', [AccountSettlementController::class, 'pdcClearancePopulate'])->name('settlement.pdc-clearance.populate');
	Route::post('settlement/pdc-clearance/clear', [AccountSettlementController::class, 'storePdcClearance'])->name('settlement.pdc-clearance.clear');
	Route::post('settlement/pdc-clearance/bounce', [AccountSettlementController::class, 'bouncePdcClearance'])->name('settlement.pdc-clearance.bounce');

	Route::get('customer-sequence', [AccountCustomerSequenceController::class, 'index'])->name('customer-sequence.index');
	Route::get('customer-sequence/sales-calendar', [AccountCustomerSequenceController::class, 'salesCalendar'])->name('customer-sequence.sales-calendar');
	Route::post('customer-sequence/sales-calendar', [AccountCustomerSequenceController::class, 'storeSalesCalendar'])->name('customer-sequence.sales-calendar.store');
	Route::get('customer-sequence/arrange', [AccountCustomerSequenceController::class, 'arrange'])->name('customer-sequence.arrange');
	Route::post('customer-sequence/arrange', [AccountCustomerSequenceController::class, 'storeArrange'])->name('customer-sequence.arrange.store');
	Route::get('customer-sequence/arrange/bulk-import/template', [AccountCustomerSequenceController::class, 'downloadArrangeTemplate'])->name('customer-sequence.arrange.bulk-import.template');
	Route::post('customer-sequence/arrange/bulk-import', [AccountCustomerSequenceController::class, 'bulkImportArrange'])->name('customer-sequence.arrange.bulk-import.store');
	Route::get('customer-sequence/add', [AccountCustomerSequenceController::class, 'add'])->name('customer-sequence.add');
	Route::post('customer-sequence/add', [AccountCustomerSequenceController::class, 'storeAdd'])->name('customer-sequence.add.store');
	Route::get('customer-sequence/route-sequence', [AccountCustomerSequenceController::class, 'routeSequence'])->name('customer-sequence.route-sequence');
	Route::post('customer-sequence/route-sequence', [AccountCustomerSequenceController::class, 'storeRouteSequence'])->name('customer-sequence.route-sequence.store');
	Route::get('customer-sequence/copy-sequence', [AccountCustomerSequenceController::class, 'copySequence'])->name('customer-sequence.copy-sequence');
	Route::post('customer-sequence/copy-sequence', [AccountCustomerSequenceController::class, 'storeCopySequence'])->name('customer-sequence.copy-sequence.store');
	Route::get('auto-jp-management', [AccountAutoJpManagementController::class, 'index'])->name('auto-jp.index');
	Route::post('auto-jp-management/generate', [AccountAutoJpManagementController::class, 'generate'])->name('auto-jp.generate');
	Route::post('auto-jp-management/{plan}/publish', [AccountAutoJpManagementController::class, 'publish'])
		->whereNumber('plan')
		->name('auto-jp.publish');
	Route::get('salesman-otp', [AccountSalesmanOtpController::class, 'index'])->name('salesman-otp.index');
	Route::match(['GET', 'POST'], 'salesman-otp/generate', [AccountSalesmanOtpController::class, 'generate'])->name('salesman-otp.generate');

	Route::resource('customer-category', AccountCustomerCategoryController::class)
		->only(['index', 'store', 'update', 'destroy'])
		->parameters(['customer-category' => 'customerCategory']);

	Route::resource('customer-channel', AccountCustomerChannelController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['customer-channel' => 'customerChannel']);

	Route::get('salesman/bulk-import/template', [AccountSalesmanController::class, 'downloadBulkImportTemplate'])
		->name('salesman-bulk-import.template');
	Route::post('salesman/bulk-import', [AccountSalesmanController::class, 'bulkImport'])
		->name('salesman-bulk-import.store');
	Route::resource('salesman', AccountSalesmanController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['salesman' => 'salesman']);

	Route::resource('customer-message', AccountMessageController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['customer-message' => 'message']);

	Route::resource('salesman-message', AccountSalesmanMessageController::class)
		->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
		->parameters(['salesman-message' => 'salesmanMessage']);
});

Route::middleware(['auth', 'form.access'])->prefix('transaction')->name('transaction.')->group(function () {
	Route::get('begin-opening-stock', [App\Http\Controllers\Account\BeginOpeningStockController::class, 'index'])->name('begin-opening-stock');
	Route::get('begin-opening-stock/{detailkey}', [App\Http\Controllers\Account\BeginOpeningStockController::class, 'show'])
		->whereNumber('detailkey')
		->name('begin-opening-stock.show');
	Route::get('load', [App\Http\Controllers\Transaction\LoadController::class, 'index'])->name('load');
	Route::get('load/{detailkey}', [App\Http\Controllers\Transaction\LoadController::class, 'show'])
		->whereNumber('detailkey')
		->name('load.show');
	Route::get('load-transfer', [App\Http\Controllers\Transaction\LoadTransferController::class, 'index'])->name('load-transfer');
	Route::get('load-transfer/{detailkey}', [App\Http\Controllers\Transaction\LoadTransferController::class, 'show'])
		->whereNumber('detailkey')
		->name('load-transfer.show');
	Route::get('customer-inventory', [App\Http\Controllers\Transaction\CustomerInventoryController::class, 'index'])->name('customer-inventory');
	Route::get('customer-inventory/{primaryId}', [App\Http\Controllers\Transaction\CustomerInventoryController::class, 'show'])
		->whereNumber('primaryId')
		->name('customer-inventory.show');
	Route::get('invoice', [App\Http\Controllers\Transaction\InvoiceController::class, 'index'])->name('invoice');
	Route::get('invoice/{transactionkey}/print', [App\Http\Controllers\Transaction\InvoiceController::class, 'print'])
		->whereNumber('transactionkey')
		->name('invoice.print');
	Route::get('invoice/{transactionkey}', [App\Http\Controllers\Transaction\InvoiceController::class, 'show'])
		->whereNumber('transactionkey')
		->name('invoice.show');
	Route::get('advance-payment', [App\Http\Controllers\Transaction\AdvancePaymentController::class, 'index'])->name('advance-payment');
	Route::get('advance-payment/{transactionkey}', [App\Http\Controllers\Transaction\AdvancePaymentController::class, 'show'])
		->whereNumber('transactionkey')
		->name('advance-payment.show');
	Route::get('ar-collection', [App\Http\Controllers\Transaction\ArCollectionController::class, 'index'])->name('ar-collection');
	Route::get('ar-collection/{transactionkey}', [App\Http\Controllers\Transaction\ArCollectionController::class, 'show'])
		->whereNumber('transactionkey')
		->name('ar-collection.show');
	Route::get('unload-inventory', [App\Http\Controllers\Transaction\UnloadInventoryController::class, 'index'])->name('unload-inventory');
	Route::get('unload-inventory/{detailkey}', [App\Http\Controllers\Transaction\UnloadInventoryController::class, 'show'])
		->whereNumber('detailkey')
		->name('unload-inventory.show');
	Route::get('unload-variance', [App\Http\Controllers\Transaction\UnloadVarianceController::class, 'index'])->name('unload-variance');
	Route::get('unload-variance/{detailkey}', [App\Http\Controllers\Transaction\UnloadVarianceController::class, 'show'])
		->whereNumber('detailkey')
		->name('unload-variance.show');
	Route::get('damage-return', [App\Http\Controllers\Transaction\DamageReturnController::class, 'index'])->name('damage-return');
	Route::get('damage-return/{transactionkey}', [App\Http\Controllers\Transaction\DamageReturnController::class, 'show'])
		->whereNumber('transactionkey')
		->name('damage-return.show');
	Route::get('inventory-summary', [App\Http\Controllers\Transaction\InventorySummaryController::class, 'index'])->name('inventory-summary');
	Route::get('inventory-summary/{routekey}', [App\Http\Controllers\Transaction\InventorySummaryController::class, 'show'])
		->whereNumber('routekey')
		->name('inventory-summary.show');
	Route::get('sales-order', [App\Http\Controllers\Transaction\SalesOrderController::class, 'index'])->name('sales-order');
	Route::get('sales-order/{transactionkey}/print', [App\Http\Controllers\Transaction\SalesOrderController::class, 'print'])
		->whereNumber('transactionkey')
		->name('sales-order.print');
	Route::get('sales-order/{transactionkey}', [App\Http\Controllers\Transaction\SalesOrderController::class, 'show'])
		->whereNumber('transactionkey')
		->name('sales-order.show');
	Route::get('load-request', [App\Http\Controllers\Transaction\LoadRequestController::class, 'index'])->name('load-request');
	Route::get('load-request/create', [App\Http\Controllers\Transaction\LoadRequestController::class, 'create'])->name('load-request.create');
	Route::get('load-request/route-meta', [App\Http\Controllers\Transaction\LoadRequestController::class, 'routeMeta'])->name('load-request.route-meta');
	Route::get('load-request/item-meta', [App\Http\Controllers\Transaction\LoadRequestController::class, 'itemMeta'])->name('load-request.item-meta');
	Route::post('load-request/line', [App\Http\Controllers\Transaction\LoadRequestController::class, 'storeLine'])->name('load-request.lines.store');
	Route::put('load-request/line/{line}', [App\Http\Controllers\Transaction\LoadRequestController::class, 'updateLine'])
		->whereNumber('line')
		->name('load-request.lines.update');
	Route::patch('load-request/{detailkey}', [App\Http\Controllers\Transaction\LoadRequestController::class, 'updateHeader'])
		->whereNumber('detailkey')
		->name('load-request.update');
	Route::get('load-request/{detailkey}', [App\Http\Controllers\Transaction\LoadRequestController::class, 'show'])
		->whereNumber('detailkey')
		->name('load-request.show');
});

Route::middleware(['auth', 'form.access'])->prefix('merchandizing')->name('merchandizing.')->group(function () {
	Route::get('planogram', [MerchandizingPlanogramController::class, 'index'])->name('planogram.index');
	Route::get('planogram/create', [MerchandizingPlanogramController::class, 'create'])->name('planogram.create');
	Route::post('planogram', [MerchandizingPlanogramController::class, 'store'])->name('planogram.store');
	Route::post('planogram/images', [MerchandizingPlanogramController::class, 'uploadImage'])->name('planogram.images.store');
	Route::delete('planogram/images/temp', [MerchandizingPlanogramController::class, 'deleteImage'])->name('planogram.images.temp.destroy');
	Route::delete('planogram/images/{detail}', [MerchandizingPlanogramController::class, 'deleteImage'])->name('planogram.images.destroy');
	Route::post('planogram/cleanup-temp', [MerchandizingPlanogramController::class, 'cleanupTemp'])->name('planogram.cleanup-temp');
	Route::get('planogram/{planogram}', [MerchandizingPlanogramController::class, 'show'])->name('planogram.show');
	Route::get('planogram/{planogram}/edit', [MerchandizingPlanogramController::class, 'edit'])->name('planogram.edit');
	Route::put('planogram/{planogram}', [MerchandizingPlanogramController::class, 'update'])->name('planogram.update');
	Route::delete('planogram/{planogram}', [MerchandizingPlanogramController::class, 'destroy'])->name('planogram.destroy');
	Route::get('images-captured', [MerchandizingImagesCapturedController::class, 'index'])->name('images-captured.index');
	Route::get('images-captured/{customerCode}', [MerchandizingImagesCapturedController::class, 'show'])->name('images-captured.show');
	Route::get('survey', [MerchandizingSurveyController::class, 'index'])->name('survey.index');
	Route::get('survey/create', [MerchandizingSurveyController::class, 'create'])->name('survey.create');
	Route::post('survey', [MerchandizingSurveyController::class, 'store'])->name('survey.store');
	Route::get('survey/lookup-index/create', [MerchandizingSurveyController::class, 'lookupIndexManagerCreate'])->name('survey.lookup-index.create');
	Route::get('survey/lookup-index/{lookupIndex}/edit', [MerchandizingSurveyController::class, 'lookupIndexManagerEdit'])->name('survey.lookup-index.edit');
	Route::post('survey/lookup-index', [MerchandizingSurveyController::class, 'lookupIndexManagerStore'])->name('survey.lookup-index.store');
	Route::put('survey/lookup-index/{lookupIndex}', [MerchandizingSurveyController::class, 'lookupIndexManagerUpdate'])->name('survey.lookup-index.update');
	Route::post('survey/lookup-index/{lookupIndex}/details', [MerchandizingSurveyController::class, 'lookupIndexDetailStore'])->name('survey.lookup-index.details.store');
	Route::put('survey/lookup-index/{lookupIndex}/details/{detail}', [MerchandizingSurveyController::class, 'lookupIndexDetailUpdate'])->name('survey.lookup-index.details.update');
	Route::delete('survey/lookup-index/{lookupIndex}/details/{detail}', [MerchandizingSurveyController::class, 'lookupIndexDetailDestroy'])->name('survey.lookup-index.details.destroy');
	Route::get('survey/{survey}', [MerchandizingSurveyController::class, 'show'])->name('survey.show');
	Route::get('survey/{survey}/edit', [MerchandizingSurveyController::class, 'edit'])->name('survey.edit');
	Route::put('survey/{survey}', [MerchandizingSurveyController::class, 'update'])->name('survey.update');
	Route::delete('survey/{survey}', [MerchandizingSurveyController::class, 'destroy'])->name('survey.destroy');
	Route::get('survey-plan', [MerchandizingSurveyPlanController::class, 'index'])->name('survey-plan.index');
	Route::get('survey-plan/create', [MerchandizingSurveyPlanController::class, 'create'])->name('survey-plan.create');
	Route::post('survey-plan', [MerchandizingSurveyPlanController::class, 'store'])->name('survey-plan.store');
	Route::get('survey-plan/{surveyPlan}', [MerchandizingSurveyPlanController::class, 'show'])->name('survey-plan.show');
	Route::get('survey-plan/{surveyPlan}/edit', [MerchandizingSurveyPlanController::class, 'edit'])->name('survey-plan.edit');
	Route::put('survey-plan/{surveyPlan}', [MerchandizingSurveyPlanController::class, 'update'])->name('survey-plan.update');
	Route::delete('survey-plan/{surveyPlan}', [MerchandizingSurveyPlanController::class, 'destroy'])->name('survey-plan.destroy');
	Route::get('survey-key', [MerchandizingSurveyKeyController::class, 'index'])->name('survey-key.index');
	Route::get('survey-key/create', [MerchandizingSurveyKeyController::class, 'create'])->name('survey-key.create');
	Route::post('survey-key', [MerchandizingSurveyKeyController::class, 'store'])->name('survey-key.store');
	Route::get('survey-key/{surveyKey}', [MerchandizingSurveyKeyController::class, 'show'])->name('survey-key.show');
	Route::get('survey-key/{surveyKey}/edit', [MerchandizingSurveyKeyController::class, 'edit'])->name('survey-key.edit');
	Route::put('survey-key/{surveyKey}', [MerchandizingSurveyKeyController::class, 'update'])->name('survey-key.update');
	Route::delete('survey-key/{surveyKey}', [MerchandizingSurveyKeyController::class, 'destroy'])->name('survey-key.destroy');
	Route::get('pos-master', [MerchandizingPosMasterController::class, 'index'])->name('pos-master.index');
	Route::get('pos-master/create', [MerchandizingPosMasterController::class, 'create'])->name('pos-master.create');
	Route::post('pos-master', [MerchandizingPosMasterController::class, 'store'])->name('pos-master.store');
	Route::get('pos-master/{posMaster}', [MerchandizingPosMasterController::class, 'show'])->name('pos-master.show');
	Route::get('pos-master/{posMaster}/edit', [MerchandizingPosMasterController::class, 'edit'])->name('pos-master.edit');
	Route::put('pos-master/{posMaster}', [MerchandizingPosMasterController::class, 'update'])->name('pos-master.update');
	Route::delete('pos-master/{posMaster}', [MerchandizingPosMasterController::class, 'destroy'])->name('pos-master.destroy');
	Route::get('customer-pos-limit', [MerchandizingCustomerPosLimitController::class, 'index'])->name('customer-pos-limit.index');
	Route::get('customer-pos-limit/create', [MerchandizingCustomerPosLimitController::class, 'create'])->name('customer-pos-limit.create');
	Route::post('customer-pos-limit', [MerchandizingCustomerPosLimitController::class, 'store'])->name('customer-pos-limit.store');
	Route::post('customer-pos-limit/{customerPosLimit}/details', [MerchandizingCustomerPosLimitController::class, 'detailStore'])->name('customer-pos-limit.details.store');
	Route::put('customer-pos-limit/{customerPosLimit}/details/{detail}', [MerchandizingCustomerPosLimitController::class, 'detailUpdate'])->name('customer-pos-limit.details.update');
	Route::delete('customer-pos-limit/{customerPosLimit}/details/{detail}', [MerchandizingCustomerPosLimitController::class, 'detailDestroy'])->name('customer-pos-limit.details.destroy');
	Route::get('customer-pos-limit/{customerPosLimit}', [MerchandizingCustomerPosLimitController::class, 'show'])->name('customer-pos-limit.show');
	Route::get('customer-pos-limit/{customerPosLimit}/edit', [MerchandizingCustomerPosLimitController::class, 'edit'])->name('customer-pos-limit.edit');
	Route::put('customer-pos-limit/{customerPosLimit}', [MerchandizingCustomerPosLimitController::class, 'update'])->name('customer-pos-limit.update');
	Route::delete('customer-pos-limit/{customerPosLimit}', [MerchandizingCustomerPosLimitController::class, 'destroy'])->name('customer-pos-limit.destroy');
	Route::get('pos-instruction', [MerchandizingPosInstructionController::class, 'index'])->name('pos-instruction.index');
	Route::get('pos-instruction/create', [MerchandizingPosInstructionController::class, 'create'])->name('pos-instruction.create');
	Route::post('pos-instruction', [MerchandizingPosInstructionController::class, 'store'])->name('pos-instruction.store');
	Route::get('pos-instruction/{posInstruction}', [MerchandizingPosInstructionController::class, 'show'])->name('pos-instruction.show');
	Route::get('pos-instruction/{posInstruction}/edit', [MerchandizingPosInstructionController::class, 'edit'])->name('pos-instruction.edit');
	Route::put('pos-instruction/{posInstruction}', [MerchandizingPosInstructionController::class, 'update'])->name('pos-instruction.update');
	Route::delete('pos-instruction/{posInstruction}', [MerchandizingPosInstructionController::class, 'destroy'])->name('pos-instruction.destroy');
});

Route::middleware(['auth', 'form.access'])->prefix('links')->name('links.')->group(function () {
	Route::get('active-inactive-items', [LinksActiveInactiveItemsController::class, 'index'])->name('active-inactive-items.index');
	Route::get('active-inactive-items/load', [LinksActiveInactiveItemsController::class, 'load'])->name('active-inactive-items.load');
	Route::post('active-inactive-items/save', [LinksActiveInactiveItemsController::class, 'save'])->name('active-inactive-items.save');
	Route::get('category-key', [LinksCategoryKeyController::class, 'index'])->name('category-key.index');
	Route::get('category-key/load', [LinksCategoryKeyController::class, 'load'])->name('category-key.load');
	Route::post('category-key/save', [LinksCategoryKeyController::class, 'save'])->name('category-key.save');
	Route::get('items-group', [LinksItemsGroupController::class, 'index'])->name('items-group.index');
	Route::get('items-group/load', [LinksItemsGroupController::class, 'load'])->name('items-group.load');
	Route::post('items-group/save', [LinksItemsGroupController::class, 'save'])->name('items-group.save');
	Route::get('outlet-product-code', [LinksOutletProductCodeController::class, 'index'])->name('outlet-product-code.index');
	Route::get('outlet-product-code/load', [LinksOutletProductCodeController::class, 'load'])->name('outlet-product-code.load');
	Route::post('outlet-product-code/save', [LinksOutletProductCodeController::class, 'save'])->name('outlet-product-code.save');
	Route::get('planogram-key', [LinksPlanogramKeyController::class, 'index'])->name('planogram-key.index');
	Route::get('planogram-key/load', [LinksPlanogramKeyController::class, 'load'])->name('planogram-key.load');
	Route::post('planogram-key/save', [LinksPlanogramKeyController::class, 'save'])->name('planogram-key.save');
	Route::get('route-item-group', [LinksRouteItemGroupController::class, 'index'])->name('route-item-group.index');
	Route::get('route-item-group/load', [LinksRouteItemGroupController::class, 'load'])->name('route-item-group.load');
	Route::post('route-item-group/save', [LinksRouteItemGroupController::class, 'save'])->name('route-item-group.save');
	Route::get('promotion', [LinksPromotionController::class, 'index'])->name('promotion.index');
	Route::get('promotion/load', [LinksPromotionController::class, 'load'])->name('promotion.load');
	Route::post('promotion/save', [LinksPromotionController::class, 'save'])->name('promotion.save');
	Route::get('special-price', [LinksSpecialPriceController::class, 'index'])->name('special-price.index');
	Route::get('special-price/load', [LinksSpecialPriceController::class, 'load'])->name('special-price.load');
	Route::post('special-price/save', [LinksSpecialPriceController::class, 'save'])->name('special-price.save');
	Route::get('survey', [LinksSurveyController::class, 'index'])->name('survey.index');
	Route::get('survey/load', [LinksSurveyController::class, 'load'])->name('survey.load');
	Route::post('survey/save', [LinksSurveyController::class, 'save'])->name('survey.save');
});

	Route::middleware(['auth', 'form.access'])->prefix('scheme')->name('scheme.')->group(function () {
		Route::get('promotion', [SchemePromotionController::class, 'index'])->name('promotion.index');
		Route::get('promotion/promo-plan', [SchemePromoPlanController::class, 'index'])->name('promotion.promo-plan.index');
		Route::get('promotion/promo-plan/create', [SchemePromoPlanController::class, 'create'])->name('promotion.promo-plan.create');
		Route::post('promotion/promo-plan', [SchemePromoPlanController::class, 'store'])->name('promotion.promo-plan.store');
		Route::get('promotion/promo-plan/{promoPlan}', [SchemePromoPlanController::class, 'show'])->name('promotion.promo-plan.show');
		Route::get('promotion/promo-plan/{promoPlan}/edit', [SchemePromoPlanController::class, 'edit'])->name('promotion.promo-plan.edit');
		Route::put('promotion/promo-plan/{promoPlan}', [SchemePromoPlanController::class, 'update'])->name('promotion.promo-plan.update');
		Route::delete('promotion/promo-plan/{promoPlan}', [SchemePromoPlanController::class, 'destroy'])->name('promotion.promo-plan.destroy');
		Route::get('promotion/promo-key', [SchemePromoKeyController::class, 'index'])->name('promotion.promo-key.index');
		Route::get('promotion/promo-key/create', [SchemePromoKeyController::class, 'create'])->name('promotion.promo-key.create');
		Route::post('promotion/promo-key', [SchemePromoKeyController::class, 'store'])->name('promotion.promo-key.store');
		Route::get('promotion/promo-key/{promoKey}', [SchemePromoKeyController::class, 'show'])->name('promotion.promo-key.show');
		Route::get('promotion/promo-key/{promoKey}/edit', [SchemePromoKeyController::class, 'edit'])->name('promotion.promo-key.edit');
		Route::put('promotion/promo-key/{promoKey}', [SchemePromoKeyController::class, 'update'])->name('promotion.promo-key.update');
		Route::delete('promotion/promo-key/{promoKey}', [SchemePromoKeyController::class, 'destroy'])->name('promotion.promo-key.destroy');
		Route::get('special-price/pricing-plan', [SchemeSpecialPricePlanController::class, 'index'])->name('special-price.pricing-plan.index');
		Route::get('special-price/pricing-plan/create', [SchemeSpecialPricePlanController::class, 'create'])->name('special-price.pricing-plan.create');
		Route::post('special-price/pricing-plan', [SchemeSpecialPricePlanController::class, 'store'])->name('special-price.pricing-plan.store');
		Route::get('special-price/pricing-plan/{specialPricePlan}', [SchemeSpecialPricePlanController::class, 'show'])->name('special-price.pricing-plan.show');
		Route::get('special-price/pricing-plan/{specialPricePlan}/edit', [SchemeSpecialPricePlanController::class, 'edit'])->name('special-price.pricing-plan.edit');
		Route::put('special-price/pricing-plan/{specialPricePlan}', [SchemeSpecialPricePlanController::class, 'update'])->name('special-price.pricing-plan.update');
		Route::delete('special-price/pricing-plan/{specialPricePlan}', [SchemeSpecialPricePlanController::class, 'destroy'])->name('special-price.pricing-plan.destroy');
		Route::get('special-price/pricing-key', [SchemeSpecialPriceKeyController::class, 'index'])->name('special-price.pricing-key.index');
		Route::get('special-price/pricing-key/create', [SchemeSpecialPriceKeyController::class, 'create'])->name('special-price.pricing-key.create');
		Route::post('special-price/pricing-key', [SchemeSpecialPriceKeyController::class, 'store'])->name('special-price.pricing-key.store');
		Route::get('special-price/pricing-key/{specialPriceKey}', [SchemeSpecialPriceKeyController::class, 'show'])->name('special-price.pricing-key.show');
	Route::get('special-price/pricing-key/{specialPriceKey}/edit', [SchemeSpecialPriceKeyController::class, 'edit'])->name('special-price.pricing-key.edit');
	Route::post('special-price/pricing-key/{specialPriceKey}', [SchemeSpecialPriceKeyController::class, 'update'])->name('special-price.pricing-key.update-post');
	Route::put('special-price/pricing-key/{specialPriceKey}', [SchemeSpecialPriceKeyController::class, 'update'])->name('special-price.pricing-key.update');
	Route::delete('special-price/pricing-key/{specialPriceKey}', [SchemeSpecialPriceKeyController::class, 'destroy'])->name('special-price.pricing-key.destroy');
	Route::get('loyalty/loyalty-group/item-group-items', [SchemeLoyaltyGroupController::class, 'itemGroupItems'])->name('loyalty.loyalty-group.item-group-items');
	Route::get('loyalty/loyalty-group', [SchemeLoyaltyGroupController::class, 'index'])->name('loyalty.loyalty-group.index');
	Route::get('loyalty/loyalty-group/create', [SchemeLoyaltyGroupController::class, 'create'])->name('loyalty.loyalty-group.create');
	Route::post('loyalty/loyalty-group', [SchemeLoyaltyGroupController::class, 'store'])->name('loyalty.loyalty-group.store');
	Route::get('loyalty/loyalty-group/{loyaltyGroup}', [SchemeLoyaltyGroupController::class, 'show'])->name('loyalty.loyalty-group.show');
	Route::get('loyalty/loyalty-group/{loyaltyGroup}/edit', [SchemeLoyaltyGroupController::class, 'edit'])->name('loyalty.loyalty-group.edit');
	Route::put('loyalty/loyalty-group/{loyaltyGroup}', [SchemeLoyaltyGroupController::class, 'update'])->name('loyalty.loyalty-group.update');
	Route::delete('loyalty/loyalty-group/{loyaltyGroup}', [SchemeLoyaltyGroupController::class, 'destroy'])->name('loyalty.loyalty-group.destroy');
	Route::get('loyalty/loyalty-plan', [SchemeLoyaltyPlanController::class, 'index'])->name('loyalty.loyalty-plan.index');
	Route::get('loyalty/loyalty-plan/create', [SchemeLoyaltyPlanController::class, 'create'])->name('loyalty.loyalty-plan.create');
	Route::post('loyalty/loyalty-plan', [SchemeLoyaltyPlanController::class, 'store'])->name('loyalty.loyalty-plan.store');
	Route::get('loyalty/loyalty-plan/{loyaltyPlan}', [SchemeLoyaltyPlanController::class, 'show'])->name('loyalty.loyalty-plan.show');
	Route::get('loyalty/loyalty-plan/{loyaltyPlan}/edit', [SchemeLoyaltyPlanController::class, 'edit'])->name('loyalty.loyalty-plan.edit');
	Route::put('loyalty/loyalty-plan/{loyaltyPlan}', [SchemeLoyaltyPlanController::class, 'update'])->name('loyalty.loyalty-plan.update');
	Route::delete('loyalty/loyalty-plan/{loyaltyPlan}', [SchemeLoyaltyPlanController::class, 'destroy'])->name('loyalty.loyalty-plan.destroy');
	Route::get('loyalty/loyalty-key', [SchemeLoyaltyKeyController::class, 'index'])->name('loyalty.loyalty-key.index');
	Route::get('loyalty/loyalty-key/create', [SchemeLoyaltyKeyController::class, 'create'])->name('loyalty.loyalty-key.create');
	Route::post('loyalty/loyalty-key', [SchemeLoyaltyKeyController::class, 'store'])->name('loyalty.loyalty-key.store');
	Route::get('loyalty/loyalty-key/{loyaltyKey}', [SchemeLoyaltyKeyController::class, 'show'])->name('loyalty.loyalty-key.show');
	Route::get('loyalty/loyalty-key/{loyaltyKey}/edit', [SchemeLoyaltyKeyController::class, 'edit'])->name('loyalty.loyalty-key.edit');
	Route::put('loyalty/loyalty-key/{loyaltyKey}', [SchemeLoyaltyKeyController::class, 'update'])->name('loyalty.loyalty-key.update');
	Route::delete('loyalty/loyalty-key/{loyaltyKey}', [SchemeLoyaltyKeyController::class, 'destroy'])->name('loyalty.loyalty-key.destroy');
	Route::get('promotion/promo-group/item-group-items', [SchemePromotionGroupController::class, 'itemGroupItems'])->name('promotion.promo-group.item-group-items');
	Route::get('promotion/promo-group/qualification-group', [SchemePromotionGroupController::class, 'qualificationIndex'])->name('promotion.qualification-group.index');
	Route::get('promotion/promo-group/qualification-group/create', [SchemePromotionGroupController::class, 'qualificationCreate'])->name('promotion.qualification-group.create');
	Route::post('promotion/promo-group/qualification-group', [SchemePromotionGroupController::class, 'qualificationStore'])->name('promotion.qualification-group.store');
	Route::get('promotion/promo-group/qualification-group/{qualificationGroup}', [SchemePromotionGroupController::class, 'qualificationShow'])->name('promotion.qualification-group.show');
	Route::get('promotion/promo-group/qualification-group/{qualificationGroup}/edit', [SchemePromotionGroupController::class, 'qualificationEdit'])->name('promotion.qualification-group.edit');
	Route::put('promotion/promo-group/qualification-group/{qualificationGroup}', [SchemePromotionGroupController::class, 'qualificationUpdate'])->name('promotion.qualification-group.update');
	Route::delete('promotion/promo-group/qualification-group/{qualificationGroup}', [SchemePromotionGroupController::class, 'qualificationDestroy'])->name('promotion.qualification-group.destroy');
	Route::get('promotion/promo-group/assignment-group', [SchemePromotionGroupController::class, 'assignmentIndex'])->name('promotion.assignment-group.index');
	Route::get('promotion/promo-group/assignment-group/create', [SchemePromotionGroupController::class, 'assignmentCreate'])->name('promotion.assignment-group.create');
	Route::post('promotion/promo-group/assignment-group', [SchemePromotionGroupController::class, 'assignmentStore'])->name('promotion.assignment-group.store');
	Route::get('promotion/promo-group/assignment-group/{assignmentGroup}', [SchemePromotionGroupController::class, 'assignmentShow'])->name('promotion.assignment-group.show');
	Route::get('promotion/promo-group/assignment-group/{assignmentGroup}/edit', [SchemePromotionGroupController::class, 'assignmentEdit'])->name('promotion.assignment-group.edit');
	Route::put('promotion/promo-group/assignment-group/{assignmentGroup}', [SchemePromotionGroupController::class, 'assignmentUpdate'])->name('promotion.assignment-group.update');
	Route::delete('promotion/promo-group/assignment-group/{assignmentGroup}', [SchemePromotionGroupController::class, 'assignmentDestroy'])->name('promotion.assignment-group.destroy');
	Route::get('supervisor-free-contract', [SchemeSupervisorFreeContractController::class, 'index'])->name('supervisor-free-contract.index');
	Route::get('supervisor-free-contract/create', [SchemeSupervisorFreeContractController::class, 'create'])->name('supervisor-free-contract.create');
	Route::post('supervisor-free-contract', [SchemeSupervisorFreeContractController::class, 'store'])->name('supervisor-free-contract.store');
	Route::get('supervisor-free-contract/{supervisorFreeContract}', [SchemeSupervisorFreeContractController::class, 'show'])->name('supervisor-free-contract.show');
	Route::get('supervisor-free-contract/{supervisorFreeContract}/edit', [SchemeSupervisorFreeContractController::class, 'edit'])->name('supervisor-free-contract.edit');
	Route::put('supervisor-free-contract/{supervisorFreeContract}', [SchemeSupervisorFreeContractController::class, 'update'])->name('supervisor-free-contract.update');
	Route::delete('supervisor-free-contract/{supervisorFreeContract}', [SchemeSupervisorFreeContractController::class, 'destroy'])->name('supervisor-free-contract.destroy');
	Route::post('supervisor-free-contract/{supervisorFreeContract}/items', [SchemeSupervisorFreeContractController::class, 'addItem'])->name('supervisor-free-contract.items.add');
	Route::put('supervisor-free-contract/{supervisorFreeContract}/items/{itemcode}', [SchemeSupervisorFreeContractController::class, 'updateItem'])->name('supervisor-free-contract.items.update');
	Route::delete('supervisor-free-contract/{supervisorFreeContract}/items/{itemcode}', [SchemeSupervisorFreeContractController::class, 'removeItem'])->name('supervisor-free-contract.items.remove');
});

/*
 * |--------------------------------------------------------------------------
 * | Auth Routes
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/auth.php';

/*
 * |--------------------------------------------------------------------------
 * | Preview Pages Routes
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/preview.php';

/*
 * |--------------------------------------------------------------------------
 * | Customer Location Routes
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/customerlocation.php';

/*
 * |--------------------------------------------------------------------------
 * | Route Tracking Routes (Planned vs Actual)
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/routetracking.php';

/*
 * |--------------------------------------------------------------------------
 * | Route Location Routes (Planned vs Actual)
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/routelocation.php';

/*
 * |--------------------------------------------------------------------------
 * | Route Replay Routes (animated playback of real GPS history)
 * |--------------------------------------------------------------------------
 */

require __DIR__ . '/routereplay.php';

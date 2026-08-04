import "./bootstrap";

import { createApp, h } from "vue";
import { createInertiaApp, Link, Head, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createPinia } from "pinia";

// Main layouts
import LayoutAuthenticated from "@/layouts/Authenticated.vue";
import LayoutGuestLanding from "@/layouts/GuestLanding.vue";
import LayoutGuestSimple from "@/layouts/GuestSimple.vue";

// Preview layout variations
import LayoutSimple from "@/layouts/variations/Simple.vue";
import LayoutLanding from "@/layouts/variations/Landing.vue";
import LayoutBackend from "@/layouts/variations/Backend.vue";
import LayoutBackendBoxed from "@/layouts/variations/BackendBoxed.vue";
import LayoutBackendMegaMenu from "@/layouts/variations/BackendMegaMenu.vue";
import LayoutBackendSidebarMiniNav from "@/layouts/variations/BackendSidebarMiniNav.vue";

// Main Stylesheet
import "@/../scss/main.scss";

// All color themes are included and available by default
// Feel free to comment out any of them if you won't use them in your project
import "@/../scss/oneui/themes/amethyst.scss";
import "@/../scss/oneui/themes/city.scss";
import "@/../scss/oneui/themes/flat.scss";
import "@/../scss/oneui/themes/modern.scss";
import "@/../scss/oneui/themes/smooth.scss";

// Template components
import BaseBlock from "@/components/BaseBlock.vue";
import BaseBackground from "@/components/BaseBackground.vue";
import BasePageHeading from "@/components/BasePageHeading.vue";

// Template directives
import clickRipple from "@/directives/clickRipple";

// Bootstrap framework
import * as bootstrap from "bootstrap";
window.bootstrap = bootstrap;

const appName = window.document.getElementsByTagName("title")[0]?.innerText || "TRAC";
const logoutRedirectKey = "trac_force_login_redirect";

function getCurrentInertiaPage() {
  const appRoot = document.getElementById("app");
  const serializedPage = appRoot?.dataset?.page;

  if (!serializedPage) {
    return null;
  }

  try {
    return JSON.parse(serializedPage);
  } catch {
    return null;
  }
}

function buildReportTranslationMap(t = {}) {
  return new Map([
    ["Company", t.company ?? "الشركة"],
    ["Region", t.region ?? "المنطقة"],
    ["Branch / Depot", t.branch_depot ?? "الفرع / المستودع"],
    ["Area", t.area ?? "المنطقة"],
    ["Sub Area", t.sub_area ?? "المنطقة الفرعية"],
    ["Route", t.route ?? "المسار"],
    ["Customer", t.customer ?? "العميل"],
    ["Customer Type", t.customer_type ?? "نوع العميل"],
    ["Year", t.year ?? "السنة"],
    ["Month", t.month ?? "الشهر"],
    ["Start Date", t.start_date ?? "تاريخ البدء"],
    ["End Date", t.end_date ?? "تاريخ النهاية"],
    ["From Date", t.from_date ?? "من تاريخ"],
    ["To Date", t.to_date ?? "إلى تاريخ"],
    ["Transaction Date - From", t.transaction_date_from ?? "تاريخ المعاملة - من"],
    ["Transaction Date - To", t.transaction_date_to ?? "تاريخ المعاملة - إلى"],
    ["Select Year", t.select_year ?? "اختر السنة"],
    ["All Companies", t.all_companies ?? "كل الشركات"],
    ["All Regions", t.all_regions ?? "كل المناطق"],
    ["All Branches / Depots", t.all_branches_depots ?? "كل الفروع / المستودعات"],
    ["All Areas", t.all_areas ?? "كل المناطق"],
    ["All Sub Areas", t.all_sub_areas ?? "كل المناطق الفرعية"],
    ["All Routes", t.all_routes ?? "كل المسارات"],
    ["All Customer Types", t.all_customer_types ?? "كل أنواع العملاء"],
    ["All Customers", t.all_customers ?? "كل العملاء"],
    ["All Years", t.all_years ?? "كل السنوات"],
    ["All Categories", t.all_categories ?? "كل الفئات"],
    ["All Items", t.all_items ?? "كل الأصناف"],
    ["All Major Categories", t.all_major_categories ?? "كل الفئات الرئيسية"],
    ["Global Report Filters", t.global_report_filters ?? "عوامل تصفية التقارير العامة"],
    ["Load Report", t.load_report ?? "تحميل التقرير"],
    ["Reset", t.reset ?? "إعادة تعيين"],
    ["Excel", t.excel ?? "إكسل"],
    ["PDF", t.pdf ?? "PDF"],
    ["No records found", t.no_records_found ?? "No records found."],
    ["Previous", t.previous ?? "السابق"],
    ["Next", t.next ?? "التالي"],
    ["No records found.", t.no_records_found ?? "لا توجد سجلات."],
    ["Total", t.total ?? "الإجمالي"],
    ["Reports", t.reports ?? "التقارير"],
    ["Daily Report", t.daily_report ?? "التقرير اليومي"],
    ["Transaction Report", t.transaction_report ?? "تقرير المعاملات"],
    ["Merchandizing Report", t.merchandizing_report ?? "تقرير التسويق المرئي"],
    ["Accounts Report", t.accounts_report ?? "تقارير الحسابات"],
    ["Data Analysis", t.data_analysis ?? "تحليل البيانات"],
    ["Route Summary", t.route_summary ?? "ملخص المسار"],
    ["Route Activity", t.route_activity ?? "نشاط المسار"],
    ["Route Inventory", t.route_inventory ?? "مخزون المسار"],
    ["Route Trip Analysis", t.route_trip_analysis ?? "تحليل رحلة المسار"],
    ["Route Deposit Summary", t.route_deposit_summary ?? "ملخص إيداع المسار"],
    ["Discount Summary", t.discount_summary ?? "ملخص الخصومات"],
    ["Pricing Summary", t.pricing_summary ?? "ملخص التسعير"],
    ["Sales Summary", t.sales_summary ?? "ملخص المبيعات"],
    ["Order Summary", t.order_summary ?? "ملخص الطلبات"],
    ["Bad Return Summary", t.bad_return_summary ?? "ملخص المرتجعات التالفة"],
    ["Collection Summary", t.collection_summary ?? "ملخص التحصيل"],
    ["Route Visit Summary", t.route_visit_summary ?? "ملخص زيارات المسار"],
    ["Payment Summary", t.payment_summary ?? "ملخص المدفوعات"],
    ["Deposit Summary", t.deposit_summary ?? "ملخص الإيداع"],
    ["Final Deposit", t.final_deposit ?? "الإيداع النهائي"],
    ["Item History", t.item_history ?? "سجل الصنف"],
    ["POS Tracking", t.pos_tracking ?? "تتبع نقاط البيع"],
    ["Survey Tracking", t.survey_tracking ?? "تتبع الاستبيان"],
    ["Waste Stock", t.waste_stock ?? "مخزون الهالك"],
    ["Assets Availability", t.assets_availability ?? "توفر الأصول"],
    ["Merchandized Stock", t.merchandized_stock ?? "المخزون المعروض"],
    ["Route Ageing", t.route_ageing ?? "تقادم المسار"],
    ["Customer Ageing", t.customer_ageing ?? "تقادم العملاء"],
    ["Route Pending Balance", t.route_pending_balance ?? "الرصيد المعلق للمسار"],
    ["Customer Pending Balance", t.customer_pending_balance ?? "الرصيد المعلق للعميل"],
    ["Route Monthly Revenue", t.route_monthly_revenue ?? "الإيراد الشهري للمسار"],
    ["Sales Free Summary", t.sales_free_summary ?? "ملخص المبيعات والمجاني"],
    ["Item Sales Summary", t.item_sales_summary ?? "ملخص مبيعات الأصناف"],
    ["Item Group Wise Sales", t.item_group_wise_sales ?? "مبيعات حسب مجموعة الأصناف"],
    ["Customer Pending Balance Report", t.customer_pending_balance ?? "Customer Pending Balance"],
    ["Discount Summary Report", t.discount_summary ?? "Discount Summary"],
    ["Pricing Summary Report", t.pricing_summary ?? "Pricing Summary"],
    ["Bad Return Summary Report", t.bad_return_summary ?? "Bad Return Summary"],
    ["Collection Summary Report", t.collection_summary ?? "Collection Summary"],
    ["Deposit Summary Report", t.deposit_summary ?? "Deposit Summary"],
    ["Final Deposit Report", t.final_deposit ?? "Final Deposit"],
    ["Item History Summary", t.item_history ?? "Item History"],
    ["Order Summary Report", t.order_summary ?? "Order Summary"],
    ["Payment Summary Report", t.payment_summary ?? "Payment Summary"],
    ["Waste Stock Report", t.waste_stock ?? "Waste Stock"],
    ["Assets Availability Report", t.assets_availability ?? "Assets Availability"],
    ["Merchandized Stock Report", t.merchandized_stock ?? "Merchandized Stock"],
    ["Survey Tracking Report", t.survey_tracking ?? "Survey Tracking"],
    ["Route Monthly Revenue Report", t.route_monthly_revenue ?? "Route Monthly Revenue"],
    ["Sales Free Summary Report", t.sales_free_summary ?? "Sales Free Summary"],
    ["Item Sales Summary Report", t.item_sales_summary ?? "Item Sales Summary"],
    ["Item Group Wise Sales Report", t.item_group_wise_sales ?? "Item Group Wise Sales"],
    ["Review pending invoice balances by head office, customer, invoice, and month.", t.customer_pending_balance_report_note ?? "مراجعة أرصدة الفواتير المعلقة حسب المكتب الرئيسي والعميل والفاتورة والشهر."],
    ["Review route-wise sales, returns, free goods, and discounts by item group.", t.item_group_wise_sales_report_note ?? "مراجعة المبيعات والمرتجعات والمجاني والخصومات حسب مجموعة الأصناف خلال الفترة المحددة."],
    ["Review item-wise sales, returns, expiry, discounts, and free goods.", t.item_sales_summary_report_note ?? "مراجعة المبيعات والمرتجعات والانتهاء والخصومات والمجاني حسب الصنف خلال الفترة المحددة."],
    ["Review route revenue by month for the selected year.", t.route_monthly_revenue_report_note ?? "مراجعة إيراد المسار حسب الشهر للسنة المحددة."],
    ["Review sales, free, return, and discount values by route and item.", t.sales_free_summary_report_note ?? "مراجعة المبيعات والمجاني والمرتجعات والخصومات وصافي المبيعات حسب المسار ومجموعة الأصناف والصنف."],
    ["Review customer asset records captured during visits, including POS code, serial, status, and remarks.", t.assets_availability_report_note ?? "مراجعة سجلات زيارة أصول العملاء حسب المسار والمنفذ ورمز نقطة البيع والرقم التسلسلي والحالة والملاحظات."],
    ["Review shelf and store stock checks captured during visits, including cut-off and max quantities.", t.merchandized_stock_report_note ?? "مراجعة فحوصات مخزون الأرفف والمخازن في المنفذ حسب الزيارة والعميل والصنف وكمية الحد وتاريخ الانتهاء."],
    ["Review customer survey answers captured during route visits, grouped by route, salesman, and outlet.", t.survey_tracking_report_note ?? "مراجعة أسئلة الاستبيان والإجابات المسجلة لزيارات العملاء خلال الفترة المحددة."],
    ["Review visit-wise outlet waste stock checks by route, customer, item, and expiry.", t.waste_stock_report_note ?? "مراجعة فحوصات مخزون الهالك في المنفذ حسب تاريخ الزيارة والعميل والصنف والكمية وتاريخ الانتهاء."],
    ["Review item-wise damaged, expired, and other bad return quantities and values for the selected period.", t.bad_return_summary_report_note ?? "مراجعة كميات وقيم المرتجعات التالفة والمنتهية وغيرها حسب الصنف للفترة المحددة."],
    ["Review route-wise receipt collections, cash, cheque, and invoice references for the selected period.", t.collection_summary_report_note ?? "مراجعة تحصيلات الإيصالات والنقد والشيكات ومراجع الفواتير حسب المسار للفترة المحددة."],
    ["Review invoice and receipt deposits by transaction date, route, payment type, and total amount.", t.deposit_summary_report_note ?? "مراجعة إيداعات الفواتير والإيصالات حسب تاريخ المعاملة والمسار ونوع الدفع وإجمالي المبلغ."],
    ["Review final deposited collection amounts by receipt, customer, invoice reference, and payment method.", t.final_deposit_report_note ?? "مراجعة مبالغ التحصيل المودعة نهائيا حسب الإيصال والعميل ومرجع الفاتورة وطريقة الدفع."],
    ["Review route trip inventory movement, sales, returns, and closing stock by item and major category.", t.item_history_report_note ?? "مراجعة حركة مخزون الرحلات والمرتجعات والمخزون الختامي حسب الصنف والفئة الرئيسية."],
    ["Review route-wise order transactions and order net amounts by transaction date.", t.order_summary_report_note ?? "مراجعة معاملات الطلبات وصافي مبالغ الطلبات حسب المسار للفترة المحددة."],
    ["Review invoice-wise payments, collections, and outstanding balances for the selected period.", t.payment_summary_report_note ?? "مراجعة المدفوعات والتحصيلات والأرصدة المستحقة حسب الفاتورة للفترة المحددة."],
    ["Route Code", t.route_code ?? "رمز المسار"],
    ["Transaction Date", t.transaction_date ?? "تاريخ المعاملة"],
    ["Transaction Time", t.transaction_time ?? "وقت المعاملة"],
    ["Invoice Number", t.invoice_number ?? "رقم الفاتورة"],
    ["Invoice No", t.invoice_no ?? "رقم الفاتورة"],
    ["Salesman Code", t.salesman_code ?? "رمز مندوب المبيعات"],
    ["Customer Code", t.customer_code ?? "رمز العميل"],
    ["Customer Name", t.customer_name ?? "اسم العميل"],
    ["Payment Type", t.payment_type ?? "نوع الدفع"],
    ["Sales Amount", t.sales_amount ?? "مبلغ المبيعات"],
    ["Sales Qty Cases", t.sales_qty_cases ?? "كمية المبيعات كراتين"],
    ["Sales Qty Pcs", t.sales_qty_pcs ?? "كمية المبيعات قطع"],
    ["Good Return Amount", t.good_return_amount ?? "مبلغ المرتجع الصالح"],
    ["Good Return Qty Cases", t.good_return_qty_cases ?? "كمية المرتجع الصالح كراتين"],
    ["Good Return Qty Pcs", t.good_return_qty_pcs ?? "كمية المرتجع الصالح قطع"],
    ["Bad Return Amount", t.bad_return_amount ?? "مبلغ المرتجع التالف"],
    ["Bad Return Qty Cases", t.bad_return_qty_cases ?? "كمية المرتجع التالف كراتين"],
    ["Bad Return Qty Pcs", t.bad_return_qty_pcs ?? "كمية المرتجع التالف قطع"],
    ["Free Amount", t.free_amount ?? "مبلغ المجاني"],
    ["Free Qty Cases", t.free_qty_cases ?? "كمية المجاني كراتين"],
    ["Free Qty Pcs", t.free_qty_pcs ?? "كمية المجاني قطع"],
    ["Invoice Amount", t.invoice_amount ?? "مبلغ الفاتورة"],
    ["Discount Amount", t.discount_amount ?? "مبلغ الخصم"],
    ["Net Amount", t.net_amount ?? "صافي المبلغ"],
    ["Immediate Paid", t.immediate_paid ?? "مدفوع فوري"],
    ["Immediate Cash", t.immediate_cash ?? "نقد فوري"],
    ["Immediate Cheque", t.immediate_cheque ?? "شيك فوري"],
    ["Immediate CASH Payment", t.immediate_cash_payment ?? "دفعة نقدية فورية"],
    ["Immediate Cheque Payment", t.immediate_cheque_payment ?? "دفعة شيك فورية"],
    ["Invoice Balance", t.invoice_balance ?? "رصيد الفاتورة"],
    ["Outstanding Balance Amount", t.outstanding_balance_amount ?? "مبلغ الرصيد المستحق"],
    ["Total Amount", t.total_amount ?? "إجمالي المبلغ"],
    ["Total Invoice Amount", t.total_invoice_amount ?? "إجمالي مبلغ الفاتورة"],
    ["Type", t.type ?? "النوع"],
    ["Receipt", t.receipt ?? "الإيصال"],
    ["Receipt Number", t.receipt_number ?? "رقم الإيصال"],
    ["Against Invoice", t.against_invoice ?? "مقابل الفاتورة"],
    ["Order Number", t.order_number ?? "رقم الطلب"],
    ["Order Net Amount", t.order_net_amount ?? "صافي مبلغ الطلب"],
    ["Received Time", t.received_time ?? "وقت الاستلام"],
    ["MOP", t.mop ?? "طريقة الدفع"],
    ["HO Code", t.ho_code ?? "رمز المكتب الرئيسي"],
    ["Comments", t.comments ?? "الملاحظات"],
    ["Credit Days", t.credit_days ?? "أيام الائتمان"],
    ["Above 120", t.above_120 ?? "أكثر من 120"],
    ["PDC Amount", t.pdc_amount ?? "مبلغ الشيك المؤجل"],
    ["PDC Date", t.pdc_date ?? "تاريخ الشيك المؤجل"],
    ["Details", t.details ?? "التفاصيل"],
    ["Major Category", t.major_category ?? "الفئة الرئيسية"],
    ["Items", t.items ?? "الأصناف"],
    ["Item", t.item ?? "الصنف"],
    ["Item Code", t.item_code ?? "رمز الصنف"],
    ["Item Description", t.item_description ?? "وصف الصنف"],
    ["Item Group", t.item_group ?? "مجموعة الأصناف"],
    ["Item Group Code", t.item_group_code ?? "رمز مجموعة الأصناف"],
    ["Item Category", t.item_category ?? "فئة الصنف"],
    ["Group", t.group ?? "المجموعة"],
    ["Description", t.description ?? "الوصف"],
    ["Date", t.date ?? "التاريخ"],
    ["Time", t.time ?? "الوقت"],
    ["Visit Date", t.visit_date ?? "تاريخ الزيارة"],
    ["Visit Time", t.visit_time ?? "وقت الزيارة"],
    ["Invoiced By", t.invoiced_by ?? "تمت الفوترة بواسطة"],
    ["Invoiced Date", t.invoiced_date ?? "تاريخ الفاتورة"],
    ["Paid Amount", t.paid_amount ?? "المبلغ المدفوع"],
    ["Amount Paid in CASH", t.amount_paid_in_cash ?? "المبلغ المدفوع نقدا"],
    ["Amount Paid in CHEQUE", t.amount_paid_in_cheque ?? "المبلغ المدفوع بشيك"],
    ["AR CASH Collection", t.ar_cash_collection ?? "تحصيل نقدي للذمم"],
    ["AR Cheque Collection", t.ar_cheque_collection ?? "تحصيل شيكات للذمم"],
    ["Tran. Date", t.tran_date ?? "تاريخ الحركة"],
    ["Tran. Time", t.tran_time ?? "وقت الحركة"],
    ["Trip Start Date - Trip End Date", t.trip_date_range ?? "تاريخ بدء الرحلة - تاريخ انتهاء الرحلة"],
    ["Opening Case/Unit", t.opening_case_unit ?? "الافتتاحي كرتون/وحدة"],
    ["Load Case/Unit", t.load_case_unit ?? "التحميل كرتون/وحدة"],
    ["Transfer IN Case/Unit", t.transfer_in_case_unit ?? "التحويل الداخل كرتون/وحدة"],
    ["Transfer OUT Case/Unit", t.transfer_out_case_unit ?? "التحويل الخارج كرتون/وحدة"],
    ["Sales Case/Unit", t.sales_case_unit ?? "المبيعات كرتون/وحدة"],
    ["Good Return Case/Unit", t.good_return_case_unit ?? "المرتجع الصالح كرتون/وحدة"],
    ["Bad Return Case/Unit", t.bad_return_case_unit ?? "المرتجع التالف كرتون/وحدة"],
    ["Free Case/Unit", t.free_case_unit ?? "المجاني كرتون/وحدة"],
    ["Damage Variance Case/Unit", t.damage_variance_case_unit ?? "فرق التالف كرتون/وحدة"],
    ["Closing Case/Unit", t.closing_case_unit ?? "الختامي كرتون/وحدة"],
    ["Opening Stock Value", t.opening_stock_value ?? "قيمة المخزون الافتتاحي"],
    ["Daily Loaded Value", t.daily_loaded_value ?? "قيمة التحميل اليومي"],
    ["Closing Value", t.closing_value ?? "القيمة الختامية"],
    ["Damage Variance Value", t.damage_variance_value ?? "قيمة فرق التالف"],
    ["Truck Stock Value", t.truck_stock_value ?? "قيمة مخزون الشاحنة"],
    ["Sales Qty", t.sales_qty ?? "كمية المبيعات"],
    ["Free Qty", t.free_qty ?? "كمية المجاني"],
    ["Net Sales", t.net_sales ?? "صافي المبيعات"],
    ["Net Sales Amount", t.net_sales_amount ?? "صافي مبلغ المبيعات"],
    ["Net Sales Qty", t.net_sales_qty ?? "صافي كمية المبيعات"],
    ["Discounts", t.discounts ?? "الخصومات"],
    ["Good Ret. Qty", t.good_ret_qty ?? "كمية المرتجع الصالح"],
    ["Bad Ret. Qty", t.bad_ret_qty ?? "كمية المرتجع التالف"],
    ["Damaged Qty.", t.damaged_qty ?? "كمية التالف"],
    ["Damaged Value", t.damaged_value ?? "قيمة التالف"],
    ["Other Damaged Qty.", t.other_damaged_qty ?? "كمية التالف الآخر"],
    ["Other Damaged Value", t.other_damaged_value ?? "قيمة التالف الآخر"],
    ["Total Damaged Qty.", t.total_damaged_qty ?? "إجمالي كمية التالف"],
    ["Total Damaged Value", t.total_damaged_value ?? "إجمالي قيمة التالف"],
    ["G. Return Qty", t.g_return_qty ?? "كمية المرتجع الصالح"],
    ["G. Return @ Inv. Price", t.g_return_inv_price ?? "المرتجع الصالح بسعر الفاتورة"],
    ["Good Return @ Inv. Price", t.good_return_inv_price ?? "المرتجع الصالح بسعر الفاتورة"],
    ["Good Return @ Std. Price", t.good_return_std_price ?? "المرتجع الصالح بالسعر القياسي"],
    ["Damaged Return Qty", t.damaged_return_qty ?? "كمية المرتجع التالف"],
    ["Damaged Return @ Inv. Price", t.damaged_return_inv_price ?? "المرتجع التالف بسعر الفاتورة"],
    ["Damage Ret. Qty", t.damage_ret_qty ?? "كمية المرتجع التالف"],
    ["Damage Ret @ Inv. Price", t.damage_ret_inv_price ?? "المرتجع التالف بسعر الفاتورة"],
    ["Damage Ret @ Std. Price", t.damage_ret_std_price ?? "المرتجع التالف بالسعر القياسي"],
    ["Bad Ret @ Inv. Price", t.bad_ret_inv_price ?? "المرتجع التالف بسعر الفاتورة"],
    ["Bad Ret @ Std. Price", t.bad_ret_std_price ?? "المرتجع التالف بالسعر القياسي"],
    ["Expired Qty", t.expired_qty ?? "الكمية المنتهية"],
    ["Expired Qty.", t.expired_qty ?? "الكمية المنتهية"],
    ["Expired Value", t.expired_value ?? "قيمة المنتهي"],
    ["Expired @ Inv. Price", t.expired_inv_price ?? "المنتهي بسعر الفاتورة"],
    ["Expired Ret @ Inv. Price", t.expired_ret_inv_price ?? "المرتجع المنتهي بسعر الفاتورة"],
    ["Expired Ret @ Std. Price", t.expired_ret_std_price ?? "المرتجع المنتهي بالسعر القياسي"],
    ["Free Goods @ Std. Price", t.free_goods_std_price ?? "المجاني بالسعر القياسي"],
    ["Sales @ Inv. Price", t.sales_inv_price ?? "المبيعات بسعر الفاتورة"],
    ["Sales @ Std. Price", t.sales_std_price ?? "المبيعات بالسعر القياسي"],
    ["Case Price", t.case_price ?? "سعر الكرتون"],
    ["Unit Price", t.unit_price ?? "سعر الوحدة"],
    ["Price Difference", t.price_difference ?? "فرق السعر"],
    ["Inv. Discount Break Up", t.invoice_discount_break_up ?? "تفصيل خصم الفاتورة"],
    ["Item Discount Break Up", t.item_discount_break_up ?? "تفصيل خصم الصنف"],
    ["POS", t.pos ?? "نقطة البيع"],
    ["POS Code", t.pos_code ?? "رمز نقطة البيع"],
    ["POS Status", t.pos_status ?? "حالة نقطة البيع"],
    ["POS Description", t.pos_description ?? "وصف نقطة البيع"],
    ["POS Qty", t.pos_qty ?? "كمية نقطة البيع"],
    ["POS Serial", t.pos_serial ?? "الرقم التسلسلي لنقطة البيع"],
    ["Serial No", t.serial_no ?? "الرقم التسلسلي"],
    ["UPC", t.upc ?? "الباركود"],
    ["Shelf", t.shelf ?? "الرف"],
    ["Store", t.store ?? "المخزن"],
    ["Location1 Qty", t.location1_qty ?? "كمية الموقع 1"],
    ["Location2 Qty", t.location2_qty ?? "كمية الموقع 2"],
    ["Location3 Qty", t.location3_qty ?? "كمية الموقع 3"],
    ["Cut-off Qty", t.cut_off_qty ?? "كمية الحد"],
    ["Max Qty", t.max_qty ?? "الكمية القصوى"],
    ["Expiry", t.expiry ?? "الانتهاء"],
    ["Remarks", t.remarks ?? "الملاحظات"],
    ["Survey Description", t.survey_description ?? "وصف الاستبيان"],
    ["Survey Response", t.survey_response ?? "إجابة الاستبيان"],
    ["Salesman", t.salesman ?? "مندوب المبيعات"],
    ["Total FOC", t.total_foc ?? "إجمالي المجاني"],
  ]);
}

function localizeReportText(text, translations, t) {
  const trimmed = text.trim();

  if (!trimmed) {
    return text;
  }

  if (translations.has(trimmed)) {
    return text.replace(trimmed, translations.get(trimmed));
  }

  const paginationMatch = trimmed.match(/^Showing\s+(\d+)\s+to\s+(\d+)\s+of\s+(\d+)$/);
  if (paginationMatch) {
    const [, from, to, total] = paginationMatch;
    const localized = `${t.showing ?? "عرض"} ${from} ${t.to ?? "إلى"} ${to} ${t.of ?? "من"} ${total}`;
    return text.replace(trimmed, localized);
  }

  const suffixMatch = trimmed.match(/^(.*?)(?:\s+(Report|Summary))$/);
  if (suffixMatch && translations.has(suffixMatch[1])) {
    return text.replace(trimmed, translations.get(suffixMatch[1]));
  }

  return text;
}

function localizeReportsDom() {
  const page = getCurrentInertiaPage();
  if (!page?.component?.startsWith("reports/") || page?.props?.locale !== "ar") {
    return;
  }

  const t = page.props.translations?.ui ?? {};
  const translations = buildReportTranslationMap(t);
  const root = document.getElementById("app");
  if (!root) {
    return;
  }

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!node.parentElement) {
        return NodeFilter.FILTER_REJECT;
      }

      if (["SCRIPT", "STYLE", "NOSCRIPT"].includes(node.parentElement.tagName)) {
        return NodeFilter.FILTER_REJECT;
      }

      return NodeFilter.FILTER_ACCEPT;
    },
  });

  const textNodes = [];
  while (walker.nextNode()) {
    textNodes.push(walker.currentNode);
  }

  for (const node of textNodes) {
    const localized = localizeReportText(node.textContent ?? "", translations, t);
    if (localized !== node.textContent) {
      node.textContent = localized;
    }
  }

  for (const element of root.querySelectorAll("[placeholder],[title]")) {
    for (const attribute of ["placeholder", "title"]) {
      const value = element.getAttribute(attribute);
      if (!value) {
        continue;
      }

      const localized = localizeReportText(value, translations, t);
      if (localized !== value) {
        element.setAttribute(attribute, localized);
      }
    }
  }

  if (document.title) {
    const titleParts = document.title.split(" - ");
    titleParts[0] = localizeReportText(titleParts[0], translations, t);
    document.title = titleParts.join(" - ");
  }
}

function shouldForceLoginRedirect() {
  return window.sessionStorage.getItem(logoutRedirectKey) === "1";
}

function forceLoginRedirect() {
  if (window.location.pathname !== "/login") {
    window.location.replace("/login");
  }
}

window.addEventListener("pageshow", (event) => {
  const navigationEntries = performance.getEntriesByType?.("navigation") ?? [];
  const navigationType = navigationEntries[0]?.type;

  if (shouldForceLoginRedirect() && (event.persisted || navigationType === "back_forward")) {
    forceLoginRedirect();
    return;
  }

  if (event.persisted || navigationType === "back_forward") {
    window.location.reload();
  }
});

window.addEventListener("popstate", () => {
  if (shouldForceLoginRedirect()) {
    forceLoginRedirect();
  }
});

router.on("finish", () => {
  window.requestAnimationFrame(() => {
    localizeReportsDom();
  });
});

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  progress: {
    color: "#3b82f6",
  },
  resolve: (name) => {
    const page = resolvePageComponent(
      `./views/${name}.vue`,
      import.meta.glob("./views/**/*.vue"),
    );

    // Set default persistent layout
    page.then((module) => {
      if (module.default.layout === undefined) {
        // Check if we are in a preview page
        if (name.startsWith("preview/")) {
          if (name === "preview/backend/elements/MegaMenuView") {
            module.default.layout = LayoutBackendMegaMenu;
          } else if (
            name === "preview/backend/pages/generic/SidebarMiniNavView"
          ) {
            module.default.layout = LayoutBackendSidebarMiniNav;
          } else if (
            name.startsWith("preview/auth/") ||
            name.startsWith("preview/errors/") ||
            name.startsWith("preview/specials/")
          ) {
            module.default.layout = LayoutSimple;
          } else if (name.startsWith("preview/landing/")) {
            module.default.layout = LayoutLanding;
          } else if (name.startsWith("preview/backend-boxed/")) {
            module.default.layout = LayoutBackendBoxed;
          } else {
            module.default.layout = LayoutBackend;
          }
        } else {
          // We are in working application pages
          if (name === "Welcome") {
            module.default.layout = LayoutGuestLanding;
          } else if (name.startsWith("auth/")) {
            module.default.layout = LayoutGuestSimple;
          } else {
            module.default.layout = LayoutAuthenticated;
          }
        }
      }
    });

    return page;
  },
  setup({ el, App, props, plugin }) {
    if (props.initialPage.props.auth?.user) {
      window.sessionStorage.removeItem(logoutRedirectKey);
    }

    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .component("Link", Link)
      .component("Head", Head)
      .component("BaseBlock", BaseBlock)
      .component("BaseBackground", BaseBackground)
      .component("BasePageHeading", BasePageHeading)
      .directive("click-ripple", clickRipple)
      .mount(el);

    window.requestAnimationFrame(() => {
      localizeReportsDom();
    });

    return app;
  },
});

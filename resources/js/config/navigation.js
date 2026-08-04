export const navigation = [
  {
    label: "analytics",
    icon: "fa fa-chart-column",
    sub: [
      { label: "overview", to: "/analytics/overview", icon: "fa fa-chart-pie", permission: "analytics overview" },
      { label: "sales_analytics", to: "/analytics/sales", icon: "fa fa-chart-line", permission: "sales analytics" },
      { label: "collection_analytics", to: "/analytics/collections", icon: "fa fa-wallet", permission: "collection analytics" },
      { label: "inventory_analytics", to: "/analytics/inventory", icon: "fa fa-boxes-stacked", permission: "inventory analytics" },
    ],
  },

  {
    label: "user_management",
    icon: "fa fa-users",
    sub: [
      { label: "user_type",        to: "/usermanagement/user-type",        icon: "fa fa-id-card", permission: "user type" },
      { label: "user_master",      to: "/usermanagement/user-master",      icon: "fa fa-user", permission: "users" },
      { label: "user_permission",  to: "/usermanagement/user-permission",  icon: "fa fa-shield-halved", permission: "user permission" },
    ],
  },

  {
    label: "basic",
    icon: "fa fa-list",
    sub: [
      { label: "company_master", to: "/basic/company", icon: "fa fa-building", permission: "company" },
      { label: "currency_master", to: "/basic/currency", icon: "fa fa-coins", permission: "currency" },
      { label: "bank_master", to: "/basic/bank", icon: "fa fa-building-columns", permission: "bank" },
      { label: "cash_description", to: "/basic/cashdescription", icon: "fa fa-money-bill", permission: "cash description" },
      { label: "inventory_location", to: "/basic/inventorylocation", icon: "fa fa-warehouse", permission: "inventory location" },
      { label: "national_sales_manager", to: "/basic/nationalsalesmanager", icon: "fa fa-user-tie", permission: "national sales mgr" },
      { label: "regional_manager", to: "/basic/regionmanager", icon: "fa fa-user-group", permission: "region manager" },
      { label: "branch_depot_manager", to: "/basic/branchmanager", icon: "fa fa-code-branch", permission: "branch/depot manager" },
      { label: "area_manager", to: "/basic/areamanager", icon: "fa fa-users-viewfinder", permission: "area manager" },
      { label: "supervisor", to: "/basic/supervisor", icon: "fa fa-user-check", permission: "supervisor" },
      { label: "reason_master", to: "/basic/reason", icon: "fa fa-clipboard-list", permission: "reason" },
    ],
  },

  {
    label: "organisation",
    icon: "fa fa-sitemap",
    sub: [
      { label: "country", to: "/organisation/country", icon: "fa fa-earth-asia", permission: "country" },
      { label: "region", to: "/organisation/region", icon: "fa fa-map", permission: "region" },
      { label: "depot_master", to: "/organisation/depot", icon: "fa fa-warehouse", permission: "depot" },
      { label: "area", to: "/organisation/area", icon: "fa fa-map-location-dot", permission: "area" },
      { label: "sub_area", to: "/organisation/subarea", icon: "fa fa-location-dot", permission: "sub area" },
      { label: "van", to: "/organisation/van", icon: "fa fa-van-shuttle", permission: "van" },
      { label: "device_registration", to: "/organisation/device-registration", icon: "fa fa-mobile-screen-button", permission: "device registration" },
      { label: "route_category", to: "/organisation/routecategory", icon: "fa fa-route", permission: "route category" },
      { label: "route", to: "/organisation/route", icon: "fa fa-road", permission: "route" },
      { label: "route_template_setting", to: "/organisation/routetemplate", icon: "fa fa-layer-group", permission: "route template" },
    ],
  },

  {
    label: "account",
    icon: "fa fa-wallet",
    sub: [
      {
        label: "message",
        icon: "fa fa-envelope",
        sub: [
          { label: "customer_message", to: "/account/customer-message", icon: "fa fa-user-group", permission: "customer message" },
          { label: "salesman_message", to: "/account/salesman-message", icon: "fa fa-id-badge", permission: "salesman message" },
        ],
      },
      { label: "salesman", to: "/account/salesman", icon: "fa fa-id-badge", permission: "account salesman" },
      { label: "salesman_otp", to: "/account/salesman-otp", icon: "fa fa-key", permission: "salesman otp" },
      { label: "customer_channel", to: "/account/customer-channel", icon: "fa fa-diagram-project", permission: "account customer channel" },
      { label: "customer_category", to: "/account/customer-category", icon: "fa fa-tags", permission: "account customer category" },
      { label: "customer", to: "/account/customer", icon: "fa fa-users", permission: "account customer" },
      { label: "customer_template", to: "/account/customer-template", icon: "fa fa-clone", permission: "account customer template" },
      { label: "customer_authorize_group", to: "/account/customer-authorize-group", icon: "fa fa-list-check", permission: "account customer authorize group" },
      { label: "customer_sequence", to: "/account/customer-sequence", icon: "fa fa-list-ol", permission: "account customer sequence" },
      { label: "auto_jp_management", to: "/account/auto-jp-management", icon: "fa fa-route", permission: "account auto jp management" },
      { label: "tax", to: "/account/tax", icon: "fa fa-percent", permission: "account tax" },
      {
        label: "transaction",
        icon: "fa fa-cash-register",
        sub: [
          { label: "opening_balance", to: "/account/transaction/opening-balance", icon: "fa fa-scale-balanced", permission: "account transaction" },
          { label: "gc_collection", to: "/account/transaction/gc-collection", icon: "fa fa-money-bill-transfer", permission: "account transaction" },
          { label: "ho_collection", to: "/account/transaction/ho-collection", icon: "fa fa-building-circle-arrow-right", permission: "account transaction" },
          {
            label: "debit_note",
            icon: "fa fa-file-invoice-dollar",
            sub: [
              { label: "debit_note_customer", to: "/account/transaction/debit-note/customer", icon: "fa fa-user-pen", permission: "account transaction" },
              { label: "debit_note_route", to: "/account/transaction/debit-note/route", icon: "fa fa-road-circle-exclamation", permission: "account transaction" },
            ],
          },
          {
            label: "credit_note",
            icon: "fa fa-file-circle-plus",
            sub: [
              { label: "credit_note_customer", to: "/account/transaction/credit-note/customer", icon: "fa fa-user-check", permission: "account transaction" },
              { label: "credit_note_route", to: "/account/transaction/credit-note/route", icon: "fa fa-road-circle-check", permission: "account transaction" },
            ],
          },
        ],
      },
      {
        label: "settlement",
        icon: "fa fa-money-check-dollar",
        sub: [
          { label: "cashier_receipt", to: "/account/settlement/cash-receipt", icon: "fa fa-receipt", permission: "account transaction" },
          { label: "pdc_clearance", to: "/account/settlement/pdc-clearance", icon: "fa fa-money-check", permission: "account transaction" },
        ],
      },
    ],
  },

  {
    label: "gps_routing",
    icon: "fa fa-route",
    sub: [
      { label: "customer_location", to: "/customer-location", icon: "fa fa-map-location-dot", permission: "customer location" },
      { label: "route_location", to: "/route-location", icon: "fa fa-location-crosshairs", permission: "route location" },
      { label: "route_tracking", to: "/route-tracking", icon: "fa fa-code-compare", permission: "route tracking" },
      { label: "route_replay", to: "/route-replay", icon: "fa fa-play-circle", permission: "route replay" },
    ],
  },

  {
    label: "inventory",
    icon: "fa fa-boxes-stacked",
    sub: [
      { label: "company_group", to: "/inventory/companygroup", icon: "fa fa-object-group", permission: "company group" },
      { label: "major_category", to: "/inventory/majorcategory", icon: "fa fa-box-open", permission: "major category" },
      { label: "sub_major_category", to: "/inventory/submajorcategory", icon: "fa fa-boxes-packing", permission: "sub major category" },
      { label: "item_group", to: "/inventory/itemgroup", icon: "fa fa-layer-group", permission: "item group" },
      { label: "items", to: "/inventory/item", icon: "fa fa-box", permission: "items" },
      { label: "route_item_group", to: "/inventory/routeitemgroup", icon: "fa fa-diagram-project", permission: "route item group" },
      { label: "daily_salesman_load", to: "/inventory/dailysalesmanload", icon: "fa fa-truck-ramp-box", permission: "daily salesman load" },
      { label: "delivery", to: "/inventory/delivery", icon: "fa fa-truck-fast", permission: "delivery" },
      {
        label: "target_commission",
        icon: "fa fa-bullseye",
        sub: [
          { label: "target_group", to: "/inventory/targetgroup", icon: "fa fa-boxes-stacked", permission: "target group" },
          { label: "target_commission", to: "/inventory/targetcommission", icon: "fa fa-bullseye", permission: "target & commission" },
        ],
      },
    ],
  },

  {
    label: "scheme",
    icon: "fa fa-tags",
    sub: [
        {
          label: "promotion",
          icon: "fa fa-ticket",
          sub: [
            {
              label: "promo_group",
              icon: "fa fa-layer-group",
              sub: [
                { label: "qualification_group", to: "/scheme/promotion/promo-group/qualification-group", icon: "fa fa-list-check", permission: "qualification group" },
                { label: "assignment_group",    to: "/scheme/promotion/promo-group/assignment-group",    icon: "fa fa-diagram-project", permission: "assignment group" },
              ],
            },
            { label: "promo_plan", to: "/scheme/promotion/promo-plan", icon: "fa fa-sitemap", permission: "promo plan" },
            { label: "promo_key",  to: "/scheme/promotion/promo-key",  icon: "fa fa-key",     permission: "promo key" },
          ],
        },
        {
          label: "special_price",
          icon: "fa fa-tags",
          sub: [
            { label: "pricing_plan", to: "/scheme/special-price/pricing-plan", icon: "fa fa-list",   permission: "pricing plan" },
            { label: "pricing_key",  to: "/scheme/special-price/pricing-key",  icon: "fa fa-ticket", permission: "pricing key" },
          ],
        },
        {
          label: "loyalty",
          icon: "fa fa-gift",
          sub: [
            { label: "loyalty_group", to: "/scheme/loyalty/loyalty-group", icon: "fa fa-layer-group", permission: "loyalty group" },
            { label: "loyalty_plan",  to: "/scheme/loyalty/loyalty-plan",  icon: "fa fa-sitemap",     permission: "loyalty plan" },
            { label: "loyalty_key",   to: "/scheme/loyalty/loyalty-key",   icon: "fa fa-key",         permission: "loyalty key" },
          ],
        },
        { label: "supervisor_free_contract", to: "/scheme/supervisor-free-contract", icon: "fa fa-file-contract", permission: "supervisor free contract" },
      ],
    },

  {
    label: "merchandizing",
    icon: "fa fa-store",
    sub: [
      {
        label: "survey",
        icon: "fa fa-clipboard-list",
        sub: [
          { label: "survey_definition", to: "/merchandizing/survey", icon: "fa fa-list", permission: "survey" },
          { label: "survey_plan", to: "/merchandizing/survey-plan", icon: "fa fa-sitemap", permission: "survey plan" },
          { label: "survey_key", to: "/merchandizing/survey-key", icon: "fa fa-key", permission: "survey key" },
        ],
      },
      {
        label: "pos",
        icon: "fa fa-cash-register",
        sub: [
          { label: "pos_master", to: "/merchandizing/pos-master", icon: "fa fa-desktop", permission: "pos master" },
          { label: "customer_pos_limit", to: "/merchandizing/customer-pos-limit", icon: "fa fa-users-gear", permission: "customer pos limit" },
          { label: "pos_instruction", to: "/merchandizing/pos-instruction", icon: "fa fa-clipboard-check", permission: "pos instruction" },
        ],
      },
      { label: "planogram", to: "/merchandizing/planogram", icon: "fa fa-images", permission: "planogram" },
      { label: "images_captured", to: "/merchandizing/images-captured", icon: "fa fa-camera", permission: "images captured" },
    ],
  },
  {
    label: "link_module",
    icon: "fa fa-link",
    sub: [
      { label: "category_key", to: "/links/category-key", icon: "fa fa-key", permission: "category key" },
      { label: "promotion", to: "/links/promotion", icon: "fa fa-ticket", permission: "promotion link" },
      { label: "special_price", to: "/links/special-price", icon: "fa fa-tags", permission: "special price link" },
      { label: "survey", to: "/links/survey", icon: "fa fa-clipboard-list", permission: "survey link" },
      { label: "outlet_product_code", to: "/links/outlet-product-code", icon: "fa fa-barcode", permission: "outlet product code" },
      { label: "route_item_group", to: "/links/route-item-group", icon: "fa fa-layer-group", permission: "route item group" },
      { label: "active_in_active_items", to: "/links/active-inactive-items", icon: "fa fa-toggle-off", permission: "active/inactive items" },
      { label: "planogram_key", to: "/links/planogram-key", icon: "fa fa-images", permission: "planogram key" },
      { label: "items_group", to: "/links/items-group", icon: "fa fa-boxes-stacked", permission: "items group" },
    ],
  },

  {
    label: "transaction",
    icon: "fa fa-arrow-right-arrow-left",
    sub: [
      { label: "begin_opening_stock", to: "/transaction/begin-opening-stock", icon: "fa fa-warehouse", permission: "begin / opening stock" },
      { label: "load", to: "/transaction/load", icon: "fa fa-truck-ramp-box", permission: "load" },
      { label: "load_request", to: "/transaction/load-request", icon: "fa fa-truck-loading", permission: "load request" },
      { label: "load_transfer", to: "/transaction/load-transfer", icon: "fa fa-right-left", permission: "load transfer" },
      { label: "customer_inventory", to: "/transaction/customer-inventory", icon: "fa fa-boxes-stacked", permission: "customer inventory" },
      { label: "invoice", to: "/transaction/invoice", icon: "fa fa-file-lines", permission: "invoice" },
      { label: "sales_order", to: "/transaction/sales-order", icon: "fa fa-file-invoice", permission: "sales order" },
      { label: "advance_payment", to: "/transaction/advance-payment", icon: "fa fa-money-check-dollar", permission: "advance payment" },
      { label: "ar_collection", to: "/transaction/ar-collection", icon: "fa fa-receipt", permission: "ar collection" },
      { label: "unload_inventory", to: "/transaction/unload-inventory", icon: "fa fa-box-open", permission: "unload inventory" },
      { label: "unload_variance", to: "/transaction/unload-variance", icon: "fa fa-triangle-exclamation", permission: "unload variance" },
      { label: "damage_return", to: "/transaction/damage-return", icon: "fa fa-arrow-rotate-left", permission: "damage return" },
      { label: "inventory_summary", to: "/transaction/inventory-summary", icon: "fa fa-table-cells-large", permission: "inventory summary" },
    ],
  },

  {
    label: "reports",
    icon: "fa fa-chart-column",
    permission: "reports",
    sub: [
      {
        label: "daily_report",
        icon: "fa fa-calendar-day",
        sub: [
          { label: "route_summary", to: "/reports/daily-report/route-summary", icon: "fa fa-route", permission: "route summary" },
          { label: "route_activity", to: "/reports/daily-report/route-activity", icon: "fa fa-list-check", permission: "route activity" },
          { label: "route_inventory", to: "/reports/daily-report/route-inventory", icon: "fa fa-boxes-stacked", permission: "route inventory" },
          { label: "route_trip_analysis", to: "/reports/daily-report/route-trip-analysis", icon: "fa fa-map-signs", permission: "route trip analysis" },
          { label: "route_deposit_summary", to: "/reports/daily-report/route-deposit-summary", icon: "fa fa-money-check-dollar", permission: "route deposit summary" },
          { label: "discount_summary", to: "/reports/daily-report/discount-summary", icon: "fa fa-percent", permission: "discount summary" },
          { label: "pricing_summary", to: "/reports/daily-report/pricing-summary", icon: "fa fa-tags", permission: "pricing summary" },
        ],
      },
      {
        label: "transaction_report",
        icon: "fa fa-receipt",
        sub: [
          { label: "sales_summary", to: "/reports/transaction-report/sales-summary", icon: "fa fa-chart-line", permission: "sales summary" },
          { label: "order_summary", to: "/reports/transaction-report/order-summary", icon: "fa fa-file-invoice", permission: "order summary" },
          { label: "bad_return_summary", to: "/reports/transaction-report/bad-return-summary", icon: "fa fa-arrow-rotate-left", permission: "bad return summary" },
          { label: "collection_summary", to: "/reports/transaction-report/collection-summary", icon: "fa fa-money-check-dollar", permission: "collection summary" },
          { label: "route_visit_summary", to: "/reports/transaction-report/route-visit-summary", icon: "fa fa-road-circle-check", permission: "route visit summary" },
          { label: "payment_summary", to: "/reports/transaction-report/payment-summary", icon: "fa fa-wallet", permission: "payment summary" },
          { label: "deposit_summary", to: "/reports/transaction-report/deposit-summary", icon: "fa fa-money-check-dollar", permission: "deposit summary" },
          { label: "final_deposit", to: "/reports/transaction-report/final-deposit", icon: "fa fa-money-bill-transfer", permission: "final deposit" },
          { label: "item_history", to: "/reports/transaction-report/item-history", icon: "fa fa-clock-rotate-left", permission: "item history" },
        ],
      },
      {
        label: "merchandizing_report",
        icon: "fa fa-store",
        sub: [
          { label: "pos_tracking", to: "/reports/merchandizing-report/pos-tracking", icon: "fa fa-map-location-dot", permission: "pos tracking" },
          { label: "survey_tracking", to: "/reports/merchandizing-report/survey-tracking", icon: "fa fa-clipboard-question", permission: "survey tracking" },
          { label: "waste_stock", to: "/reports/merchandizing-report/waste-stock", icon: "fa fa-trash-can", permission: "waste stock" },
          { label: "assets_availability", to: "/reports/merchandizing-report/assets-availability", icon: "fa fa-box-archive", permission: "assets availability" },
          { label: "merchandized_stock", to: "/reports/merchandizing-report/merchandized-stock", icon: "fa fa-boxes-stacked", permission: "merchandized stock" },
        ],
      },
      {
        label: "accounts_report",
        icon: "fa fa-wallet",
        sub: [
          { label: "route_ageing", to: "/reports/accounts-report/route-ageing", icon: "fa fa-hourglass-half", permission: "route ageing" },
          { label: "customer_ageing", to: "/reports/accounts-report/customer-ageing", icon: "fa fa-users-between-lines", permission: "customer ageing" },
          { label: "route_pending_balance", to: "/reports/accounts-report/route-pending-balance", icon: "fa fa-chart-simple", permission: "route pending balance" },
          { label: "customer_pending_balance", to: "/reports/accounts-report/customer-pending-balance", icon: "fa fa-file-invoice-dollar", permission: "customer pending balance" },
        ],
      },
      {
        label: "data_analysis",
        icon: "fa fa-chart-pie",
        sub: [
          { label: "route_monthly_revenue", to: "/reports/data-analysis/route-monthly-revenue", icon: "fa fa-chart-column", permission: "route monthly revenue" },
          { label: "sales_free_summary", to: "/reports/data-analysis/sales-free-summary", icon: "fa fa-chart-bar", permission: "sales free summary" },
          { label: "item_sales_summary", to: "/reports/data-analysis/item-sales-summary", icon: "fa fa-table-cells-large", permission: "item sales summary" },
          { label: "item_group_wise_sales", to: "/reports/data-analysis/item-group-wise-sales", icon: "fa fa-layer-group", permission: "item group wise sales" },
        ],
      },
    ],
  },

];

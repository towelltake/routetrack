/*
 * Main and demo navigation arrays
 *
 */

export default {
  main: [
    {
      name: "Dashboard",
      to: "/preview/backend/dashboard",
      icon: "si si-speedometer",
    },
    {
      name: "Route Pro V 2.0",
      to: "/preview/backend/dashboard",
      icon: "si si-speedometer",
    },
    {
      name: "Scheme",
      icon: "si si-badge",
      subActivePaths: "/scheme",
      sub: [
        {
          name: "Promotion",
          subActivePaths: "/scheme/promotion",
          sub: [
            {
              name: "Qualification Group",
              to: "/scheme/promotion/promo-group/qualification-group",
            },
            {
              name: "Assignment Group",
              to: "/scheme/promotion/promo-group/assignment-group",
            },
            {
              name: "Promo Plan",
              to: "/scheme/promotion/promo-plan",
            },
            {
              name: "Promo Key",
              to: "/scheme/promotion/promo-key",
            },
          ],
        },
        {
          name: "Special Price",
          subActivePaths: "/scheme/special-price",
          sub: [
            {
              name: "Pricing Plan",
              to: "/scheme/special-price/pricing-plan",
            },
            {
              name: "Pricing Key",
              to: "/scheme/special-price/pricing-key",
            },
          ],
        },
        {
          name: "Loyalty",
          subActivePaths: "/scheme/loyalty",
          sub: [
            {
              name: "Loyalty Group",
              to: "/scheme/loyalty/loyalty-group",
            },
            {
              name: "Loyalty Plan",
              to: "/scheme/loyalty/loyalty-plan",
            },
            {
              name: "Loyalty Key",
              to: "/scheme/loyalty/loyalty-key",
            },
          ],
        },
        {
          name: "Supervisor Free Contract",
          to: "/scheme/supervisor-free-contract",
        },
      ],
    },
    {
      name: "الترويج",
      icon: "si si-basket-loaded",
      subActivePaths: "/merchandizing",
      sub: [
        {
          name: "الاستبيان",
          subActivePaths: "/merchandizing/survey",
          sub: [
            {
              name: "تعريف الاستبيان",
              to: "/merchandizing/survey",
            },
            {
              name: "خطة الاستبيان",
              to: "/merchandizing/survey-plan",
            },
            {
              name: "مفتاح الاستبيان",
              to: "/merchandizing/survey-key",
            },
          ],
        },
        {
          name: "نقاط البيع",
          subActivePaths: "/merchandizing/pos",
          sub: [
            {
              name: "POS الرئيسي",
              to: "/merchandizing/pos-master",
            },
            {
              name: "حد POS للعميل",
              to: "/merchandizing/customer-pos-limit",
            },
            {
              name: "تعليمات POS",
              to: "/merchandizing/pos-instruction",
            },
          ],
        },
        {
          name: "المخطط المرئي",
          to: "/merchandizing/planogram",
        },
        {
          name: "الصور الملتقطة",
          to: "/merchandizing/images-captured",
        },
      ],
    },
    {
      name: "Inventory",
      icon: "si si-layers",
      subActivePaths: "/inventory",
      sub: [
        {
          name: "Company Group",
          to: "/inventory/companygroup",
        },
        {
          name: "Major Category",
          to: "/inventory/majorcategory",
        },
        {
          name: "Sub Major Category",
          to: "/inventory/submajorcategory",
        },
        {
          name: "Item Group",
          to: "/inventory/itemgroup",
        },
        {
          name: "Items",
          to: "/inventory/item",
        },
        {
          name: "Route Item Group",
          to: "/inventory/routeitemgroup",
        },
        {
          name: "Daily Salesman Load",
          to: "/inventory/dailysalesmanload",
        },
        {
          name: "Delivery",
          to: "/inventory/delivery",
        },
        {
          name: "Target & Commission",
          subActivePaths: "/inventory/target",
          sub: [
            {
              name: "Target Group",
              to: "/inventory/targetgroup",
            },
            {
              name: "Target & Commission",
              to: "/inventory/targetcommission",
            },
          ],
        },
      ],
    },
    {
      name: "Link",
      icon: "si si-link",
      subActivePaths: "/links",
      sub: [
        {
          name: "Category Key",
          to: "/links/category-key",
        },
        {
          name: "Promotion",
          to: "/links/promotion",
        },
        {
          name: "Special Price",
          to: "/links/special-price",
        },
        {
          name: "Survey",
          to: "/links/survey",
        },
        {
          name: "Outlet Product Code",
          to: "/links/outlet-product-code",
        },
        {
          name: "Route Item Group",
          to: "/links/route-item-group",
        },
        {
          name: "Active / In Active Items",
          to: "/links/active-inactive-items",
        },
        {
          name: "Planogram Key",
          to: "/links/planogram-key",
        },
        {
          name: "Items Group",
          to: "/links/items-group",
        },
      ],
    },
    {
      name: "Transaction",
      icon: "si si-shuffle",
      subActivePaths: "/transaction",
      sub: [
        {
          name: "Begin / Opening Stock",
          to: "/transaction/begin-opening-stock",
        },
        {
          name: "Load",
          to: "/transaction/load",
        },
        {
          name: "Load Request",
          to: "/transaction/load-request",
        },
        {
          name: "Load Transfer",
          to: "/transaction/load-transfer",
        },
        {
          name: "Customer Inventory",
          to: "/transaction/customer-inventory",
        },
        {
          name: "Invoice",
          to: "/transaction/invoice",
        },
        {
          name: "Sales Order",
          to: "/transaction/sales-order",
        },
        {
          name: "Advance Payment",
          to: "/transaction/advance-payment",
        },
        {
          name: "AR Collection",
          to: "/transaction/ar-collection",
        },
        {
          name: "Unload Inventory",
          to: "/transaction/unload-inventory",
        },
        {
          name: "Unload Variance",
          to: "/transaction/unload-variance",
        },
        {
          name: "Damage Return",
          to: "/transaction/damage-return",
        },
        {
          name: "Inventory Summary",
          to: "/transaction/inventory-summary",
        },
      ],
    },
    // You can also set an external link to your main navigation and it will render as a link
    // {
    //   name: "Link Name",
    //   to: "https://example.com",
    //   icon: "si si-link",
    //   target: "_blank", // You can also set its target property
    // },
    {
      name: "Page Packs",
      icon: "si si-layers",
      subActivePaths: "/preview/backend/page-packs",
      sub: [
        {
          name: "Blog",
          icon: "si si-pencil",
          subActivePaths: "/preview/backend/page-packs/blog",
          sub: [
            {
              name: "Classic",
              to: "/preview/backend/page-packs/blog/classic",
            },
            {
              name: "List",
              to: "/preview/backend/page-packs/blog/list",
            },
            {
              name: "Grid",
              to: "/preview/backend/page-packs/blog/grid",
            },
            {
              name: "Story",
              to: "/preview/backend/page-packs/blog/story",
            },
            {
              name: "Story Cover",
              to: "/preview/backend/page-packs/blog/story-cover",
            },
          ],
        },
        {
          name: "e-Learning",
          icon: "si si-graduation",
          subActivePaths: "/preview/backend/page-packs/elearning",
          sub: [
            {
              name: "Courses",
              to: "/preview/backend/page-packs/elearning/courses",
            },
            {
              name: "Course",
              to: "/preview/backend/page-packs/elearning/course",
            },
            {
              name: "Lesson",
              to: "/preview/backend/page-packs/elearning/lesson",
            },
          ],
        },
        {
          name: "Forum",
          icon: "si si-bubbles",
          subActivePaths: "/preview/backend/page-packs/forum",
          sub: [
            {
              name: "Categories",
              to: "/preview/backend/page-packs/forum/categories",
            },
            {
              name: "Topics",
              to: "/preview/backend/page-packs/forum/topics",
            },
            {
              name: "Discussion",
              to: "/preview/backend/page-packs/forum/discussion",
            },
          ],
        },
        {
          name: "Boxed Backend",
          icon: "si si-magnet",
          subActivePaths: "/preview/backend-boxed",
          sub: [
            {
              name: "Dashboard",
              to: "/preview/backend-boxed",
            },
            {
              name: "Search",
              to: "/preview/backend-boxed/search",
            },
            {
              name: "Simple 1",
              to: "/preview/backend-boxed/simple1",
            },
            {
              name: "Simple 2",
              to: "/preview/backend-boxed/simple2",
            },
            {
              name: "Image 1",
              to: "/preview/backend-boxed/image1",
            },
            {
              name: "Image 2",
              to: "/preview/backend-boxed/image2",
            },
          ],
        },
      ],
    },
    {
      name: "User Interface",
      heading: true,
    },
    {
      name: "Blocks",
      icon: "si si-energy",
      subActivePaths: "/preview/backend/blocks",
      sub: [
        {
          name: "Styles",
          to: "/preview/backend/blocks/styles",
        },
        {
          name: "Options",
          to: "/preview/backend/blocks/options",
        },
        {
          name: "Forms",
          to: "/preview/backend/blocks/forms",
        },
        {
          name: "Themed",
          to: "/preview/backend/blocks/themed",
        },
        {
          name: "API",
          to: "/preview/backend/blocks/api",
        },
      ],
    },
    {
      name: "Elements",
      icon: "si si-badge",
      subActivePaths: "/preview/backend/elements",
      sub: [
        {
          name: "Grid",
          to: "/preview/backend/elements/grid",
        },
        {
          name: "Typography",
          to: "/preview/backend/elements/typography",
        },
        {
          name: "Icons",
          to: "/preview/backend/elements/icons",
        },
        {
          name: "Buttons",
          to: "/preview/backend/elements/buttons",
        },
        {
          name: "Button Groups",
          to: "/preview/backend/elements/button-groups",
        },
        {
          name: "Dropdowns",
          to: "/preview/backend/elements/dropdowns",
        },
        {
          name: "Tabs",
          to: "/preview/backend/elements/tabs",
        },
        {
          name: "Navigation",
          to: "/preview/backend/elements/navigation",
        },
        {
          name: "Horizontal Navigation",
          to: "/preview/backend/elements/navigation-horizontal",
        },
        {
          name: "Mega Menu",
          to: "/preview/backend/elements/mega-menu",
        },
        {
          name: "Progress",
          to: "/preview/backend/elements/progress",
        },
        {
          name: "Alerts",
          to: "/preview/backend/elements/alerts",
        },
        {
          name: "Tooltips",
          to: "/preview/backend/elements/tooltips",
        },
        {
          name: "Popovers",
          to: "/preview/backend/elements/popovers",
        },
        {
          name: "Modals",
          to: "/preview/backend/elements/modals",
        },
        {
          name: "Images Overlay",
          to: "/preview/backend/elements/images-overlay",
        },
        {
          name: "Timeline",
          to: "/preview/backend/elements/timeline",
        },
        {
          name: "Ribbons",
          to: "/preview/backend/elements/ribbons",
        },
        {
          name: "Steps",
          to: "/preview/backend/elements/steps",
        },
        {
          name: "Animations",
          to: "/preview/backend/elements/animations",
        },
        {
          name: "Color Themes",
          to: "/preview/backend/elements/color-themes",
        },
      ],
    },
    {
      name: "Tables",
      icon: "si si-grid",
      subActivePaths: "/preview/backend/tables",
      sub: [
        {
          name: "Styles",
          to: "/preview/backend/tables/styles",
        },
        {
          name: "Responsive",
          to: "/preview/backend/tables/responsive",
        },
        {
          name: "Helpers",
          to: "/preview/backend/tables/helpers",
        },
      ],
    },
    {
      name: "Forms",
      icon: "si si-note",
      subActivePaths: "/preview/backend/forms",
      sub: [
        {
          name: "Elements",
          to: "/preview/backend/forms/elements",
        },
        {
          name: "Layouts",
          to: "/preview/backend/forms/layouts",
        },
        {
          name: "Input Groups",
          to: "/preview/backend/forms/input-groups",
        },
        {
          name: "Plugins",
          to: "/preview/backend/forms/plugins",
        },
        {
          name: "Editors",
          to: "/preview/backend/forms/editors",
        },
        {
          name: "Validation",
          to: "/preview/backend/forms/validation",
        },
      ],
    },
    {
      name: "Develop",
      heading: true,
    },
    {
      name: "Plugins",
      icon: "si si-wrench",
      subActivePaths: "/preview/backend/plugins",
      sub: [
        {
          name: "Image Cropper",
          to: "/preview/backend/plugins/image-cropper",
        },
        {
          name: "Charts",
          to: "/preview/backend/plugins/charts",
        },
        {
          name: "Calendar",
          to: "/preview/backend/plugins/calendar",
        },
        {
          name: "Carousel",
          to: "/preview/backend/plugins/carousel",
        },
        {
          name: "Offcanvas",
          to: "/preview/backend/plugins/offcanvas",
        },
        {
          name: "Syntax Highlighting",
          to: "/preview/backend/plugins/syntax-highlighting",
        },
        {
          name: "Rating",
          to: "/preview/backend/plugins/rating",
        },
        {
          name: "Dialogs",
          to: "/preview/backend/plugins/dialogs",
        },
        {
          name: "Notifications",
          to: "/preview/backend/plugins/notifications",
        },
        {
          name: "Gallery",
          to: "/preview/backend/plugins/gallery",
        },
      ],
    },
    {
      name: "Layout",
      icon: "si si-magic-wand",
      subActivePaths: "/preview/backend/layout",
      sub: [
        {
          name: "Page",
          subActivePaths: "/preview/backend/layout/page",
          sub: [
            {
              name: "Default",
              to: "/preview/backend/layout/page/default",
            },
            {
              name: "Flipped",
              to: "/preview/backend/layout/page/flipped",
            },
          ],
        },
        {
          name: "Dark Mode",
          subActivePaths: "/preview/backend/layout/dark-mode",
          sub: [
            {
              name: "On",
              to: "/preview/backend/layout/dark-mode/on",
            },
            {
              name: "Off",
              to: "/preview/backend/layout/dark-mode/off",
            },
            {
              name: "System",
              to: "/preview/backend/layout/dark-mode/system",
            },
          ],
        },
        {
          name: "Main Content",
          subActivePaths: "/preview/backend/layout/main-content",
          sub: [
            {
              name: "Full Width",
              to: "/preview/backend/layout/main-content/full-width",
            },
            {
              name: "Narrow",
              to: "/preview/backend/layout/main-content/narrow",
            },
            {
              name: "Boxed",
              to: "/preview/backend/layout/main-content/boxed",
            },
          ],
        },
        {
          name: "Header",
          subActivePaths: "/preview/backend/layout/header",
          sub: [
            {
              name: "Fixed",
              heading: true,
            },
            {
              name: "Light",
              to: "/preview/backend/layout/header/fixed-light",
            },
            {
              name: "Dark",
              to: "/preview/backend/layout/header/fixed-dark",
            },
            {
              name: "Static",
              heading: true,
            },
            {
              name: "Light",
              to: "/preview/backend/layout/header/static-light",
            },
            {
              name: "Dark",
              to: "/preview/backend/layout/header/static-dark",
            },
          ],
        },
        {
          name: "Sidebar",
          subActivePaths: "/preview/backend/layout/sidebar",
          sub: [
            {
              name: "Mini",
              to: "/preview/backend/layout/sidebar/mini",
            },
            {
              name: "Light",
              to: "/preview/backend/layout/sidebar/light",
            },
            {
              name: "Dark",
              to: "/preview/backend/layout/sidebar/dark",
            },
            {
              name: "Hidden",
              to: "/preview/backend/layout/sidebar/hidden",
            },
          ],
        },
        {
          name: "Side Overlay",
          subActivePaths: "/preview/backend/layout/side-overlay",
          sub: [
            {
              name: "Visible",
              to: "/preview/backend/layout/side-overlay/visible",
            },
            {
              name: "Hover Mode",
              to: "/preview/backend/layout/side-overlay/hover-mode",
            },
            {
              name: "No Page Overlay",
              to: "/preview/backend/layout/side-overlay/no-page-overlay",
            },
          ],
        },
        {
          name: "Loaders",
          to: "/preview/backend/layout/loaders",
        },
        {
          name: "API",
          to: "/preview/backend/layout/api",
        },
      ],
    },
    {
      name: "Multi Level Menu",
      icon: "si si-puzzle",
      sub: [
        {
          name: "Link 1-1",
          to: "#",
        },
        {
          name: "Link 1-2",
          to: "#",
        },
        {
          name: "Sub Level 2",
          sub: [
            {
              name: "Link 2-1",
              to: "#",
            },
            {
              name: "Link 2-2",
              to: "#",
            },
            {
              name: "Sub Level 3",
              sub: [
                {
                  name: "Link 4-1",
                  to: "#",
                },
                {
                  name: "Link 4-2",
                  to: "#",
                },
                {
                  name: "Sub Level 5",
                  sub: [
                    {
                      name: "Link 5-1",
                      to: "#",
                    },
                    {
                      name: "Link 5-2",
                      to: "#",
                    },
                    {
                      name: "Sub Level 6",
                      sub: [
                        {
                          name: "Link 6-1",
                          to: "#",
                        },
                        {
                          name: "Link 6-2",
                          to: "#",
                        },
                      ],
                    },
                  ],
                },
              ],
            },
          ],
        },
      ],
    },
    {
      name: "Pages",
      heading: true,
    },
    {
      name: "Generic",
      icon: "si si-layers",
      subActivePaths: "/preview/backend/pages/generic",
      sub: [
        {
          name: "Blank",
          to: "/preview/backend/pages/generic/blank",
        },
        {
          name: "Blank (Block)",
          to: "/preview/backend/pages/generic/blank-block",
        },
        {
          name: "Search",
          to: "/preview/backend/pages/generic/search",
        },
        {
          name: "Profile",
          to: "/preview/backend/pages/generic/profile",
        },
        {
          name: "Profile Edit",
          to: "/preview/backend/pages/generic/profile-edit",
        },
        {
          name: "Invoice",
          to: "/preview/backend/pages/generic/invoice",
        },
        {
          name: "Pricing Plans",
          to: "/preview/backend/pages/generic/pricing-plans",
        },
        {
          name: "FAQ",
          to: "/preview/backend/pages/generic/faq",
        },
        {
          name: "Team",
          to: "/preview/backend/pages/generic/team",
        },
        {
          name: "Contact",
          to: "/preview/backend/pages/generic/contact",
        },
        {
          name: "Support",
          to: "/preview/backend/pages/generic/support",
        },
        {
          name: "Inbox",
          to: "/preview/backend/pages/generic/inbox",
        },
        {
          name: "Sidebar with Mini Nav",
          to: "/preview/backend/pages/generic/sidebar-mini-nav",
        },
        {
          name: "Maintenance",
          to: "/preview/specials/maintenance",
        },
        {
          name: "Status",
          to: "/preview/specials/status",
        },
        {
          name: "Installation",
          to: "/preview/specials/installation",
        },
        {
          name: "Checkout",
          to: "/preview/specials/checkout",
        },
        {
          name: "Coming Soon",
          to: "/preview/specials/coming-soon",
        },
      ],
    },
    {
      name: "Authentication",
      icon: "si si-lock",
      subActivePaths: "/preview/backend/pages/auth",
      sub: [
        {
          name: "All",
          to: "/preview/backend/pages/auth",
        },
        {
          name: "Sign In",
          to: "/preview/auth/signin",
        },
        {
          name: "Sign In 2",
          to: "/preview/auth/signin2",
        },
        {
          name: "Sign In 3",
          to: "/preview/auth/signin3",
        },
        {
          name: "Sign Up",
          to: "/preview/auth/signup",
        },
        {
          name: "Sign Up 2",
          to: "/preview/auth/signup2",
        },
        {
          name: "Sign Up 3",
          to: "/preview/auth/signup3",
        },
        {
          name: "Lock Screen",
          to: "/preview/auth/lock",
        },
        {
          name: "Lock Screen 2",
          to: "/preview/auth/lock2",
        },
        {
          name: "Lock Screen 3",
          to: "/preview/auth/lock3",
        },
        {
          name: "Pass Reminder",
          to: "/preview/auth/reminder",
        },
        {
          name: "Pass Reminder 2",
          to: "/preview/auth/reminder2",
        },
        {
          name: "Pass Reminder 3",
          to: "/preview/auth/reminder3",
        },
        {
          name: "Two Factor",
          to: "/preview/auth/two-factor",
        },
        {
          name: "Two Factor 2",
          to: "/preview/auth/two-factor2",
        },
        {
          name: "Two Factor 3",
          to: "/preview/auth/two-factor3",
        },
      ],
    },
    {
      name: "Error Pages",
      icon: "si si-fire",
      subActivePaths: "/preview/backend/pages/errors",
      sub: [
        {
          name: "All",
          to: "/preview/backend/pages/errors",
        },
        {
          name: "400",
          to: "/preview/errors/400",
        },
        {
          name: "401",
          to: "/preview/errors/401",
        },
        {
          name: "403",
          to: "/preview/errors/403",
        },
        {
          name: "404",
          to: "/preview/errors/404",
        },
        {
          name: "500",
          to: "/preview/errors/500",
        },
        {
          name: "503",
          to: "/preview/errors/503",
        },
      ],
    },
  ],
  boxed: [
    {
      name: "Dashboard",
      to: "/preview/backend-boxed/",
      icon: "si si-compass",
    },
    {
      name: "Pages",
      heading: true,
    },
    {
      name: "Variations",
      icon: "si si-puzzle",
      sub: [
        {
          name: "Simple 1",
          to: "/preview/backend-boxed/simple1",
        },
        {
          name: "Simple 2",
          to: "/preview/backend-boxed/simple2",
        },
        {
          name: "Image 1",
          to: "/preview/backend-boxed/image1",
        },
        {
          name: "Image 2",
          to: "/preview/backend-boxed/image2",
        },
      ],
    },
    {
      name: "Search",
      to: "/preview/backend-boxed/search",
      icon: "si si-magnifier",
    },
    {
      name: "Go Back",
      to: "/preview/backend/dashboard",
      icon: "si si-action-undo",
    },
  ],
  demo: [
    {
      name: "Home",
      to: "#",
      icon: "fa fa-home",
      badge: 5,
    },
    {
      name: "Manage",
      heading: true,
    },
    {
      name: "Products",
      icon: "fa fa-briefcase",
      sub: [
        {
          name: "HTML Templates",
          icon: "fab fa-html5",
          sub: [
            {
              name: "Description",
              to: "#",
              icon: "fa fa-pencil-alt",
            },
            {
              name: "Statistics",
              to: "#",
              icon: "fa fa-chart-line",
            },
            {
              name: "Sales",
              to: "#",
              icon: "fa fa-chart-area",
              badge: 320,
            },
            {
              name: "Media",
              to: "#",
              icon: "far fa-images",
              badge: 18,
            },
            {
              name: "Files",
              to: "#",
              icon: "far fa-file-alt",
              badge: 32,
            },
          ],
        },
        {
          name: "WordPress Themes",
          icon: "fab fa-wordpress",
          sub: [
            {
              name: "Description",
              to: "#",
              icon: "fa fa-pencil-alt",
            },
            {
              name: "Statistics",
              to: "#",
              icon: "fa fa-chart-line",
            },
            {
              name: "Sales",
              to: "#",
              icon: "fa fa-chart-area",
              badge: 680,
            },
            {
              name: "Media",
              to: "#",
              icon: "far fa-images",
              badge: 15,
            },
            {
              name: "Files",
              to: "#",
              icon: "far fa-file-alt",
              badge: 20,
            },
          ],
        },
        {
          name: "Web Applications",
          icon: "fab fa-medapps",
          sub: [
            {
              name: "Description",
              to: "#",
              icon: "fa fa-pencil-alt",
            },
            {
              name: "Statistics",
              to: "#",
              icon: "fa fa-chart-line",
            },
            {
              name: "Sales",
              to: "#",
              icon: "fa fa-chart-area",
              badge: 158,
            },
            {
              name: "Media",
              to: "#",
              icon: "far fa-images",
              badge: 65,
            },
            {
              name: "Files",
              to: "#",
              icon: "far fa-file-alt",
              badge: 78,
            },
          ],
        },
        {
          name: "Video Templates",
          icon: "fab fa-youtube",
          sub: [
            {
              name: "Description",
              to: "#",
              icon: "fa fa-pencil-alt",
            },
            {
              name: "Statistics",
              to: "#",
              icon: "fa fa-chart-line",
            },
            {
              name: "Sales",
              to: "#",
              icon: "fa fa-chart-area",
              badge: 920,
            },
            {
              name: "Media",
              to: "#",
              icon: "far fa-images",
              badge: 7,
            },
            {
              name: "Files",
              to: "#",
              icon: "far fa-file-alt",
              badge: 19,
            },
          ],
        },
        {
          name: "Add Product",
          to: "#",
          icon: "fa fa-plus",
        },
      ],
    },
    {
      name: "Payments",
      icon: "fa fa-money-bill-wave",
      sub: [
        {
          name: "Scheduled",
          to: "#",
          badge: 3,
          "badge-variant": "success",
        },
        {
          name: "Recurring",
          to: "#",
        },
        {
          name: "Manage",
          to: "#",
        },
        {
          name: "New Payment",
          to: "#",
          icon: "fa fa-plus",
        },
      ],
    },
    {
      name: "Services",
      icon: "fa fa-globe-americas",
      sub: [
        {
          name: "Hosting",
          to: "#",
        },
        {
          name: "Web Design",
          to: "#",
        },
        {
          name: "Web Development",
          to: "#",
        },
        {
          name: "Graphic Design",
          to: "#",
        },
        {
          name: "Legal",
          to: "#",
        },
        {
          name: "Consulting",
          to: "#",
        },
      ],
    },
    {
      name: "Personal",
      heading: true,
    },
    {
      name: "Profile",
      icon: "far fa-user",
      sub: [
        {
          name: "Edit",
          to: "#",
        },
        {
          name: "Settings",
          to: "#",
        },
        {
          name: "Log out",
          to: "#",
        },
      ],
    },
  ],
};

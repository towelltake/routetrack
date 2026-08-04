<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Preview Pages Routes
|--------------------------------------------------------------------------
*/

Route::prefix('preview')->group(function () {
    // Landing Routes
    Route::get('/', function () {
        return Inertia::render('preview/landing/LandingView');
    })->name('preview.landing');

    // Backend Routes
    Route::prefix('backend')->group(function () {
        // Dashboard
        Route::get('/dashboard', function () {
            return Inertia::render('preview/backend/DashboardView');
        })->name('preview.backend.dashboard');

        // Page Packs Routes
        Route::prefix('page-packs')->group(function () {
            // Blog Routes
            Route::prefix('blog')->group(function () {
                Route::get('/classic', function () {
                    return Inertia::render('preview/backend/blog/ClassicView');
                })->name('preview.backend.page-packs.blog.classic');

                Route::get('/list', function () {
                    return Inertia::render('preview/backend/blog/ListView');
                })->name('preview.backend.page-packs.blog.list');

                Route::get('/grid', function () {
                    return Inertia::render('preview/backend/blog/GridView');
                })->name('preview.backend.page-packs.blog.grid');

                Route::get('/story', function () {
                    return Inertia::render('preview/backend/blog/StoryView');
                })->name('preview.backend.page-packs.blog.story');

                Route::get('/story-cover', function () {
                    return Inertia::render('preview/backend/blog/StoryCoverView');
                })->name('preview.backend.page-packs.blog.story-cover');
            });

            // e-Learning Routes
            Route::prefix('elearning')->group(function () {
                Route::get('/courses', function () {
                    return Inertia::render('preview/backend/elearning/CoursesView');
                })->name('preview.backend.page-packs.elearning.courses');

                Route::get('/course', function () {
                    return Inertia::render('preview/backend/elearning/CourseView');
                })->name('preview.backend.page-packs.elearning.course');

                Route::get('/lesson', function () {
                    return Inertia::render('preview/backend/elearning/LessonView');
                })->name('preview.backend.page-packs.elearning.lesson');
            });

            // Forum Routes
            Route::prefix('forum')->group(function () {
                Route::get('/categories', function () {
                    return Inertia::render('preview/backend/forum/CategoriesView');
                })->name('preview.backend.page-packs.forum.categories');

                Route::get('/topics', function () {
                    return Inertia::render('preview/backend/forum/TopicsView');
                })->name('preview.backend.page-packs.forum.topics');

                Route::get('/discussion', function () {
                    return Inertia::render('preview/backend/forum/DiscussionView');
                })->name('preview.backend.page-packs.forum.discussion');
            });
        });

        // Blocks Routes
        Route::prefix('blocks')->group(function () {
            Route::get('/styles', function () {
                return Inertia::render('preview/backend/blocks/StylesView');
            })->name('preview.backend.blocks.styles');

            Route::get('/options', function () {
                return Inertia::render('preview/backend/blocks/OptionsView');
            })->name('preview.backend.blocks.options');

            Route::get('/forms', function () {
                return Inertia::render('preview/backend/blocks/FormsView');
            })->name('preview.backend.blocks.forms');

            Route::get('/themed', function () {
                return Inertia::render('preview/backend/blocks/ThemedView');
            })->name('preview.backend.blocks.themed');

            Route::get('/api', function () {
                return Inertia::render('preview/backend/blocks/ApiView');
            })->name('preview.backend.blocks.api');
        });

        // Elements Routes
        Route::prefix('elements')->group(function () {
            Route::get('/grid', function () {
                return Inertia::render('preview/backend/elements/GridView');
            })->name('preview.backend.elements.grid');

            Route::get('/typography', function () {
                return Inertia::render('preview/backend/elements/TypographyView');
            })->name('preview.backend.elements.typography');

            Route::get('/icons', function () {
                return Inertia::render('preview/backend/elements/IconsView');
            })->name('preview.backend.elements.icons');

            Route::get('/buttons', function () {
                return Inertia::render('preview/backend/elements/ButtonsView');
            })->name('preview.backend.elements.buttons');

            Route::get('/button-groups', function () {
                return Inertia::render('preview/backend/elements/ButtonGroupsView');
            })->name('preview.backend.elements.button-groups');

            Route::get('/dropdowns', function () {
                return Inertia::render('preview/backend/elements/DropdownsView');
            })->name('preview.backend.elements.dropdowns');

            Route::get('/tabs', function () {
                return Inertia::render('preview/backend/elements/TabsView');
            })->name('preview.backend.elements.tabs');

            Route::get('/navigation', function () {
                return Inertia::render('preview/backend/elements/NavigationView');
            })->name('preview.backend.elements.navigation');

            Route::get('/navigation-horizontal', function () {
                return Inertia::render('preview/backend/elements/NavigationHorizontalView');
            })->name('preview.backend.elements.navigation-horizontal');

            Route::get('/mega-menu', function () {
                return Inertia::render('preview/backend/elements/MegaMenuView');
            })->name('preview.backend.elements.mega-menu');

            Route::get('/progress', function () {
                return Inertia::render('preview/backend/elements/ProgressView');
            })->name('preview.backend.elements.progress');

            Route::get('/alerts', function () {
                return Inertia::render('preview/backend/elements/AlertsView');
            })->name('preview.backend.elements.alerts');

            Route::get('/tooltips', function () {
                return Inertia::render('preview/backend/elements/TooltipsView');
            })->name('preview.backend.elements.tooltips');

            Route::get('/popovers', function () {
                return Inertia::render('preview/backend/elements/PopoversView');
            })->name('preview.backend.elements.popovers');

            Route::get('/modals', function () {
                return Inertia::render('preview/backend/elements/ModalsView');
            })->name('preview.backend.elements.modals');

            Route::get('/images-overlay', function () {
                return Inertia::render('preview/backend/elements/ImagesOverlayView');
            })->name('preview.backend.elements.images-overlay');

            Route::get('/timeline', function () {
                return Inertia::render('preview/backend/elements/TimelineView');
            })->name('preview.backend.elements.timeline');

            Route::get('/ribbons', function () {
                return Inertia::render('preview/backend/elements/RibbonsView');
            })->name('preview.backend.elements.ribbons');

            Route::get('/steps', function () {
                return Inertia::render('preview/backend/elements/StepsView');
            })->name('preview.backend.elements.steps');

            Route::get('/animations', function () {
                return Inertia::render('preview/backend/elements/AnimationsView');
            })->name('preview.backend.elements.animations');

            Route::get('/color-themes', function () {
                return Inertia::render('preview/backend/elements/ColorThemesView');
            })->name('preview.backend.elements.color-themes');
        });

        // Tables Routes
        Route::prefix('tables')->group(function () {
            Route::get('/styles', function () {
                return Inertia::render('preview/backend/tables/StylesView');
            })->name('preview.backend.tables.styles');

            Route::get('/responsive', function () {
                return Inertia::render('preview/backend/tables/ResponsiveView');
            })->name('preview.backend.tables.responsive');

            Route::get('/helpers', function () {
                return Inertia::render('preview/backend/tables/HelpersView');
            })->name('preview.backend.tables.helpers');
        });

        // Forms Routes
        Route::prefix('forms')->group(function () {
            Route::get('/elements', function () {
                return Inertia::render('preview/backend/forms/ElementsView');
            })->name('preview.backend.forms.elements');

            Route::get('/layouts', function () {
                return Inertia::render('preview/backend/forms/LayoutsView');
            })->name('preview.backend.forms.layouts');

            Route::get('/input-groups', function () {
                return Inertia::render('preview/backend/forms/InputGroupsView');
            })->name('preview.backend.forms.input-groups');

            Route::get('/plugins', function () {
                return Inertia::render('preview/backend/forms/PluginsView');
            })->name('preview.backend.forms.plugins');

            Route::get('/editors', function () {
                return Inertia::render('preview/backend/forms/EditorsView');
            })->name('preview.backend.forms.editors');

            Route::get('/validation', function () {
                return Inertia::render('preview/backend/forms/ValidationView');
            })->name('preview.backend.forms.validation');
        });

        // Plugins Routes
        Route::prefix('plugins')->group(function () {
            Route::get('/image-cropper', function () {
                return Inertia::render('preview/backend/plugins/ImageCropperView');
            })->name('preview.backend.plugins.image-cropper');

            Route::get('/charts', function () {
                return Inertia::render('preview/backend/plugins/ChartsView');
            })->name('preview.backend.plugins.charts');

            Route::get('/calendar', function () {
                return Inertia::render('preview/backend/plugins/CalendarView');
            })->name('preview.backend.plugins.calendar');

            Route::get('/carousel', function () {
                return Inertia::render('preview/backend/plugins/CarouselView');
            })->name('preview.backend.plugins.carousel');

            Route::get('/offcanvas', function () {
                return Inertia::render('preview/backend/plugins/OffcanvasView');
            })->name('preview.backend.plugins.offcanvas');

            Route::get('/syntax-highlighting', function () {
                return Inertia::render('preview/backend/plugins/SyntaxHighlightingView');
            })->name('preview.backend.plugins.syntax-highlighting');

            Route::get('/rating', function () {
                return Inertia::render('preview/backend/plugins/RatingView');
            })->name('preview.backend.plugins.rating');

            Route::get('/dialogs', function () {
                return Inertia::render('preview/backend/plugins/DialogsView');
            })->name('preview.backend.plugins.dialogs');

            Route::get('/notifications', function () {
                return Inertia::render('preview/backend/plugins/NotificationsView');
            })->name('preview.backend.plugins.notifications');

            Route::get('/gallery', function () {
                return Inertia::render('preview/backend/plugins/GalleryView');
            })->name('preview.backend.plugins.gallery');
        });

        // Layout Routes
        Route::prefix('layout')->group(function () {
            Route::prefix('page')->group(function () {
                Route::get('/default', function () {
                    return Inertia::render('preview/backend/layout/page/DefaultView');
                })->name('preview.backend.layout.page.default');

                Route::get('/flipped', function () {
                    return Inertia::render('preview/backend/layout/page/FlippedView');
                })->name('preview.backend.layout.page.flipped');
            });

            Route::prefix('dark-mode')->group(function () {
                Route::get('/on', function () {
                    return Inertia::render('preview/backend/layout/dark-mode/OnView');
                })->name('preview.backend.layout.dark-mode.on');

                Route::get('/off', function () {
                    return Inertia::render('preview/backend/layout/dark-mode/OffView');
                })->name('preview.backend.layout.dark-mode.off');

                Route::get('/system ', function () {
                    return Inertia::render('preview/backend/layout/dark-mode/SystemView');
                })->name('preview.backend.layout.dark-mode.system');
            });

            Route::prefix('main-content')->group(function () {
                Route::get('/full-width', function () {
                    return Inertia::render('preview/backend/layout/main-content/FullWidthView');
                })->name('preview.backend.layout.main-content.full-width');

                Route::get('/narrow', function () {
                    return Inertia::render('preview/backend/layout/main-content/NarrowView');
                })->name('preview.backend.layout.main-content.narrow');

                Route::get('/boxed', function () {
                    return Inertia::render('preview/backend/layout/main-content/BoxedView');
                })->name('preview.backend.layout.main-content.boxed');
            });

            Route::prefix('header')->group(function () {
                Route::get('/fixed-light', function () {
                    return Inertia::render('preview/backend/layout/header/FixedLightView');
                })->name('preview.backend.layout.header.fixed-light');

                Route::get('/fixed-dark', function () {
                    return Inertia::render('preview/backend/layout/header/FixedDarkView');
                })->name('preview.backend.layout.header.fixed-dark');

                Route::get('/static-light', function () {
                    return Inertia::render('preview/backend/layout/header/StaticLightView');
                })->name('preview.backend.layout.header.static-light');

                Route::get('/static-dark', function () {
                    return Inertia::render('preview/backend/layout/header/StaticDarkView');
                })->name('preview.backend.layout.header.static-dark');
            });

            Route::prefix('sidebar')->group(function () {
                Route::get('/mini', function () {
                    return Inertia::render('preview/backend/layout/sidebar/MiniView');
                })->name('preview.backend.layout.sidebar.mini');

                Route::get('/light', function () {
                    return Inertia::render('preview/backend/layout/sidebar/LightView');
                })->name('preview.backend.layout.sidebar.light');

                Route::get('/dark', function () {
                    return Inertia::render('preview/backend/layout/sidebar/DarkView');
                })->name('preview.backend.layout.sidebar.dark');

                Route::get('/hidden', function () {
                    return Inertia::render('preview/backend/layout/sidebar/HiddenView');
                })->name('preview.backend.layout.sidebar.hidden');
            });

            Route::prefix('side-overlay')->group(function () {
                Route::get('/visible', function () {
                    return Inertia::render('preview/backend/layout/side-overlay/VisibleView');
                })->name('preview.backend.layout.side-overlay.visible');

                Route::get('/hover-mode', function () {
                    return Inertia::render('preview/backend/layout/side-overlay/HoverModeView');
                })->name('preview.backend.layout.side-overlay.hover-mode');

                Route::get('/no-page-overlay', function () {
                    return Inertia::render('preview/backend/layout/side-overlay/NoPageOverlayView');
                })->name('preview.backend.layout.side-overlay.no-page-overlay');
            });

            Route::get('/loaders', function () {
                return Inertia::render('preview/backend/layout/LoadersView');
            })->name('preview.backend.layout.loaders');

            Route::get('/api', function () {
                return Inertia::render('preview/backend/layout/ApiView');
            })->name('preview.backend.layout.api');
        });

        // Pages Routes
        Route::prefix('pages')->group(function () {
            Route::get('/auth', function () {
                return Inertia::render('preview/backend/pages/AuthView');
            })->name('preview.backend.pages.auth');

            Route::get('/errors', function () {
                return Inertia::render('preview/backend/pages/ErrorsView');
            })->name('preview.backend.pages.errors');

            // Generic Pages Routes
            Route::prefix('generic')->group(function () {
                Route::get('/blank', function () {
                    return Inertia::render('preview/backend/pages/generic/BlankView');
                })->name('preview.backend.pages.generic.blank');

                Route::get('/blank-block', function () {
                    return Inertia::render('preview/backend/pages/generic/BlankBlockView');
                })->name('preview.backend.pages.generic.blank-block');

                Route::get('/search', function () {
                    return Inertia::render('preview/backend/pages/generic/SearchView');
                })->name('preview.backend.pages.generic.search');

                Route::get('/profile', function () {
                    return Inertia::render('preview/backend/pages/generic/ProfileView');
                })->name('preview.backend.pages.generic.profile');

                Route::get('/profile-edit', function () {
                    return Inertia::render('preview/backend/pages/generic/ProfileEditView');
                })->name('preview.backend.pages.generic.profile-edit');

                Route::get('/invoice', function () {
                    return Inertia::render('preview/backend/pages/generic/InvoiceView');
                })->name('preview.backend.pages.generic.invoice');

                Route::get('/pricing-plans', function () {
                    return Inertia::render('preview/backend/pages/generic/PricingPlansView');
                })->name('preview.backend.pages.generic.pricing-plans');

                Route::get('/faq', function () {
                    return Inertia::render('preview/backend/pages/generic/FaqView');
                })->name('preview.backend.pages.generic.faq');

                Route::get('/team', function () {
                    return Inertia::render('preview/backend/pages/generic/TeamView');
                })->name('preview.backend.pages.generic.team');

                Route::get('/contact', function () {
                    return Inertia::render('preview/backend/pages/generic/ContactView');
                })->name('preview.backend.pages.generic.contact');

                Route::get('/support', function () {
                    return Inertia::render('preview/backend/pages/generic/SupportView');
                })->name('preview.backend.pages.generic.support');

                Route::get('/inbox', function () {
                    return Inertia::render('preview/backend/pages/generic/InboxView');
                })->name('preview.backend.pages.generic.inbox');

                Route::get('/sidebar-mini-nav', function () {
                    return Inertia::render('preview/backend/pages/generic/SidebarMiniNavView');
                })->name('preview.backend.pages.generic.sidebar-mini-nav');
            });
        });
    });

    // Backend Boxed Routes
    Route::prefix('backend-boxed')->group(function () {
        Route::get('/', function () {
            return Inertia::render('preview/backend-boxed/DashboardView');
        })->name('preview.backend-boxed.dashboard');

        Route::get('/simple1', function () {
            return Inertia::render('preview/backend-boxed/Simple1View');
        })->name('preview.backend-boxed.simple1');

        Route::get('/simple2', function () {
            return Inertia::render('preview/backend-boxed/Simple2View');
        })->name('preview.backend-boxed.simple2');

        Route::get('/image1', function () {
            return Inertia::render('preview/backend-boxed/Image1View');
        })->name('preview.backend-boxed.image1');

        Route::get('/image2', function () {
            return Inertia::render('preview/backend-boxed/Image2View');
        })->name('preview.backend-boxed.image2');

        Route::get('/search', function () {
            return Inertia::render('preview/backend-boxed/SearchView');
        })->name('preview.backend-boxed.search');
    });

    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::get('/signin', function () {
            return Inertia::render('preview/auth/SignInView');
        })->name('preview.auth.sign-in');

        Route::get('/signin2', function () {
            return Inertia::render('preview/auth/SignIn2View');
        })->name('preview.auth.sign-in2');

        Route::get('/signin3', function () {
            return Inertia::render('preview/auth/SignIn3View');
        })->name('preview.auth.sign-in3');

        Route::get('/signup', function () {
            return Inertia::render('preview/auth/SignUpView');
        })->name('preview.auth.sign-up');

        Route::get('/signup2', function () {
            return Inertia::render('preview/auth/SignUp2View');
        })->name('preview.auth.sign-up2');

        Route::get('/signup3', function () {
            return Inertia::render('preview/auth/SignUp3View');
        })->name('preview.auth.sign-up3');

        Route::get('/lock', function () {
            return Inertia::render('preview/auth/LockView');
        })->name('preview.auth.lock');

        Route::get('/lock2', function () {
            return Inertia::render('preview/auth/Lock2View');
        })->name('preview.auth.lock2');

        Route::get('/lock3', function () {
            return Inertia::render('preview/auth/Lock3View');
        })->name('preview.auth.lock3');

        Route::get('/reminder', function () {
            return Inertia::render('preview/auth/ReminderView');
        })->name('preview.auth.reminder');

        Route::get('/reminder2', function () {
            return Inertia::render('preview/auth/Reminder2View');
        })->name('preview.auth.reminder2');

        Route::get('/reminder3', function () {
            return Inertia::render('preview/auth/Reminder3View');
        })->name('preview.auth.reminder3');

        Route::get('/two-factor', function () {
            return Inertia::render('preview/auth/TwoFactorView');
        })->name('preview.auth.two-factor');

        Route::get('/two-factor2', function () {
            return Inertia::render('preview/auth/TwoFactor2View');
        })->name('preview.auth.two-factor2');

        Route::get('/two-factor3', function () {
            return Inertia::render('preview/auth/TwoFactor3View');
        })->name('preview.auth.two-factor3');
    });

    // Error Routes
    Route::prefix('errors')->group(function () {
        Route::get('/400', function () {
            return Inertia::render('preview/errors/400View');
        })->name('preview.errors.400');

        Route::get('/401', function () {
            return Inertia::render('preview/errors/401View');
        })->name('preview.errors.401');

        Route::get('/403', function () {
            return Inertia::render('preview/errors/403View');
        })->name('preview.errors.403');

        Route::get('/404', function () {
            return Inertia::render('preview/errors/404View');
        })->name('preview.errors.404');

        Route::get('/500', function () {
            return Inertia::render('preview/errors/500View');
        })->name('preview.errors.500');

        Route::get('/503', function () {
            return Inertia::render('preview/errors/503View');
        })->name('preview.errors.503');
    });

    // Special Routes
    Route::prefix('specials')->group(function () {
        Route::get('/maintenance', function () {
            return Inertia::render('preview/specials/MaintenanceView');
        })->name('preview.specials.maintenance');

        Route::get('/status', function () {
            return Inertia::render('preview/specials/StatusView');
        })->name('preview.specials.status');

        Route::get('/installation', function () {
            return Inertia::render('preview/specials/InstallationView');
        })->name('preview.specials.installation');

        Route::get('/checkout', function () {
            return Inertia::render('preview/specials/CheckoutView');
        })->name('preview.specials.checkout');

        Route::get('/coming-soon', function () {
            return Inertia::render('preview/specials/ComingSoonView');
        })->name('preview.specials.coming-soon');
    });
});

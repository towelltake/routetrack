<script setup></script>

<template>
  <Head title="Welcome to your app" />

  <!-- Hero -->
  <div class="bg-body-extra-light">
    <div class="content content-full text-center">
      <div class="row justify-content-center py-8">
        <div class="col-lg-9">
          <div class="d-flex align-items-center justify-content-center gap-3">
            <i class="fab fa-3x fa-laravel" style="color: #f72c1f"></i>
            +
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 500 500"
              class="d-inline-block"
              style="width: 44px; height: 44px"
            >
              <rect width="500" height="500" fill="url(#a)" rx="250" />
              <path fill="#fff" d="M184 165H95l86 86-86 86h89l86-86-86-86Z" />
              <path
                fill="#fff"
                d="M318.5 165h-89l86 86-86 86h89l86-86-86-86Z"
              />
              <defs>
                <linearGradient
                  id="a"
                  x1="35"
                  x2="632.5"
                  y1="377.5"
                  y2="65"
                  gradientUnits="userSpaceOnUse"
                >
                  <stop stop-color="#934EE7" />
                  <stop offset="1" stop-color="#7270EC" />
                </linearGradient>
              </defs>
            </svg>
            +
            <i class="fab fa-3x fa-vuejs" style="color: #3fb27f"></i>
            +
            <i class="fa fa-2x text-primary fa-circle-notch"></i>
          </div>
          <h1 class="fw-black mt-3 mb-2">
            Welcome to your brand new Laravel project!
          </h1>
          <p class="fs-lg fw-medium text-muted mb-4">
            It uses Laravel 12, Inertia.js, Vue.js and OneUI Vue Edition. It
            comes packed with authentication features out of the box, so feel
            free to
            <Link href="/register">create an account</Link> and
            <Link href="/login">log in</Link> to your dashboard.
          </p>
          <div class="d-flex justify-content-center gap-2">
            <Link href="/preview" class="btn btn-lg btn-primary" v-click-ripple>
              Preview all demo pages
              <i class="fa fa-fw fa-arrow-right ms-1 opacity-50"></i>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- END Hero -->

  <!-- Features -->
  <div id="one-vue-features" class="bg-body-light">
    <div class="content content-full">
      <div class="py-5">
        <div class="row mb-5">
          <div class="col-md-6">
            <h2 class="h1 fw-black mb-2">
              Sophisticated
              <span class="fw-normal">Features</span>
            </h2>
            <p class="fs-lg fw-medium text-muted mb-0">
              The Laravel version of OneUI Vue Edition comes packed with amazing
              features out of the box.
            </p>
          </div>
          <div
            class="col-md-6 d-none d-md-flex align-items-md-center justify-content-md-end"
          >
            <p class="h1 fw-bold text-body-bg-dark mb-0">Carefully Crafted.</p>
          </div>
        </div>
        <div class="row g-6">
          <div class="col-sm-6">
            <!-- Laravel 12 -->
            <div class="item item-rounded bg-body-extra-light my-4">
              <i class="fab fa-2x fa-laravel" style="color: #f72c1f"></i>
            </div>
            <h4 class="mb-2">Laravel 12</h4>
            <p class="text-muted mb-0">
              The popular PHP framework, powers this application. It brings
              enhanced performance, improved security features, and streamlined
              development processes, ensuring your project is built on a robust
              foundation.
            </p>
            <!-- END Laravel 12 -->
          </div>
          <div class="col-sm-6">
            <!-- Inertia.js -->
            <div class="item item-rounded bg-body-extra-light my-4">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 500 500"
                class="d-inline-block"
                style="width: 40px; height: 40px"
              >
                <rect width="500" height="500" fill="url(#a)" rx="250" />
                <path fill="#fff" d="M184 165H95l86 86-86 86h89l86-86-86-86Z" />
                <path
                  fill="#fff"
                  d="M318.5 165h-89l86 86-86 86h89l86-86-86-86Z"
                />
                <defs>
                  <linearGradient
                    id="a"
                    x1="35"
                    x2="632.5"
                    y1="377.5"
                    y2="65"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop stop-color="#934EE7" />
                    <stop offset="1" stop-color="#7270EC" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
            <h4 class="mb-2">Inertia.js</h4>
            <p class="text-muted mb-0">
              It seamlessly connects your Laravel backend with your Vue.js
              frontend, allowing you to build modern single-page applications
              without the complexity of a traditional SPA.
            </p>
            <!-- END Inertia.js -->
          </div>
          <div class="col-sm-6">
            <!-- OneUI Vue Edition -->
            <div class="item item-rounded bg-body-extra-light my-4">
              <i class="fa fa-2x fa-circle-notch text-primary"></i>
            </div>
            <h4 class="mb-2">OneUI Vue Edition</h4>
            <p class="text-muted mb-0">
              One super flexible UI framework for web developers and founders
              who value their time. Built with Bootstrap 5 and Vue.js 3. It now
              comes with a brand new dark mode.
            </p>
            <!-- END OneUI Vue Edition -->
          </div>
          <div class="col-sm-6">
            <!-- Authentication -->
            <div class="item item-rounded bg-body-extra-light my-4">
              <i class="fa fa-2x fa-lock text-danger"></i>
            </div>
            <h4 class="mb-2">Authentication</h4>
            <p class="text-muted mb-0">
              Built-in authentication features based on Laravel Breeze starter
              kit are integrated, including login, registration, password reset,
              and email verification features.
            </p>
            <!-- END Authentication -->
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- END Features -->
</template>

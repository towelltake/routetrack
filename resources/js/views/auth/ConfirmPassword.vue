<script setup>
import { useForm } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

const form = useForm({
  password: "",
});

const store = useTemplateStore();

const submit = () => {
  form.post("confirm-password", {
    onFinish: () => form.reset(),
  });
};
</script>

<template>
  <Head title="Confirm Password" />

  <!-- Page Content -->
  <BaseBackground image="/assets/media/photos/photo9@2x.jpg">
    <div class="row g-0 bg-primary-dark-op">
      <!-- Meta Info Section -->
      <div
        class="hero-static col-lg-4 d-none d-lg-flex flex-column justify-content-center"
      >
        <div class="p-4 p-xl-5 flex-grow-1 d-flex align-items-center">
          <div class="w-100">
            <Link href="/" class="link-fx fw-semibold fs-2 text-white">
              OneUI <span class="fw-normal">Vue</span>
            </Link>
            <p class="text-white-75 me-xl-8 mt-2">
              This is a secure area of the application. You will have to verify
              your password again.
            </p>
          </div>
        </div>
        <div
          class="p-4 p-xl-5 d-xl-flex justify-content-between align-items-center fs-sm"
        >
          <p class="fw-medium text-white-50 mb-0">
            <strong>{{ store.app.version }}</strong>
            &copy; {{ store.app.copyright }}
          </p>
          <ul class="list list-inline mb-0 py-2">
            <li class="list-inline-item">
              <a class="text-white-75 fw-medium" href="javascript:void(0)"
                >Legal</a
              >
            </li>
            <li class="list-inline-item">
              <a class="text-white-75 fw-medium" href="javascript:void(0)"
                >Contact</a
              >
            </li>
            <li class="list-inline-item">
              <a class="text-white-75 fw-medium" href="javascript:void(0)"
                >Terms</a
              >
            </li>
          </ul>
        </div>
      </div>
      <!-- END Meta Info Section -->

      <!-- Main Section -->
      <div
        class="hero-static col-lg-8 d-flex flex-column align-items-center bg-body-extra-light"
      >
        <div class="p-3 w-100 d-lg-none text-center">
          <Link href="/" class="link-fx fw-semibold fs-3 text-dark">
            OneUI <span class="fw-normal">Vue</span>
          </Link>
        </div>
        <div class="p-4 w-100 flex-grow-1 d-flex align-items-center">
          <div class="w-100">
            <!-- Header -->
            <div class="text-center mb-5">
              <h1 class="fw-black mb-2">Secure Area</h1>
              <p class="fw-medium text-muted">
                Please confirm your password before continuing.
              </p>
            </div>
            <!-- END Header -->

            <!-- Confirm Password Form -->
            <div class="row g-0 justify-content-center">
              <div class="col-sm-8 col-xl-4">
                <form @submit.prevent="submit">
                  <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input
                      type="password"
                      id="password"
                      class="form-control form-control-lg form-control-alt"
                      :class="{
                        'is-invalid': form.errors.password,
                      }"
                      v-model="form.password"
                      required
                      autocomplete="current-password"
                    />
                    <div v-show="form.errors.password" class="invalid-feedback">
                      {{ form.errors.password }}
                    </div>
                  </div>
                  <div class="mb-4">
                    <button
                      type="submit"
                      class="btn btn-alt-primary"
                      :class="{ 'opacity-25': form.processing }"
                      :disabled="form.processing"
                    >
                      Confirm password
                      <i class="fa fa-fw fa-arrow-right ms-1 opacity-50"></i>
                    </button>
                  </div>
                </form>
              </div>
            </div>
            <!-- END Confirm Password Form -->
          </div>
        </div>
        <div
          class="px-4 py-3 w-100 d-lg-none d-flex flex-column flex-sm-row justify-content-between fs-sm text-center text-sm-start"
        >
          <p class="fw-medium text-black-50 py-2 mb-0">
            <strong>{{ store.app.version }}</strong>
            &copy; {{ store.app.copyright }}
          </p>
          <ul class="list list-inline py-2 mb-0">
            <li class="list-inline-item">
              <a class="text-muted fw-medium" href="javascript:void(0)"
                >Legal</a
              >
            </li>
            <li class="list-inline-item">
              <a class="text-muted fw-medium" href="javascript:void(0)"
                >Contact</a
              >
            </li>
            <li class="list-inline-item">
              <a class="text-muted fw-medium" href="javascript:void(0)"
                >Terms</a
              >
            </li>
          </ul>
        </div>
      </div>
      <!-- END Main Section -->
    </div>
  </BaseBackground>
  <!-- END Page Content -->
</template>

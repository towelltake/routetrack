<script setup>
import { computed } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

const props = defineProps({
  email: {
    type: String,
    default: "",
  },
  token: {
    type: String,
    required: true,
  },
});

const store = useTemplateStore();
const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const form = useForm({
  token: props.token,
  email: props.email,
  password: "",
  password_confirmation: "",
});

const submit = () => {
  form.post("/reset-password", {
    onFinish: () => form.reset("password", "password_confirmation"),
  });
};
</script>

<template>
  <Head :title="t.reset_password_heading ?? 'Reset Password'" />

  <BaseBackground>
    <div class="row g-0 login-shell">
      <div
        class="hero-static col-lg-4 d-none d-lg-flex flex-column justify-content-center login-panel"
      >
        <div class="p-4 p-xl-5 flex-grow-1 d-flex align-items-center">
          <div class="w-100 login-panel-content">
            <div class="login-eyebrow text-uppercase">
              {{ t.field_execution_platform ?? "Field Execution Platform" }}
            </div>
            <Link href="/" class="link-fx fw-semibold login-brand text-white">
              TRAC
            </Link>
            <h2 class="login-panel-title text-white mt-4 mb-3">
              {{
                t.reset_password_panel_title ??
                "Set a new password and restore secure access to your TRAC workspace."
              }}
            </h2>
            <p class="login-panel-copy text-white-75 mb-4">
              {{
                t.reset_password_panel_copy ??
                "Choose a strong new password for the account linked to this reset request to continue working with route operations, collections, and reporting."
              }}
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
          <div class="text-white-50 py-2">
            {{
              t.authorized_access_internal_operations ??
              "Authorized access for internal operations teams"
            }}
          </div>
        </div>
      </div>

      <div
        class="hero-static col-lg-8 d-flex flex-column align-items-center bg-body-extra-light"
      >
        <div class="p-3 w-100 d-lg-none text-center">
          <Link href="/" class="link-fx fw-semibold fs-3 text-dark">TRAC</Link>
        </div>
        <div class="p-4 w-100 flex-grow-1 d-flex align-items-center">
          <div class="w-100">
            <div class="text-center mb-5">
              <h1 class="fw-black mb-2">
                {{ t.reset_password_heading ?? "Reset Password" }}
              </h1>
              <p class="fw-medium text-muted">
                {{
                  t.reset_password_intro ??
                  "Create a new password for your account and continue to the login screen."
                }}
              </p>
            </div>

            <div class="row g-0 justify-content-center">
              <div class="col-sm-8 col-xl-4">
                <form @submit.prevent="submit">
                  <div class="mb-4">
                    <label class="form-label" for="email">{{
                      t.email ?? "Email"
                    }}</label>
                    <input
                      id="email"
                      v-model="form.email"
                      type="email"
                      class="form-control form-control-lg form-control-alt"
                      :class="{
                        'is-invalid': form.errors.email,
                      }"
                      required
                      autocomplete="username"
                    />
                    <div v-show="form.errors.email" class="invalid-feedback">
                      {{ form.errors.email }}
                    </div>
                  </div>
                  <div class="mb-4">
                    <label class="form-label" for="password">{{
                      t.password ?? "Password"
                    }}</label>
                    <input
                      id="password"
                      v-model="form.password"
                      type="password"
                      class="form-control form-control-lg form-control-alt"
                      :class="{
                        'is-invalid': form.errors.password,
                      }"
                      required
                      autocomplete="new-password"
                    />
                    <div v-show="form.errors.password" class="invalid-feedback">
                      {{ form.errors.password }}
                    </div>
                  </div>
                  <div class="mb-4">
                    <label class="form-label" for="password_confirmation">{{
                      t.confirm_password ?? "Confirm Password"
                    }}</label>
                    <input
                      id="password_confirmation"
                      v-model="form.password_confirmation"
                      type="password"
                      class="form-control form-control-lg form-control-alt"
                      :class="{
                        'is-invalid': form.errors.password_confirmation,
                      }"
                      required
                      autocomplete="new-password"
                    />
                    <div
                      v-show="form.errors.password_confirmation"
                      class="invalid-feedback"
                    >
                      {{ form.errors.password_confirmation }}
                    </div>
                  </div>
                  <div class="mb-4">
                    <button
                      type="submit"
                      class="btn w-100 btn-alt-primary"
                      :class="{ 'opacity-25': form.processing }"
                      :disabled="form.processing"
                    >
                      {{ t.reset_password_heading ?? "Reset Password" }}
                      <i class="fa fa-fw fa-arrow-right ms-1 opacity-50"></i>
                    </button>
                  </div>
                  <div class="border-top py-3 text-center">
                    <Link
                      href="/login"
                      class="text-muted fs-sm fw-medium d-block d-lg-inline-block mb-1"
                    >
                      {{ t.back_to_login ?? "Back to login" }}
                    </Link>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div
          class="px-4 py-3 w-100 d-lg-none d-flex flex-column flex-sm-row justify-content-between fs-sm text-center text-sm-start"
        >
          <p class="fw-medium text-black-50 py-2 mb-0">
            <strong>{{ store.app.version }}</strong>
            &copy; {{ store.app.copyright }}
          </p>
        </div>
      </div>
    </div>
  </BaseBackground>
</template>

<style scoped>
.login-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(37, 99, 235, 0.1), transparent 26%),
    linear-gradient(90deg, #0f172a 0%, #13253f 26%, #f8fafc 26%, #f8fafc 100%);
}

.login-panel {
  position: relative;
  overflow: hidden;
  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.88), rgba(15, 23, 42, 0.94)),
    linear-gradient(135deg, #0b1220 0%, #163355 52%, #0f172a 100%);
}

.login-panel::before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
  background-size: 42px 42px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.45));
}

.login-panel::after {
  content: "";
  position: absolute;
  width: 420px;
  height: 420px;
  right: -140px;
  bottom: -100px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(14, 165, 233, 0.22), transparent 68%);
}

.login-panel-content {
  position: relative;
  z-index: 1;
  max-width: 30rem;
}

.login-eyebrow {
  letter-spacing: 0.18em;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgba(191, 219, 254, 0.92);
}

.login-brand {
  font-size: 2.35rem;
  letter-spacing: 0.04em;
}

.login-panel-title {
  font-size: 2rem;
  line-height: 1.15;
  font-weight: 800;
}

.login-panel-copy {
  font-size: 1rem;
  line-height: 1.75;
}

@media (max-width: 991.98px) {
  .login-shell {
    background: #f8fafc;
  }
}
</style>

<script setup>
import { computed, onBeforeUnmount, onMounted } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

defineProps({
  canResetPassword: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});

const store = useTemplateStore();
const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const form = useForm({
  username: "",
  password: "",
  remember: false,
});

const logoutRedirectKey = "trac_force_login_redirect";

function handleLoggedOutBackNavigation() {
  if (window.sessionStorage.getItem(logoutRedirectKey) !== "1") {
    return;
  }

  window.history.pushState(null, "", window.location.href);
}

onMounted(() => {
  handleLoggedOutBackNavigation();
  window.addEventListener("popstate", handleLoggedOutBackNavigation);
});

onBeforeUnmount(() => {
  window.removeEventListener("popstate", handleLoggedOutBackNavigation);
});

const submit = () => {
  form.post("/login", {
    onFinish: () => form.reset("password"),
  });
};
</script>

<template>
  <Head :title="t.log_in ?? 'Log In'" />

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
                t.login_panel_title ??
                "Control sales, distribution, inventory, and collections from one workspace."
              }}
            </h2>
            <p class="login-panel-copy text-white-75 mb-4">
              {{
                t.login_panel_copy ??
                "TRAC supports route operations with structured execution, transaction visibility, merchandizing follow-up, and reporting built for daily field use."
              }}
            </p>
            <div class="login-panel-points">
              <div class="login-point">
                <span class="login-point-marker"></span>
                <span>{{
                  t.login_point_route_execution ??
                  "Route execution and outlet coverage tracking"
                }}</span>
              </div>
              <div class="login-point">
                <span class="login-point-marker"></span>
                <span>{{
                  t.login_point_inventory_control ??
                  "Inventory, invoicing, settlement, and pending balance control"
                }}</span>
              </div>
              <div class="login-point">
                <span class="login-point-marker"></span>
                <span>{{
                  t.login_point_operational_reports ??
                  "Operational reports for field, accounts, and merchandising teams"
                }}</span>
              </div>
            </div>
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
          <Link href="/" class="link-fx fw-semibold fs-3 text-dark">
            TRAC
          </Link>
        </div>
        <div class="p-4 w-100 flex-grow-1 d-flex align-items-center">
          <div class="w-100">
            <div class="text-center mb-5">
              <h1 class="fw-black mb-2">{{ t.log_in ?? "Log In" }}</h1>
              <p class="fw-medium text-muted">
                {{ t.login_welcome_message ?? "Welcome, please log in." }}
              </p>
            </div>

            <div class="row g-0 justify-content-center">
              <div class="col-sm-8 col-xl-4">
                <div
                  v-if="status"
                  class="alert alert-success d-flex align-items-center justify-content-center fs-sm fw-medium mb-5"
                  role="alert"
                >
                  <i
                    class="fa fa-check-circle me-2 opacity-50 flex-shrink-0"
                  ></i>
                  <span>{{ status }}</span>
                </div>

                <form @submit.prevent="submit">
                  <div class="mb-4">
                    <label class="form-label" for="username">{{
                      t.username ?? "Username"
                    }}</label>
                    <input
                      id="username"
                      v-model="form.username"
                      type="text"
                      class="form-control form-control-lg form-control-alt"
                      :class="{
                        'is-invalid': form.errors.username,
                      }"
                      required
                      autofocus
                      autocomplete="username"
                    />
                    <div v-show="form.errors.username" class="invalid-feedback">
                      {{ form.errors.username }}
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
                      autocomplete="current-password"
                    />
                    <div v-show="form.errors.password" class="invalid-feedback">
                      {{ form.errors.password }}
                    </div>
                  </div>
                  <div
                    class="d-flex justify-content-between align-items-center mb-4"
                  >
                    <div class="form-check">
                      <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="form-check-input"
                      />
                      <label class="form-check-label" for="remember">
                        {{ t.remember_me ?? "Remember me" }}
                      </label>
                    </div>
                    <div>
                      <button
                        type="submit"
                        class="btn btn-alt-primary"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                      >
                        <i class="fa fa-fw fa-sign-in-alt me-1 opacity-50"></i>
                        {{ t.log_in ?? "Log In" }}
                      </button>
                    </div>
                  </div>
                  <div class="border-top py-3 text-center">
                    <Link
                      v-if="canResetPassword"
                      href="/forgot-password"
                      class="text-muted fs-sm fw-medium d-block d-lg-inline-block mb-1"
                    >
                      {{ t.forgot_password ?? "Forgot password?" }}
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

.login-panel-points {
  display: grid;
  gap: 0.9rem;
}

.login-point {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  color: rgba(226, 232, 240, 0.92);
  line-height: 1.5;
}

.login-point-marker {
  width: 10px;
  height: 10px;
  margin-top: 0.45rem;
  flex: 0 0 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, #38bdf8 0%, #60a5fa 100%);
  box-shadow: 0 0 0 6px rgba(56, 189, 248, 0.12);
}

@media (max-width: 991.98px) {
  .login-shell {
    background: #f8fafc;
  }
}
</style>

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

const companyLogos = [
  { file: "1 MCS-logo-1.png", name: "Matrah Cold Stores LLC" },
  { file: "2 Fairtrade-logo.png", name: "Fairtrade LLC" },
  { file: "3 CSTC-logo-1.jpg", name: "Cold Storage & Trading Company LLC" },
  { file: "3 MDC-logo-1.png", name: "Majan Distribution Co. LLC" },
].sort((a, b) => a.file.localeCompare(b.file, "en", { numeric: true }));

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

  <BaseBackground class="bg-white">
    <div class="row g-0 login-shell">
      <div class="hero-static col-lg-4 d-none d-lg-flex flex-column align-items-center login-panel">
        <img
          src="/assets/enhance.png"
          alt="Enhance Group retail and distribution"
          class="login-panel-image"
        />
        <footer class="login-company-footer" aria-label="Group companies">
          <img
            v-for="company in companyLogos"
            :key="company.file"
            :src="`/assets/Companies/${encodeURIComponent(company.file)}`"
            :alt="company.name"
            class="login-company-logo"
          />
        </footer>
      </div>

      <div
        class="hero-static col-lg-8 d-flex flex-column align-items-center bg-white login-form-panel"
      >
        <img
          src="/assets/TOWELL%20LOGO.png"
          alt=""
          aria-hidden="true"
          class="login-towell-watermark"
        />
        <div class="p-3 w-100 d-lg-none text-center">
          <Link href="/" class="link-fx fw-semibold fs-3 text-dark">
            TRAC
          </Link>
        </div>
        <div class="p-4 w-100 flex-grow-1 d-flex align-items-center">
          <div class="w-100">
            <div class="text-center mb-5">
              <img
                src="/assets/eg.png"
                alt="Enhance Group"
                class="login-logo mb-3"
              />
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
  background: #fff;
}

.login-logo {
  display: block;
  width: 180px;
  max-width: 100%;
  height: auto;
  margin-inline: auto;
}

.login-form-panel {
  position: relative;
  isolation: isolate;
  overflow: hidden;
}

.login-towell-watermark {
  position: absolute;
  z-index: -1;
  top: 50%;
  right: -8rem;
  width: clamp(18rem, 26vw, 32rem);
  height: min(80vh, 44rem);
  object-fit: contain;
  transform: translateY(-50%) rotate(-22deg);
  opacity: 0.08;
  pointer-events: none;
}

@media (max-width: 991.98px) {
  .login-towell-watermark {
    right: -12rem;
    opacity: 0.04;
  }
}

.login-panel {
  padding: 2rem;
  gap: 1.5rem;
  background: #fff;
}

.login-panel-image {
  display: block;
  width: 100%;
  height: auto;
  max-height: calc(100vh - 10rem);
  margin-block: auto;
  object-fit: contain;
}

.login-company-footer {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  flex-shrink: 0;
}

.login-company-logo {
  display: block;
  width: 100%;
  height: 64px;
  object-fit: contain;
}
</style>

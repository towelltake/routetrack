<script setup>
import { computed } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

defineProps({
  mustVerifyEmail: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});

const page = usePage();
const user = page.props.auth.user;
const t = computed(() => page.props.translations?.ui ?? {});

const form = useForm({
  name: user.name,
  email: user.email,
});
</script>

<template>
  <form @submit.prevent="form.patch('/profile', { preserveScroll: true })">
    <div
      v-if="
        mustVerifyEmail &&
        user.email_verified_at === null &&
        status === 'verification-link-sent'
      "
      class="alert alert-success d-flex align-items-center justify-content-center fs-sm fw-medium mb-4"
      role="alert"
    >
      <i class="fa fa-check-circle me-2 opacity-50 flex-shrink-0"></i>
      <span>
        {{ t.verification_link_sent ?? "A new verification link has been sent to your email address." }}
      </span>
    </div>
    <div class="mb-4">
      <label class="form-label" for="name">{{ t.name ?? "Name" }}</label>
      <input
        id="name"
        v-model="form.name"
        type="text"
        class="form-control form-control-lg form-control-alt"
        :class="{
          'is-invalid': form.errors.name,
        }"
        required
        autocomplete="name"
      />
      <div v-show="form.errors.email" class="invalid-feedback">
        {{ form.errors.name }}
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label" for="email">{{ t.email ?? "Email" }}</label>
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
      <div
        v-if="mustVerifyEmail && user.email_verified_at === null"
        class="fs-sm text-muted mt-2"
      >
        {{ t.email_unverified ?? "Your email address is unverified!" }}
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <Link
        v-if="mustVerifyEmail && user.email_verified_at === null"
        href="/email/verification-notification"
        method="post"
        as="button"
        class="btn btn-alt-primary"
      >
        {{ t.resend_verification_email ?? "Re-send the verification email" }}
      </Link>
      <button type="submit" class="btn btn-primary" :disabled="form.processing">
        {{ t.save ?? "Save" }}
      </button>
      <div v-if="form.recentlySuccessful" class="fs-sm text-muted">{{ t.saved_success ?? "Saved!" }}</div>
    </div>
  </form>
</template>

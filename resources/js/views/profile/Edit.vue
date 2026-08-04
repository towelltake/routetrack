<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";

defineProps({
  mustVerifyEmail: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});
</script>

<template>
  <Head :title="t.profile ?? 'Profile'" />

  <!-- Hero -->
  <BaseBackground image="/assets/media/photos/photo33@2x.jpg">
    <div class="bg-primary-dark-op">
      <div class="content content-full text-center">
        <div class="my-3">
          <img
            class="img-avatar img-avatar-thumb"
            :src="$page.props.auth.user.gravatar"
            alt="User Avatar"
          />
        </div>
        <h1 class="h2 text-white mb-0">{{ $page.props.auth.user.name }}</h1>
        <h2 class="h4 fw-normal text-white-75">{{ t.edit_your_profile ?? "Edit your profile" }}</h2>
      </div>
    </div>
  </BaseBackground>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content content-boxed">
    <BaseBlock>
      <div class="row g-5">
        <div class="col-xl-6">
          <div class="profile-panel h-100 pe-xl-4">
            <h3 class="fs-4 mb-2">{{ t.profile_information ?? "Profile Information" }}</h3>
            <p class="fs-sm text-muted mb-4">
              {{ t.profile_information_note ?? "Your account's vital info. Your username will be publicly visible." }}
            </p>
            <UpdateProfileInformationForm
              :must-verify-email="mustVerifyEmail"
              :status="status"
            />
          </div>
        </div>

        <div class="col-xl-6">
          <div class="h-100 ps-xl-4">
            <h3 class="fs-4 mb-2">{{ t.update_password ?? "Update Password" }}</h3>
            <p class="fs-sm text-muted mb-4">
              {{ t.update_password_note ?? "Ensure your account is using a long, random password to stay secure." }}
            </p>
            <UpdatePasswordForm />
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
  <!-- END Page Content -->
</template>

<style scoped>
@media (min-width: 1200px) {
  .profile-panel {
    border-inline-end: 1px solid rgba(148, 163, 184, 0.25);
  }
}
</style>

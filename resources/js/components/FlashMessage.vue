<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { translateAlertMessage } from "@/utils/translateAlertMessage";

const page = usePage();

const flash = computed(() => page.props.flash || {});
const t = computed(() => page.props.translations?.ui ?? {});
const locale = computed(() => page.props.locale || "en");
const positionClass = computed(() =>
  locale.value === "ar" ? "top-0 start-0 ps-3" : "top-0 end-0 pe-3",
);
const successMessage = computed(() =>
  translateAlertMessage(flash.value.success, t.value, locale.value),
);
const errorMessage = computed(() =>
  translateAlertMessage(flash.value.error, t.value, locale.value),
);

const flashKey = computed(
  () =>
    `${locale.value}-${flash.value.id ?? ""}-${flash.value.success ?? ""}-${flash.value.error ?? ""}`,
);
</script>

<template>
  <div
    :key="flashKey"
    class="position-fixed p-3"
    :class="positionClass"
    style="z-index: 2000; max-width: 360px"
  >
    <div
      v-if="successMessage"
      class="alert alert-success alert-dismissible fade show shadow-sm"
    >
      <i class="fa fa-check-circle me-1"></i> {{ successMessage }}
      <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <div
      v-if="errorMessage"
      class="alert alert-danger alert-dismissible fade show shadow-sm"
    >
      <i class="fa fa-exclamation-circle me-1"></i> {{ errorMessage }}
      <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
</template>

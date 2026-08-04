<script setup>
import { useMastersStore } from "@/stores/masters";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { translateAlertMessage } from "@/utils/translateAlertMessage";

const page = usePage();
const t = computed(() => page.props.translations.ui);

const locale = page.props.locale || "en";
const isRtl = locale === "ar";
const translatedMessage = computed(() =>
  translateAlertMessage(store.alert.message, t.value, locale),
);

const store = useMastersStore();

function confirm() {
  store.alert.action();
  store.alert.show = false;
}

function cancel() {
  store.alert.show = false;
}
</script>

<template>
  <div
    v-if="store.alert.show"
    class="modal fade show d-block bg-dark bg-opacity-50"
    tabindex="-1"
    role="dialog"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <BaseBlock :title="t.confirmation" transparent class="mb-0">
          <!-- Close button -->
          <template #options>
            <button type="button" class="btn-block-option" @click="cancel">
              <i class="fa fa-fw fa-times"></i>
            </button>
          </template>

          <!-- Content -->
          <template #content>
            <div class="block-content fs-sm">
              <p>{{ translatedMessage }}</p>
            </div>

            <!-- Footer buttons -->
            <div
              class="block-content block-content-full bg-body"
              :class="isRtl ? 'text-start' : 'text-end'"
            >
              <button
                type="button"
                class="btn btn-sm btn-danger me-1"
                @click="confirm"
              >
                {{ t.yes }}
              </button>

              <button
                type="button"
                class="btn btn-sm btn-alt-secondary"
                @click="cancel"
              >
                {{ t.cancel }}
              </button>
            </div>
          </template>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

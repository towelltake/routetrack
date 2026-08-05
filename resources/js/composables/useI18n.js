import { computed, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useTemplateStore } from "@/stores/template";

export function useI18n() {
  const page = usePage();
  const store = useTemplateStore();
  const locale = computed(() => page.props.locale || "en");
  const isRtl = computed(() => locale.value === "ar");

  watch(
    locale,
    (lang) => {
      const rtl = lang === "ar";

      store.settings.rtlSupport = rtl;
      store.settings.sidebarLeft = !rtl;
    },
    { immediate: true },
  );

  return { isRtl };
}

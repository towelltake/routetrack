import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

function normalizePrecision(value) {
  const digits = Number.parseInt(value, 10);

  if (Number.isNaN(digits)) {
    return 3;
  }

  return Math.min(6, Math.max(0, digits));
}

export function useAmountFormatter() {
  const page = usePage();

  const amountDecimalPlaces = computed(() =>
    normalizePrecision(page.props.settings?.amountDecimalPlaces),
  );

  function formatAmount(value, digits = amountDecimalPlaces.value) {
    return Number(value ?? 0).toFixed(normalizePrecision(digits));
  }

  function roundAmount(value, digits = amountDecimalPlaces.value) {
    return Number(formatAmount(value, digits));
  }

  return {
    amountDecimalPlaces,
    formatAmount,
    roundAmount,
  };
}

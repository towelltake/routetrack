import { useMastersStore } from "@/stores/masters";

export function useAlert() {
  const store = useMastersStore();

  function confirm(message, action) {
    store.alert = {
      show: true,
      message,
      action,
    };
  }

  return { confirm };
}

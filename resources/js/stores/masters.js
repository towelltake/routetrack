import { defineStore } from "pinia";

export const useMastersStore = defineStore("masters", {
  state: () => ({
    currencies: [],
    banks: [],
    companies: [],
    channels: [],
    categories: [],
    alert: {
      show: false,
      message: "",
      action: null,
    },
  }),

  actions: {
    setCurrencies(data) {
      this.currencies = data;
    },
    setBanks(data) {
      this.banks = data;
    },
    setCompanies(data) {
      this.companies = data;
    },
  },
});

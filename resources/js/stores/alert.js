import { defineStore } from "pinia";

export const useAlertStore = defineStore("alert", {
  state: () => ({
    alert: {
      show: false,
      message: "",
      action: null,
    },
  }),

  // actions: {
  //   confirm(message, callback) {
  //     this.type = "confirm";
  //     this.message = message;
  //     this.callback = callback;
  //     this.show = true;
  //   },
  //   success(message) {
  //     this.type = "success";
  //     this.message = message;
  //     this.show = true;
  //   },
  //   close() {
  //     this.show = false;
  //     this.callback = null;
  //   },
  // },
});

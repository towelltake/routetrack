<script setup>
import { computed, reactive } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import VueSelect from "vue-select";

const props = defineProps({
  optionSets: { type: Object, required: true },
  defaults: { type: Object, required: true },
});
const page = usePage();
const t = page.props.translations.ui;

const form = reactive({
  routecode: null,
  week: props.defaults.week ?? 1,
});

const routeValue = computed({
  get: () => findOption(props.optionSets.routeOptions, form.routecode),
  set: (option) => {
    form.routecode = option ? option.id : null;
  },
});

function openPage(type) {
  if (type === "sales-calendar") {
    router.get("/account/customer-sequence/sales-calendar", { year: new Date().getFullYear() });
    return;
  }

  if (!form.routecode) {
    return;
  }

  const params = { routecode: form.routecode, week: form.week };

  if (type === "arrange") {
    router.get("/account/customer-sequence/arrange", params);
    return;
  }

  if (type === "route-sequence") {
    router.get("/account/customer-sequence/route-sequence", { ...params, day: 1 });
    return;
  }

  if (type === "copy-sequence") {
    router.get("/account/customer-sequence/copy-sequence", { routecode: form.routecode });
  }
}

function findOption(options, value) {
  if (!value) {
    return null;
  }

  return options.find((option) => String(option.id) === String(value)) ?? null;
}
</script>

<template>
  <Head :title="t.customer_sequence" />

  <BasePageHeading
    :title="t.customer_sequence"
    :subtitle="t.customer_sequence_note"
  />

  <div class="content">
    <BaseBlock>
      <div class="row g-4 align-items-end">
        <div class="col-md-5">
          <label class="form-label">{{ t.route }} <span class="text-danger">*</span></label>
          <VueSelect
            v-model="routeValue"
            :options="optionSets.routeOptions"
            label="label"
            :placeholder="t.select_route"
          />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.week }}</label>
          <select v-model="form.week" class="form-select">
            <option v-for="option in optionSets.weekOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="row g-4 mt-3 mb-3">
        <div class="col-md-3">
          <button class="btn btn-alt-primary w-100 py-3" @click="openPage('sales-calendar')">
            <i class="fa fa-calendar me-2"></i> {{ t.sales_calendar }}
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-alt-primary w-100 py-3" :disabled="!form.routecode" @click="openPage('arrange')">
            <i class="fa fa-users-gear me-2"></i> {{ t.arrange_customer }}
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-alt-primary w-100 py-3" :disabled="!form.routecode" @click="openPage('route-sequence')">
            <i class="fa fa-sort me-2"></i> {{ t.route_sequence }}
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-alt-primary w-100 py-3" :disabled="!form.routecode" @click="openPage('copy-sequence')">
            <i class="fa fa-copy me-2"></i> {{ t.copy_sequence }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>

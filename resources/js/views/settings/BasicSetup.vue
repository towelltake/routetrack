<script setup>
import { computed } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  form: { type: Object, required: true },
  routeSequenceOptions: { type: Array, required: true },
  journeyPlanOptions: { type: Array, required: true },
  transferInventoryOptions: { type: Array, required: true },
  tabletSyncModeOptions: { type: Array, required: true },
  salesCalendarCount: { type: Number, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const optionLabelKeys = {
  "Generic Week": "generic_week",
  "Sales Week": "sales_week",
  "Only One Day": "only_one_day",
  "All Days": "all_days",
  Disable: "disable",
  "Only On Routes": "only_on_routes",
  "Only On Depots": "only_on_depots",
  "On Routes/Depot": "on_routes_depot",
  Online: "online",
  Batch: "batch",
  Manual: "manual",
};

function translateOptionLabel(label) {
  const key = optionLabelKeys[label];

  return key ? (t.value[key] ?? label) : label;
}

const form = useForm({
  setupid: props.form.setupid ?? 1,
  routesequenceplanflag: props.form.routesequenceplanflag ?? 1,
  previousdayuploadflag: Boolean(props.form.previousdayuploadflag),
  journeyplanflag: props.form.journeyplanflag ?? 1,
  allowpreparefilesafterupload: Boolean(props.form.allowpreparefilesafterupload),
  transferinventoryflag: props.form.transferinventoryflag ?? 0,
  restrictpreparefile: Boolean(props.form.restrictpreparefile),
  tabletsyncmode: props.form.tabletsyncmode ?? "",
  allowmorethanonesalesman: Boolean(props.form.allowmorethanonesalesman),
  importfilepath: props.form.importfilepath ?? "",
  synctimeinterval: props.form.synctimeinterval ?? "",
  decimalplaces: props.form.decimalplaces ?? 3,
});

const showSyncInterval = computed(() => String(form.tabletsyncmode) === "2");

function submit() {
  form.transform((data) => ({
    ...data,
    previousdayuploadflag: data.previousdayuploadflag ? 1 : 0,
    allowpreparefilesafterupload: data.allowpreparefilesafterupload ? 1 : 0,
    restrictpreparefile: data.restrictpreparefile ? 1 : 0,
    allowmorethanonesalesman: data.allowmorethanonesalesman ? 1 : 0,
    tabletsyncmode: data.tabletsyncmode || null,
    synctimeinterval: String(data.tabletsyncmode) === "2" && data.synctimeinterval !== ""
      ? data.synctimeinterval
      : null,
    decimalplaces: Number(data.decimalplaces ?? 3),
  })).put("/settings/basic-setup", {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="t.basic_setup ?? 'Basic Setup'" />

  <BasePageHeading
    :title="t.basic_setup ?? 'Basic Setup'"
    :subtitle="t.basic_setup_subtitle ?? 'Manage route sequencing, sync behavior, transfer options, and MIS report settings.'"
  >
    <template #extra>
      <button
        class="btn btn-primary"
        :disabled="form.processing"
        @click="submit"
      >
        <i class="fa fa-save me-1"></i> {{ t.save ?? "Save" }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.administration_setup ?? 'Administration Setup'">
      <div class="row g-4 mb-3">
        <div class="col-lg-6">
          <label class="form-label">{{ t.route_sequence ?? "Route Sequence" }}</label>
          <select
            v-model="form.routesequenceplanflag"
            class="form-select"
            :class="{ 'is-invalid': form.errors.routesequenceplanflag }"
          >
            <option v-for="option in routeSequenceOptions" :key="option.id" :value="option.id">
              {{ translateOptionLabel(option.label) }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.routesequenceplanflag }}</div>
        </div>

        <div class="col-lg-6 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input id="previousdayuploadflag" v-model="form.previousdayuploadflag" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="previousdayuploadflag">
              {{ t.allow_previous_day_upload_sync ?? "Allow Previous Day Upload Sync" }}
            </label>
          </div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.route_journey_plan ?? "Route Journey Plan" }}</label>
          <select
            v-model="form.journeyplanflag"
            class="form-select"
            :class="{ 'is-invalid': form.errors.journeyplanflag }"
          >
            <option v-for="option in journeyPlanOptions" :key="option.id" :value="option.id">
              {{ translateOptionLabel(option.label) }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.journeyplanflag }}</div>
        </div>

        <div class="col-lg-6 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input id="allowpreparefilesafterupload" v-model="form.allowpreparefilesafterupload" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="allowpreparefilesafterupload">
              {{ t.allow_import_sync_after_upload_sync ?? "Allow Import Sync After Upload Sync" }}
            </label>
          </div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.inventory_load_transfers ?? "Inventory Load Transfers" }}</label>
          <select
            v-model="form.transferinventoryflag"
            class="form-select"
            :class="{ 'is-invalid': form.errors.transferinventoryflag }"
          >
            <option v-for="option in transferInventoryOptions" :key="option.id" :value="option.id">
              {{ translateOptionLabel(option.label) }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.transferinventoryflag }}</div>
        </div>

        <div class="col-lg-6 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input id="restrictpreparefile" v-model="form.restrictpreparefile" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="restrictpreparefile">
              {{ t.restrict_multiple_import_sync ?? "Restrict Multiple Import Sync" }}
            </label>
          </div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.tablet_transaction_sync_mode ?? "Tablet Transaction Sync Mode" }}</label>
          <select
            v-model="form.tabletsyncmode"
            class="form-select"
            :class="{ 'is-invalid': form.errors.tabletsyncmode }"
          >
            <option value="">{{ t.select_placeholder ?? "--- Select ---" }}</option>
            <option v-for="option in tabletSyncModeOptions" :key="option.id" :value="option.id">
              {{ translateOptionLabel(option.label) }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.tabletsyncmode }}</div>
        </div>

        <div class="col-lg-6 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input id="allowmorethanonesalesman" v-model="form.allowmorethanonesalesman" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="allowmorethanonesalesman">
              {{ t.allow_multiple_salesman_to_visit_customer ?? "Allow Multiple Salesman To Visit A Customer" }}
            </label>
          </div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.mis_report ?? "MIS Report" }}</label>
          <input
            v-model="form.importfilepath"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': form.errors.importfilepath }"
          >
          <div class="invalid-feedback">{{ form.errors.importfilepath }}</div>
        </div>

        <div v-if="showSyncInterval" class="col-lg-6">
          <label class="form-label">{{ t.tablet_transaction_sync_time_interval ?? "Tablet Transaction Sync Time Interval" }}</label>
          <input
            v-model="form.synctimeinterval"
            type="number"
            min="1"
            class="form-control"
            :class="{ 'is-invalid': form.errors.synctimeinterval }"
          >
          <div class="invalid-feedback">{{ form.errors.synctimeinterval }}</div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.amount_decimal_places ?? "Amount Decimal Places" }}</label>
          <input
            v-model="form.decimalplaces"
            type="number"
            min="0"
            max="6"
            class="form-control"
            :class="{ 'is-invalid': form.errors.decimalplaces }"
          >
          <div class="form-text">{{ t.amount_decimal_places_note ?? "Controls how many digits appear after the decimal point for prices and amounts." }}</div>
          <div class="invalid-feedback">{{ form.errors.decimalplaces }}</div>
        </div>
      </div>
    </BaseBlock>

    
  </div>
</template>

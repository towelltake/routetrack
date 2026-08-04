<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import VueSelect from "vue-select";

const props = defineProps({
  mode: {
    type: String,
    required: true,
  },
  routeData: {
    type: Object,
    required: true,
  },
  lookupOptions: {
    type: Object,
    required: true,
  },
  optionSets: {
    type: Object,
    required: true,
  },
  selectedTemplateId: {
    type: [Number, String],
    default: null,
  },
});

const activeTab = ref("general");
const selectedTemplate = ref(props.selectedTemplateId ?? "");
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;

const form = useForm({
  ...props.routeData,
});

const deviceAssignedOption = computed({
  get: () =>
    props.lookupOptions.deviceOptions.find(
      (option) => String(option.id) === String(form.device_assigned_id ?? ""),
    ) ?? null,
  set: (option) => {
    form.device_assigned_id = option ? option.id : null;
  },
});

const selectedWorkingDays = computed({
  get: () =>
    String(form.autojp_working_days || "")
      .split(",")
      .map((value) => value.trim())
      .filter(Boolean),
  set: (values) => {
    form.autojp_working_days = values.join(",");
  },
});

const tabs = [
  { key: "general", label: t.general },
  { key: "settings1", label: t.settings_1 },
  { key: "settings2", label: t.settings_2 },
  { key: "reports", label: t.reports },
];

const passwordFields = [
  { key: "password1", label: t.password_1 },
  { key: "password2", label: t.password_2 },
  { key: "password3", label: t.password_3 },
  { key: "password4", label: t.password_4 },
  { key: "password5", label: t.password_5 },
];

const securityFields = [
  { key: "passwordarray16", label: t.view_van_stock },
  { key: "passwordarray11", label: t.unload },
  { key: "passwordarray07", label: t.start_day },
  { key: "passwordarray02", label: t.price_change },
  { key: "passwordarray15", label: t.load_request },
  { key: "passwordarray03", label: t.promo_override },
  { key: "passwordarray12", label: t.load_out },
  { key: "passwordarray09", label: t.settlement },
  { key: "passwordarray06", label: t.load_adjust },
  { key: "passwordarray13", label: t.print_document },
  { key: "passwordarray14", label: t.load_transfer },
];

const settings1ToggleFields = [
  ["enableeodaddchecks", t.enable_eod_add_checks],
  ["enableeodexpenses", t.enable_eod_expenses],
  ["enabledelayprint", t.enable_delay_print_no_customer_no],
  ["enableaddcustomer", t.enable_add_edit_customer],
  ["enforcecallsequence", t.enforce_journey_plan],
  ["enablescancustomer", t.enable_customer_scan],
  ["loadoutadjustments", t.enable_load_out_adjustment],
  ["enablepostvoid", t.enable_post_void],
  ["transactionnoseq", t.separate_invoice_sequence_sales_return],
  ["enabledraftcopy", t.enable_draft_copy],
  ["enablefreereason", t.enable_free_reason],
  ["voidoverride", t.use_password_for_void],
];

const settings2ToggleFields = [
  ["enablemiddaytelecom", t.allow_voiding_of_collections],
  ["enableautopostingaccount", t.post_variance_against_salesman],
  ["allowroutestartdayflag", t.allow_route_start_day_in_cloud],
];

const reports = [
  { key: "reqeoddepositreport", label: t.deposit_report },
  { key: "reqeodsalesreport", label: t.sales_report },
  { key: "reqeodrteactivreport", label: t.route_activity_report },
  { key: "reqeodrtestlmtreport", label: t.settlement_report },
  { key: "reqeodroutereviewrpt", label: t.route_review_report },
  { key: "reqeodrtnexchreport", label: t.return_exchange_report },
  { key: "reqeodplacementsrpt", label: t.placements_report },
  { key: "reqeodprcchgreport", label: t.price_change_report },
  { key: "reqeodpromosreport", label: t.promotions_report },
  { key: "reqeodnosalereport", label: t.no_sale_report },
  { key: "reqeodnondelreport", label: t.non_delivery_report },
  { key: "reqeodexceptionrpt", label: t.exception_report },
  { key: "reqeodunauthbalance", label: t.unauthorized_balance_report },
  { key: "reqeodroasummary", label: t.roa_summary },
  { key: "reqeodnonscannedreport", label: t.non_scanned_report },
  { key: "reqeododomlogreport", label: t.odometer_log_report },
];

const workingDayOptions = [
  { id: "1", label: t.mon_short },
  { id: "2", label: t.tue_short },
  { id: "3", label: t.wed_short },
  { id: "4", label: t.thu_short },
  { id: "5", label: t.fri_short },
  { id: "6", label: t.sat_short },
  { id: "7", label: t.sun_short },
];

function toggleWorkingDay(dayId, checked) {
  const next = [...selectedWorkingDays.value];

  if (checked) {
    if (!next.includes(dayId)) {
      next.push(dayId);
    }
  } else {
    const index = next.indexOf(dayId);
    if (index >= 0) {
      next.splice(index, 1);
    }
  }

  selectedWorkingDays.value = next.sort();
}

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/organisation/route");
    return;
  }

  form.put(`/organisation/route/${form.routecode}`);
}

function applyTemplate() {
  router.get(
    "/organisation/route/create",
    { template_id: selectedTemplate.value || undefined },
    { preserveScroll: true, preserveState: false, replace: true },
  );
}

const pageTitle = computed(() => {
  if (isCreate.value) return t.create_route;
  if (isView.value) return t.view_route;
  return t.edit_route;
});
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.route_form_subtitle"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/organisation/route')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/organisation/route/${form.routecode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button v-else class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i>
          {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="block-content block-content-full">
        <div
          v-if="Object.keys(form.errors).length"
          class="alert alert-danger d-flex flex-column gap-1"
          role="alert"
        >
          <div class="fw-semibold">{{ t.route_save_failed }}</div>
          <div v-for="(message, field) in form.errors" :key="field">
            {{ message }}
          </div>
        </div>

        <div v-if="isCreate" class="row g-3 mb-4">
          <div class="col-md-6 col-xl-4">
            <label class="form-label">{{ t.route_template_setting }}</label>
            <div class="input-group">
              <select v-model="selectedTemplate" class="form-select">
                <option value="">{{ t.no_template }}</option>
                <option
                  v-for="option in lookupOptions.routeTemplates"
                  :key="option.id"
                  :value="option.id"
                >
                  {{ option.label }}
                </option>
              </select>
              <button class="btn btn-alt-primary" type="button" @click="applyTemplate">
                {{ t.apply }}
              </button>
            </div>
          </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-block" role="tablist">
          <li v-for="tab in tabs" :key="tab.key" class="nav-item">
            <button
              class="nav-link"
              :class="{ active: activeTab === tab.key }"
              type="button"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </li>
        </ul>

        <div class="tab-content block-content px-0 pb-0">
          <div v-show="activeTab === 'general'" class="tab-pane active">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">{{ t.route_code }}</label>
                  <input v-model="form.routecode" class="form-control" readonly />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.company }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.cmpycode"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option :value="null">{{ t.select_company }}</option>
                    <option v-for="option in lookupOptions.companies" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.cmpycode" class="text-danger fs-sm mt-1">{{ form.errors.cmpycode }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.alternate_code }}</label>
                  <input v-model="form.alternateroutecode" class="form-control" :readonly="isView" />
                  <div v-if="form.errors.alternateroutecode" class="text-danger fs-sm mt-1">{{ form.errors.alternateroutecode }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.status }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.activestatus"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option v-for="option in optionSets.statusOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.activestatus" class="text-danger fs-sm mt-1">{{ form.errors.activestatus }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.auto_jp }}</label>
                  <select v-model="form.autojp_enabled" class="form-select" :disabled="isView">
                    <option :value="0">{{ t.disable }}</option>
                    <option :value="1">{{ t.enable }}</option>
                  </select>
                  <div v-if="form.errors.autojp_enabled" class="text-danger fs-sm mt-1">{{ form.errors.autojp_enabled }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.auto_jp_start_time }}</label>
                  <input v-model="form.autojp_work_start_time" type="time" class="form-control" :readonly="isView" :disabled="isView || !Number(form.autojp_enabled)" />
                  <div v-if="form.errors.autojp_work_start_time" class="text-danger fs-sm mt-1">{{ form.errors.autojp_work_start_time }}</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.auto_jp_end_time }}</label>
                  <input v-model="form.autojp_work_end_time" type="time" class="form-control" :readonly="isView" :disabled="isView || !Number(form.autojp_enabled)" />
                  <div v-if="form.errors.autojp_work_end_time" class="text-danger fs-sm mt-1">{{ form.errors.autojp_work_end_time }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label">{{ t.auto_jp_working_days }}</label>
                  <div class="d-flex flex-wrap gap-3">
                    <label
                      v-for="option in workingDayOptions"
                      :key="option.id"
                      class="form-check form-check-inline"
                    >
                      <input
                        :value="option.id"
                        :disabled="isView || !Number(form.autojp_enabled)"
                        :checked="selectedWorkingDays.includes(option.id)"
                        class="form-check-input"
                        type="checkbox"
                        @change="toggleWorkingDay(option.id, $event.target.checked)"
                      >
                      <span class="form-check-label">{{ option.label }}</span>
                    </label>
                  </div>
                  <div v-if="form.errors.autojp_working_days" class="text-danger fs-sm mt-1">{{ form.errors.autojp_working_days }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.route_name }} <span class="text-danger">*</span></label>
                  <input
                    v-model="form.routename"
                    class="form-control"
                    :readonly="isView"
                    :required="!isView"
                  />
                  <div v-if="form.errors.routename" class="text-danger fs-sm mt-1">{{ form.errors.routename }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.arab_route_name }}</label>
                  <input v-model="form.arbroutename" class="form-control" :readonly="isView" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.region }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.regionmstcode"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option :value="null">{{ t.select_region }}</option>
                    <option v-for="option in lookupOptions.regions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.regionmstcode" class="text-danger fs-sm mt-1">{{ form.errors.regionmstcode }}</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.sub_area }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.subareacode"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option :value="null">{{ t.select_sub_area }}</option>
                    <option v-for="option in lookupOptions.subAreas" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.subareacode" class="text-danger fs-sm mt-1">{{ form.errors.subareacode }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.salesman }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.salesmancode"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option :value="null">{{ t.select_salesman }}</option>
                    <option v-for="option in lookupOptions.salesmen" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.salesmancode" class="text-danger fs-sm mt-1">{{ form.errors.salesmancode }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.van }}</label>
                  <select v-model="form.vehiclenumber" class="form-select" :disabled="isView">
                    <option :value="null">{{ t.select_van }}</option>
                    <option v-for="option in lookupOptions.vans" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.vehiclenumber" class="text-danger fs-sm mt-1">
                    {{ form.errors.vehiclenumber }}
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.route_category }}</label>
                  <select v-model="form.routecatcode" class="form-select" :disabled="isView">
                    <option :value="null">{{ t.select_route_category }}</option>
                    <option
                      v-for="option in lookupOptions.routeCategories"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.routecatcode" class="text-danger fs-sm mt-1">
                    {{ form.errors.routecatcode }}
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.route_type }} <span class="text-danger">*</span></label>
                  <select
                    v-model="form.routetype"
                    class="form-select"
                    :disabled="isView"
                    :required="!isView"
                  >
                    <option v-for="option in optionSets.routeTypes" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.device_assigned }}</label>
                  <VueSelect
                    v-model="deviceAssignedOption"
                    :options="lookupOptions.deviceOptions"
                    :disabled="isView"
                    :clearable="true"
                    label="label"
                    :placeholder="t.select_device"
                  />
                  <div v-if="form.errors.device_assigned_id" class="text-danger fs-sm mt-1">
                    {{ form.errors.device_assigned_id }}
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">{{ t.route_item_group }}</label>
                  <select v-model="form.routeitemgrpcode" class="form-select" :disabled="isView">
                    <option :value="null">{{ t.select_route_item_group }}</option>
                    <option
                      v-for="option in lookupOptions.routeItemGroups"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.routeitemgrpcode" class="text-danger fs-sm mt-1">
                    {{ form.errors.routeitemgrpcode }}
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">{{ t.item_must_key }}</label>
                  <select v-model="form.itemmustkey" class="form-select" :disabled="isView">
                    <option :value="null">{{ t.select_item_must_key }}</option>
                    <option v-for="option in lookupOptions.itemMustKeys" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <div class="form-check form-switch mt-2">
                        <input
                          id="allow-change-salesman"
                          v-model="form.presalesorder"
                          class="form-check-input"
                          type="checkbox"
                          :true-value="1"
                          :false-value="0"
                          :disabled="isView"
                        />
                        <label class="form-check-label" for="allow-change-salesman">
                          {{ t.allow_change_salesman }}
                        </label>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-check form-switch mt-2">
                        <input
                          id="depot-route"
                          v-model="form.depotrouteflag"
                          class="form-check-input"
                          type="checkbox"
                          :true-value="1"
                          :false-value="0"
                          :disabled="isView"
                        />
                        <label class="form-check-label" for="depot-route">{{ t.depot_route }}</label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-12">
                  <h5 class="border-bottom pb-2 mb-3">{{ t.security_passwords }}</h5>
                </div>
                <div v-for="field in passwordFields" :key="field.key" class="col-md-3 col-xl-2">
                  <label class="form-label">{{ field.label }}</label>
                  <input v-model="form[field.key]" type="number" min="0" class="form-control" :readonly="isView" />
                </div>

                <div class="col-12 mt-2">
                  <h5 class="border-bottom pb-2 mb-3">{{ t.security_assignment }}</h5>
                </div>
                <div v-for="field in securityFields" :key="field.key" class="col-md-3">
                  <label class="form-label">{{ field.label }}</label>
                  <input v-model="form[field.key]" type="number" min="0" class="form-control" :readonly="isView" />
                </div>
              </div>
            </div>

          <div v-show="activeTab === 'settings1'" class="tab-pane active">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">{{ t.unload_over_warning }}</label>
                  <select v-model="form.unloadoversellmessage" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.enableDisableOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.inventory_value_print }}</label>
                  <select v-model="form.inventoryvalueprint" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.inventoryValuePrintOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.prompt_odometer_input }}</label>
                  <select v-model="form.promptodominput" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.promptOdometerOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.inventory_quantity_input }}</label>
                  <select v-model="form.inventorycaseinput" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.inventoryCaseInputOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.auto_calculated_unload }}</label>
                  <select v-model="form.autocalculateloadin" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.autoCalculateLoadInOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.require_unload }}</label>
                  <select v-model="form.requireloadin" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.requireLoadInOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.route_currency }}</label>
                  <select v-model="form.amountdecimaldigits" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.decimalDigitOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.tablet_item_code_display }}</label>
                  <select v-model="form.itemcodedisplay" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.itemCodeDisplayOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.print_alternate_itemcode }}</label>
                  <select v-model="form.inventoryreportcontrol" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.printAlternateOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.tablet_customer_code_display }}</label>
                  <select v-model="form.usealternatecodes" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.useAlternateCodeOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.item_description_display }}</label>
                  <select v-model="form.itemdescriptiondisplay" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.itemDescriptionDisplayOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.enable_load_transfer }}</label>
                  <select v-model="form.enableloadtransfer" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.enableLoadTransferOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.enable_scanner_use }}</label>
                  <select v-model="form.enablescanneruse" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.scannerUseOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.target_and_goal_set_by }}</label>
                  <select v-model="form.salesmantargetdays" class="form-select" :disabled="isView">
                    <option :value="1">{{ t.weekly }}</option>
                    <option :value="2">{{ t.monthly }}</option>
                  </select>
                </div>

                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-md-4" v-for="field in settings1ToggleFields" :key="field[0]">
                      <div class="form-check form-switch mt-2">
                        <input
                          :id="field[0]"
                          v-model="form[field[0]]"
                          class="form-check-input"
                          type="checkbox"
                          :true-value="1"
                          :false-value="0"
                          :disabled="isView"
                        />
                        <label class="form-check-label" :for="field[0]">{{ field[1] }}</label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-12 mt-3">
                  <h5 class="border-bottom pb-2 mb-3">{{ t.gps }}</h5>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch mt-2">
                    <input
                      id="enablegps"
                      v-model="form.enablegps"
                      class="form-check-input"
                      type="checkbox"
                      :true-value="1"
                      :false-value="0"
                      :disabled="isView"
                    />
                    <label class="form-check-label" for="enablegps">{{ t.enable_gps }}</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.allowed_radius }}</label>
                  <input
                    v-model="form.allowedradius"
                    type="number"
                    step="0.00001"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch mt-2">
                    <input
                      id="updategps"
                      v-model="form.updategps"
                      class="form-check-input"
                      type="checkbox"
                      :true-value="1"
                      :false-value="0"
                      :disabled="isView"
                    />
                    <label class="form-check-label" for="updategps">
                      {{ t.update_gps_coordinates }}
                    </label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch mt-2">
                    <input
                      id="enforcegps"
                      v-model="form.enforcegps"
                      class="form-check-input"
                      type="checkbox"
                      :true-value="1"
                      :false-value="0"
                      :disabled="isView"
                    />
                    <label class="form-check-label" for="enforcegps">
                      {{ t.enforce_gps_coordinates_check }}
                    </label>
                  </div>
                </div>
              </div>
            </div>

          <div v-show="activeTab === 'settings2'" class="tab-pane active">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">{{ t.enable_no_sale }}</label>
                  <select v-model="form.enablenosale" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.enableNoSaleOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.cash_balance }}</label>
                  <select v-model="form.cashbalance" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.cashBalanceOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.inventory_variance }}</label>
                  <select v-model="form.inventoryvariance" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.inventoryVarianceOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.inventory_oversell }}</label>
                  <select v-model="form.invenoversell" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.inventoryOversellOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.enable_damage_trxn }}</label>
                  <select v-model="form.enabledamagedtrxn" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.damagedTransactionOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.enable_load_request }}</label>
                  <select v-model="form.includeloadrequest" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.includeLoadRequestOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.load_req_roll_up }}</label>
                  <select v-model="form.loadreqrolluporders" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.loadRequestRollupOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.load_request_method }}</label>
                  <select v-model="form.loadreqmethod" class="form-select" :disabled="isView">
                    <option
                      v-for="option in optionSets.loadRequestMethodOptions"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">{{ t.route_printer }}</label>
                  <select v-model="form.routeprinter" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.printerOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.variance_customer }}</label>
                  <select
                    v-model="form.variancecustomercode"
                    class="form-select"
                    :disabled="isView || !form.enableautopostingaccount"
                  >
                    <option :value="null">{{ t.select_customer }}</option>
                    <option
                      v-for="option in lookupOptions.varianceCustomers"
                      :key="option.id"
                      :value="option.id"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <div v-if="form.errors.variancecustomercode" class="text-danger fs-sm mt-1">
                    {{ form.errors.variancecustomercode }}
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">{{ t.memo_1 }}</label>
                  <input v-model="form.memo1" class="form-control" :readonly="isView" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.memo_2 }}</label>
                  <input v-model="form.memo2" class="form-control" :readonly="isView" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.cdc_validity_days }}</label>
                  <input v-model="form.cdcvaliditydays" type="number" min="0" class="form-control" :readonly="isView" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.new_customer_seq_no }}</label>
                  <input
                    v-model="form.newcustomerseqnumber"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>

                <div class="col-md-3">
                  <label class="form-label">{{ t.vehicle_odometer }}</label>
                  <input
                    v-model="form.vehicleodometer"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.default_delivery_days }}</label>
                  <input
                    v-model="form.defaultdeliverydays"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>

                <div class="col-md-3">
                  <label class="form-label">{{ t.load_request_avg_days }}</label>
                  <input
                    v-model="form.defaultrequestdays"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.force_settlement_days }}</label>
                  <input
                    v-model="form.forcesettlementdays"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-3">
                  <label class="form-label">{{ t.pdc_threshold }}</label>
                  <input v-model="form.pdcthreshold" type="number" step="0.001" class="form-control" :readonly="isView" />
                </div>

                <div class="col-12">
                  <div class="row g-3">
                    <div class="col-md-4" v-for="field in settings2ToggleFields" :key="field[0]">
                      <div class="form-check form-switch mt-2">
                        <input
                          :id="field[0]"
                          v-model="form[field[0]]"
                          class="form-check-input"
                          type="checkbox"
                          :true-value="1"
                          :false-value="0"
                          :disabled="isView"
                        />
                        <label class="form-check-label" :for="field[0]">{{ field[1] }}</label>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-12 mt-3">
                  <h5 class="border-bottom pb-2 mb-3">{{ t.route_credit }}</h5>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch mt-2">
                    <input
                      id="routecreditcheck"
                      v-model="form.routecreditcheck"
                      class="form-check-input"
                      type="checkbox"
                      :true-value="1"
                      :false-value="0"
                      :disabled="isView"
                    />
                    <label class="form-check-label" for="routecreditcheck">
                      {{ t.enable_route_credit }}
                    </label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.credit_limit }}</label>
                  <input
                    v-model="form.creditlimit"
                    type="number"
                    step="0.001"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.route_credit_limit_days }}</label>
                  <input
                    v-model="form.routecreditlimitdays"
                    type="number"
                    min="0"
                    class="form-control"
                    :readonly="isView"
                  />
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ t.route_balance }}</label>
                  <input
                    v-model="form.routebalance"
                    type="number"
                    step="0.001"
                    class="form-control"
                    readonly
                  />
                </div>
              </div>
            </div>

          <div v-show="activeTab === 'reports'" class="tab-pane active">
              <div class="row g-3">
                <div v-for="field in reports" :key="field.key" class="col-md-6 col-xl-4">
                  <label class="form-label">{{ field.label }}</label>
                  <select v-model="form[field.key]" class="form-select" :disabled="isView">
                    <option v-for="option in optionSets.printOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
              </div>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<style lang="scss">
@import "vue-select/dist/vue-select.css";
@import "@scss/vendor/vue-select";
</style>

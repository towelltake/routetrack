<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  templateData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const activeTab = ref("general");
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;

const form = useForm({
  ...props.templateData,
});

const tabs = [
  { key: "general", label: t.general },
  { key: "settings1", label: t.settings_1 },
  { key: "settings2", label: t.settings_2 },
];

const passwordFields = [
  { key: "password1", label: t.password_1 },
  { key: "password2", label: t.password_2 },
  { key: "password3", label: t.password_3 },
  { key: "password4", label: t.password_4 },
  { key: "password5", label: t.password_5 },
];

const securityFields = [
  { key: "passwordarray04", label: t.route_setup },
  { key: "passwordarray15", label: t.load_request },
  { key: "passwordarray05", label: t.telecom_setup },
  { key: "passwordarray16", label: t.view_van_stock },
  { key: "passwordarray08", label: t.application_exit },
  { key: "passwordarray11", label: t.unload },
  { key: "passwordarray07", label: t.start_day },
  { key: "passwordarray02", label: t.price_change },
  { key: "passwordarray01", label: t.date_time_change },
  { key: "passwordarray03", label: t.promo_override },
  { key: "passwordarray12", label: t.load_out },
  { key: "passwordarray09", label: t.settlement },
  { key: "passwordarray06", label: t.load_adjust },
  { key: "passwordarray13", label: t.print_doc_at_eod },
  { key: "passwordarray14", label: t.load_transfer },
];

const settingsOneToggles = [
  ["enableeodaddchecks", t.enable_eod_add_checks],
  ["enabledelayprint", t.enable_delay_print],
  ["enableaddcustomer", t.enable_add_customer],
  ["enforcecallsequence", t.enforce_call_sequence],
  ["enablefoclimit", t.enable_foc_limit],
  ["enablescancustomer", t.enable_scan_customer],
  ["loadoutadjustments", t.load_out_adjustments],
  ["enableeodexpenses", t.enable_eod_expenses],
  ["enablecashonlydiscount", t.enable_cash_only_discount],
  ["enablepostvoid", t.enable_post_void],
  ["enableeodadjchecks", t.enable_eod_adjust_checks],
  ["transactionnoseq", t.special_invoice_sequence],
  ["enablefreereason", t.enable_free_reason],
  ["enablestartdayrtewkdayedit", t.enable_route_weekday_edit],
  ["enablestartdaydatetimeedit", t.enable_start_day_date_time_edit],
  ["voidoverride", t.void_override],
];

const settingsTwoToggles = [
  ["enablemiddaytelecom", t.enable_midday_telecom],
  ["routecreditcheck", t.route_credit_check],
  ["updategps", t.update_gps],
  ["enforcegps", t.enforce_gps],
  ["enablegps", t.enable_gps],
  ["enabledraftcopy", t.enable_draft_copy],
];

const pageTitle = computed(() => {
  if (isCreate.value) return t.create_route_setting_template;
  if (isView.value) return t.view_route_setting_template;
  return t.edit_route_setting_template;
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/organisation/routetemplate");
    return;
  }

  form.put(`/organisation/routetemplate/${form.routecode}`);
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.route_setting_template_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/organisation/routetemplate')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/organisation/routetemplate/${form.routecode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button v-else class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="block-content block-content-full">
        <ul class="nav nav-tabs nav-tabs-block" role="tablist">
          <li v-for="tab in tabs" :key="tab.key" class="nav-item">
            <button class="nav-link" :class="{ active: activeTab === tab.key }" type="button" @click="activeTab = tab.key">
              {{ tab.label }}
            </button>
          </li>
        </ul>

        <div class="tab-content block-content px-0 pb-0">
          <div v-show="activeTab === 'general'" class="tab-pane active">
            <div class="row g-3">
              <div v-if="!isCreate" class="col-md-3">
                <label class="form-label">{{ t.route_code }}</label>
                <input v-model="form.routecode" class="form-control" readonly />
              </div>
              <div :class="isCreate ? 'col-md-8' : 'col-md-5'">
                <label class="form-label">{{ t.route_setting_template_name }} <span class="text-danger">*</span></label>
                <input v-model="form.templatename" class="form-control" :readonly="isView" :required="!isView" />
                <div v-if="form.errors.templatename" class="text-danger fs-sm mt-1">{{ form.errors.templatename }}</div>
              </div>
              <div :class="isCreate ? 'col-md-4' : 'col-md-4'">
                <label class="form-label">{{ t.status }} <span class="text-danger">*</span></label>
                <select v-model="form.activestatus" class="form-select" :disabled="isView" :required="!isView">
                  <option v-for="option in optionSets.statusOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.route_type }} <span class="text-danger">*</span></label>
                <select v-model="form.routetype" class="form-select" :disabled="isView" :required="!isView">
                  <option v-for="option in optionSets.routeTypes" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.route_category }}</label>
                <select v-model="form.routecatcode" class="form-select" :disabled="isView">
                  <option :value="null">{{ t.select_route_category }}</option>
                  <option v-for="option in lookupOptions.routeCategories" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.route_item_group }}</label>
                <select v-model="form.routeitemgrpcode" class="form-select" :disabled="isView">
                  <option :value="null">{{ t.select_route_item_group }}</option>
                  <option v-for="option in lookupOptions.routeItemGroups" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">{{ t.item_must_key }}</label>
                <select v-model="form.itemmustkey" class="form-select" :disabled="isView">
                  <option :value="null">{{ t.select_item_must_key }}</option>
                  <option v-for="option in lookupOptions.itemMustKeys" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                  <input id="template-depot-route" v-model="form.depotrouteflag" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" :disabled="isView" />
                  <label class="form-check-label" for="template-depot-route">{{ t.depot_route }}</label>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-check form-switch mt-4">
                  <input id="template-allow-change-salesman" v-model="form.presalesorder" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" :disabled="isView" />
                  <label class="form-check-label" for="template-allow-change-salesman">{{ t.allow_change_salesman }}</label>
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
                <input v-model="form[field.key]" type="number" min="0" max="5" class="form-control" :readonly="isView" />
              </div>
            </div>
          </div>

          <div v-show="activeTab === 'settings1'" class="tab-pane active">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ t.unload_over_sell_message }}</label>
                <select v-model="form.unloadoversellmessage" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.printOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.inventory_value_print }}</label>
                <select v-model="form.inventoryvalueprint" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.inventoryValuePrintOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.prompt_odometer_input }}</label>
                <select v-model="form.promptodominput" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.promptOdometerOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.inventory_case_input }}</label>
                <select v-model="form.inventorycaseinput" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.inventoryCaseInputOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.load_request_report }}</label>
                <select v-model="form.loadreqreportformat" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.loadRequestReportOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.auto_calculate_load_in }}</label>
                <select v-model="form.autocalculateloadin" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.autoCalculateLoadInOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.require_load_in }}</label>
                <select v-model="form.requireloadin" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.requireLoadInOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.amount_decimal_digits }}</label>
                <select v-model="form.amountdecimaldigits" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.decimalDigitOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.item_code_display }}</label>
                <select v-model="form.itemcodedisplay" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.itemCodeDisplayOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.use_alternate_codes }}</label>
                <select v-model="form.usealternatecodes" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.useAlternateCodeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.item_description_display }}</label>
                <select v-model="form.itemdescriptiondisplay" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.itemDescriptionDisplayOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.enable_load_transfer }}</label>
                <select v-model="form.enableloadtransfer" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.enableLoadTransferOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.scanner_use }}</label>
                <select v-model="form.enablescanneruse" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.scannerUseOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.inventory_report_control }}</label>
                <select v-model="form.inventoryreportcontrol" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.printAlternateOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.route_unload_variance }}</label>
                <input v-model="form.routeunloadvariance" type="number" step="0.001" class="form-control" :readonly="isView" />
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.salesman_target_days }}</label>
                <input v-model="form.salesmantargetdays" type="number" min="0" class="form-control" :readonly="isView" />
              </div>

              <div class="col-12">
                <div class="row g-3">
                  <div class="col-md-3" v-for="field in settingsOneToggles" :key="field[0]">
                    <div class="form-check form-switch mt-2">
                      <input :id="field[0]" v-model="form[field[0]]" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" :disabled="isView" />
                      <label class="form-check-label" :for="field[0]">{{ field[1] }}</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-show="activeTab === 'settings2'" class="tab-pane active">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ t.enable_no_sale }}</label>
                <select v-model="form.enablenosale" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.enableNoSaleOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.cash_balance }}</label>
                <select v-model="form.cashbalance" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.cashBalanceOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.inventory_variance }}</label>
                <select v-model="form.inventoryvariance" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.inventoryVarianceOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.inventory_oversell }}</label>
                <select v-model="form.invenoversell" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.inventoryOversellOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.damaged_transactions }}</label>
                <select v-model="form.enabledamagedtrxn" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.damagedTransactionOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.display_inventory_summary }}</label>
                <select v-model="form.displayinvsummary" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.displayInventorySummaryOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.include_load_request }}</label>
                <select v-model="form.includeloadrequest" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.includeLoadRequestOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.load_request_rollup_orders }}</label>
                <select v-model="form.loadreqrolluporders" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.loadRequestRollupOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.load_request_method }}</label>
                <select v-model="form.loadreqmethod" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.loadRequestMethodOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label">{{ t.route_printer }}</label>
                <select v-model="form.routeprinter" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.printerOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ t.depot_printer }}</label>
                <select v-model="form.depotprinter" class="form-select" :disabled="isView">
                  <option v-for="option in optionSets.depotPrinterOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </select>
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
                <input v-model="form.newcustomerseqnumber" type="number" min="0" class="form-control" :readonly="isView" />
              </div>

              <div class="col-md-3">
                <label class="form-label">{{ t.credit_limit }}</label>
                <input v-model="form.creditlimit" type="number" step="0.001" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.route_balance }}</label>
                <input v-model="form.routebalance" type="number" step="0.001" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.vehicle_odometer }}</label>
                <input v-model="form.vehicleodometer" type="number" min="0" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.default_delivery_days }}</label>
                <input v-model="form.defaultdeliverydays" type="number" min="0" class="form-control" :readonly="isView" />
              </div>

              <div class="col-md-3">
                <label class="form-label">{{ t.default_request_days }}</label>
                <input v-model="form.defaultrequestdays" type="number" min="0" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.force_settlement_days }}</label>
                <input v-model="form.forcesettlementdays" type="number" min="0" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.pdc_threshold }}</label>
                <input v-model="form.pdcthreshold" type="number" step="0.001" class="form-control" :readonly="isView" />
              </div>
              <div class="col-md-3">
                <label class="form-label">{{ t.allowed_radius }}</label>
                <input v-model="form.allowedradius" type="number" step="0.001" class="form-control" :readonly="isView" />
              </div>

              <div class="col-md-3">
                <label class="form-label">{{ t.route_credit_limit_days }}</label>
                <input v-model="form.routecreditlimitdays" type="number" min="0" class="form-control" :readonly="isView" />
              </div>

              <div class="col-12">
                <div class="row g-3">
                  <div class="col-md-3" v-for="field in settingsTwoToggles" :key="field[0]">
                    <div class="form-check form-switch mt-2">
                      <input :id="field[0]" v-model="form[field[0]]" class="form-check-input" type="checkbox" :true-value="1" :false-value="0" :disabled="isView" />
                      <label class="form-check-label" :for="field[0]">{{ field[1] }}</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

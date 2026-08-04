<script setup>
import { computed, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  customerData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
  formConfig: {
    type: Object,
    default: () => ({}),
  },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const config = computed(() => ({
  titleBase: t.customer,
  backUrl: "/account/customer",
  submitBaseUrl: "/account/customer",
  subtitle: t.customer_form_note,
  showContactTab: true,
  showTemplateName: false,
  templateMode: false,
  ...props.formConfig,
}));
const pageTitle = computed(() =>
  isCreate.value ? `${t.create} ${config.value.titleBase}` : isView.value ? `${t.view} ${config.value.titleBase}` : `${t.edit} ${config.value.titleBase}`,
);

const form = useForm({
  ...props.customerData,
});

const settings1MessageFields = [
  ["messagekey1", t.display_message_1],
  ["messagekey2", t.display_message_2],
  ["messagekey3", t.print_message_1],
  ["messagekey4", t.print_message_2],
  ["messagekey5", t.invoice_header_message],
  ["messagekey6", t.invoice_trailer_message],
];

const settings1CheckboxFields = [
  ["enablesurveyaudit", t.enable_survey],
  ["enableinvoicecomment", t.enable_invoice_comment],
  ["autosettlecollection", t.auto_settle_collection],
  ["enableupcprint", t.enable_item_barcode_print],
  ["enabledelayprint", t.enable_delay_print],
  ["enableinvoicecopy", t.enable_invoice_copy],
  ["enablerental", t.enable_rental],
  ["enableposequipment", t.enable_pos],
  ["enableadvancepayment", t.enable_advance_payment],
  ["enableautofillsales", t.enable_auto_fill_sales],
  ["enablebatchselection", t.enable_batch_selection],
  ["enablebuybackfree", t.enable_buy_back_free],
  ["enableexchangetrxn", t.enable_swapping],
  ["enablereturnpassword", t.use_password_for_returns],
];

const creditFieldsDisabled = computed(() => [0, 1].includes(Number(form.invoicepaymentterms)));
const headOfficeRequired = computed(() => String(form.customertype) === "2");

watch(
  () => form.customertype,
  (value) => {
    if (String(value) !== "2") {
      form.headofficecode = null;
    }
  },
);

watch(
  () => form.arcustomertype,
  (value) => {
    if (Number(value) !== 1) {
      form.tclimit = null;
    }
  },
);

watch(
  () => form.invoicepaymentterms,
  (value) => {
    if ([0, 1].includes(Number(value))) {
      form.creditlimit = 0;
      form.creditlimitdays = 0;
      form.tclimit = 0;
      form.arcustomertype = 0;
    }
  },
  { immediate: true },
);

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post(config.value.submitBaseUrl);
    return;
  }

  form.put(`${config.value.submitBaseUrl}/${form.customercode}`);
}

function formatDate(value) {
  if (!value) {
    return "-";
  }

  return new Date(value).toLocaleDateString("en-GB");
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="config.subtitle"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(config.backUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`${config.submitBaseUrl}/${form.customercode}/edit`)"
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
    <div v-if="Object.keys(form.errors).length" class="alert alert-danger">
      <div class="fw-semibold mb-2">{{ t.save_failed_help }}</div>
      <ul class="mb-0 ps-3">
        <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
      </ul>
    </div>

    <BaseBlock>
      <ul class="nav nav-tabs nav-tabs-block mb-4" role="tablist">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">
            {{ t.general }}
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settings1" type="button">
            {{ t.settings_1 }}
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settings2" type="button">
            {{ t.settings_2 }}
          </button>
        </li>
        <li v-if="config.showContactTab" class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button">
            {{ t.contact }}
          </button>
        </li>
      </ul>

      <div class="tab-content">
        <div id="tab-general" class="tab-pane active" role="tabpanel">
          <div class="row g-4 mb-3">
            <div class="col-md-3">
              <label class="form-label">{{ t.code }}</label>
              <input v-model="form.customercode" class="form-control" readonly />
            </div>
            <div v-if="!config.templateMode" class="col-md-5">
              <label class="form-label">{{ t.route }} <span class="text-danger">*</span></label>
              <select v-model="form.routecode" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.routeOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('routecode')" class="text-danger fs-sm mt-1">{{ errorFor("routecode") }}</div>
            </div>
            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.barcode }}</label>
              <input v-model="form.barcode" class="form-control" :readonly="isView" />
            </div>

            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.alternate_code }} <span class="text-danger">*</span></label>
              <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
              <div v-if="errorFor('alternatecode')" class="text-danger fs-sm mt-1">{{ errorFor("alternatecode") }}</div>
            </div>
            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.customer_type }} <span class="text-danger">*</span></label>
              <select v-model="form.customertype" class="form-select" :disabled="isView">
                <option v-for="option in optionSets.customerTypeOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('customertype')" class="text-danger fs-sm mt-1">{{ errorFor("customertype") }}</div>
            </div>
            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.head_office }} <span v-if="headOfficeRequired" class="text-danger">*</span></label>
              <select v-model="form.headofficecode" class="form-select" :disabled="isView || !headOfficeRequired">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.headOfficeOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('headofficecode')" class="text-danger fs-sm mt-1">{{ errorFor("headofficecode") }}</div>
            </div>

            <div v-if="!config.templateMode" class="col-md-6">
              <label class="form-label">{{ t.name }} <span class="text-danger">*</span></label>
              <input v-model="form.customername" class="form-control" :readonly="isView" />
              <div v-if="errorFor('customername')" class="text-danger fs-sm mt-1">{{ errorFor("customername") }}</div>
            </div>
            <div v-if="!config.templateMode" class="col-md-6">
              <label class="form-label">{{ t.arabic_name }}</label>
              <input v-model="form.arbcustomername" class="form-control" dir="rtl" :readonly="isView" />
            </div>
            <div v-if="config.showTemplateName" class="col-md-6">
              <label class="form-label">{{ t.template_name }} <span class="text-danger">*</span></label>
              <input v-model="form.templatename" class="form-control" :readonly="isView" />
              <div v-if="errorFor('templatename')" class="text-danger fs-sm mt-1">{{ errorFor("templatename") }}</div>
            </div>

            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.telephone_number }}</label>
              <input v-model="form.customerphone" class="form-control" :readonly="isView" />
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.delivery_slot_from }}</label>
              <input v-model="form.delivery_slot_from" type="time" class="form-control" :readonly="isView" />
              <div v-if="errorFor('delivery_slot_from')" class="text-danger fs-sm mt-1">{{ errorFor("delivery_slot_from") }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.delivery_slot_to }}</label>
              <input v-model="form.delivery_slot_to" type="time" class="form-control" :readonly="isView" />
              <div v-if="errorFor('delivery_slot_to')" class="text-danger fs-sm mt-1">{{ errorFor("delivery_slot_to") }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.auto_jp_priority }}</label>
              <input v-model="form.autojp_priority" type="number" min="0" max="100" class="form-control" :readonly="isView" />
              <div v-if="errorFor('autojp_priority')" class="text-danger fs-sm mt-1">{{ errorFor("autojp_priority") }}</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input id="allow_cross_route_jp" v-model="form.allow_cross_route_jp" :true-value="1" :false-value="0" class="form-check-input" type="checkbox" :disabled="isView">
                <label class="form-check-label" for="allow_cross_route_jp">
                  {{ t.allow_cross_route_auto_jp }}
                </label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.channel }}</label>
              <select v-model="form.channelcode" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.channelOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.category }}</label>
              <select v-model="form.customercategory" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.categoryOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.special_price }}</label>
              <select v-model="form.pricingkey" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.pricingKeyOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('pricingkey')" class="text-danger fs-sm mt-1">{{ errorFor("pricingkey") }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.promotions }}</label>
              <select v-model="form.promotionkey" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.promotionKeyOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('promotionkey')" class="text-danger fs-sm mt-1">{{ errorFor("promotionkey") }}</div>
            </div>

            <div v-if="!config.templateMode" class="col-md-6"><label class="form-label">{{ t.address_1 }}</label><input v-model="form.customeraddress1" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-6"><label class="form-label">{{ t.address_2 }}</label><input v-model="form.customeraddress2" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-4"><label class="form-label">{{ t.address_3 }}</label><input v-model="form.customeraddress3" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-4"><label class="form-label">{{ t.city }}</label><input v-model="form.customercity" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-4"><label class="form-label">{{ t.po_box }}</label><input v-model="form.pobox" class="form-control" :readonly="isView" /></div>

            <div class="col-md-4">
              <label class="form-label">{{ t.invoice_payment_terms }} <span class="text-danger">*</span></label>
              <select v-model="form.invoicepaymentterms" class="form-select" :disabled="isView">
                <option v-for="option in optionSets.invoicePaymentTermOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="errorFor('invoicepaymentterms')" class="text-danger fs-sm mt-1">{{ errorFor("invoicepaymentterms") }}</div>
            </div>
            <div v-if="!config.templateMode" class="col-md-4">
              <label class="form-label">{{ t.currency }}</label>
              <select v-model="form.currencycode" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.currencyOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ t.status }}</label>
              <select v-model="form.activecustomer" class="form-select" :disabled="isView">
                <option v-for="option in optionSets.statusOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div class="col-md-4"><label class="form-label">{{ t.credit_limit }}</label><input v-model="form.creditlimit" type="number" step="0.0001" class="form-control" :readonly="isView || creditFieldsDisabled" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.credit_days }}</label><input v-model="form.creditlimitdays" type="number" class="form-control" :readonly="isView || creditFieldsDisabled" /></div>

            <div class="col-md-4">
              <label class="form-label">{{ t.ar_customer_type }}</label>
              <select v-model="form.arcustomertype" class="form-select" :disabled="isView || creditFieldsDisabled">
                <option v-for="option in optionSets.arCustomerTypeOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">{{ t.bill_to_bill }}</label><input v-model="form.tclimit" type="number" class="form-control" :readonly="isView || creditFieldsDisabled || Number(form.arcustomertype) !== 1" /></div>
          </div>
        </div>

        <div id="tab-settings1" class="tab-pane" role="tabpanel">
          <div class="row g-4 mb-3">
            <div class="col-md-4" v-for="field in settings1MessageFields" :key="field[0]">
              <label class="form-label">{{ field[1] }}</label>
              <select v-model="form[field[0]]" class="form-select" :disabled="isView">
                <option :value="null">{{ t.select }}</option>
                <option v-for="option in optionSets.messageOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
              </select>
            </div>

            <div class="col-md-4"><label class="form-label">{{ t.print_sequence }}</label><select v-model="form.printsequence" class="form-select" :disabled="isView"><option v-for="option in optionSets.printSequenceOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_stock_capture }}</label><select v-model="form.forcestockcapture" class="form-select" :disabled="isView"><option v-for="option in optionSets.stockCaptureOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.invoice_price_print }}</label><select v-model="form.invoicepriceprint" class="form-select" :disabled="isView"><option v-for="option in optionSets.invoicePricePrintOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_edit_price_invs }}</label><select v-model="form.enablepriceeditinvs" class="form-select" :disabled="isView"><option v-for="option in optionSets.editPriceOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_sell_previous }}</label><select v-model="form.enablesellprevious" class="form-select" :disabled="isView"><option v-for="option in optionSets.sellPreviousOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_suggest_sales }}</label><select v-model="form.enablesuggestsales" class="form-select" :disabled="isView"><option v-for="option in optionSets.suggestSalesOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_auto_fill_damage }}</label><select v-model="form.enableautofilldamaged" class="form-select" :disabled="isView"><option v-for="option in optionSets.autoFillOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_free }}</label><select v-model="form.enablepromotrxn" class="form-select" :disabled="isView"><option v-for="option in optionSets.salesTransactionOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_auto_fill_returns }}</label><select v-model="form.enableautofillreturns" class="form-select" :disabled="isView"><option v-for="option in optionSets.autoFillOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_sales }}</label><select v-model="form.enablesalestrxn" class="form-select" :disabled="isView"><option v-for="option in optionSets.salesTransactionOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_signature_capture }}</label><select v-model="form.enablesigcapture" class="form-select" :disabled="isView"><option v-for="option in optionSets.signatureCaptureOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_damage_returns }}</label><select v-model="form.enabledamagedreturns" class="form-select" :disabled="isView"><option v-for="option in optionSets.salesTransactionOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_good_returns }}</label><select v-model="form.enablereturnstrxn" class="form-select" :disabled="isView"><option v-for="option in optionSets.salesTransactionOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_ar_collection }}</label><select v-model="form.enablearcollection" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>

            <div class="col-md-3" v-for="field in settings1CheckboxFields" :key="field[0]">
              <div class="form-check form-switch mt-4">
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

        <div id="tab-settings2" class="tab-pane" role="tabpanel">
          <div class="row g-4 mb-3">
            <div class="col-md-4"><label class="form-label">{{ t.sales }}</label><select v-model="form.invoiceformatoption" class="form-select" :disabled="isView"><option v-for="option in optionSets.invoiceFormatOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.print_language }}</label><select v-model="form.printlanguageflag" class="form-select" :disabled="isView"><option v-for="option in optionSets.printLanguageOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.latitude_coordinates }}</label><input v-model="form.fixedlatitude" type="number" step="0.0000000001" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.longitude_coordinates }}</label><input v-model="form.fixedlongitude" type="number" step="0.0000000001" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_sales_promotion }}</label><select v-model="form.enablepromoeditinvs" class="form-select" :disabled="isView"><option v-for="option in optionSets.promoEditOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_rounding }}</label><select v-model="form.roundnetamount" class="form-select" :disabled="isView"><option v-for="option in optionSets.roundingOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_order_promotion }}</label><select v-model="form.enablepromoeditords" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.rounding_type }}</label><select v-model="form.roundingoffvalue" class="form-select" :disabled="isView"><option :value="0">{{ t.normal }}</option><option :value="1">{{ t.round_down }}</option><option :value="2">{{ t.round_up }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.history_max_deliveries }}</label><input v-model="form.histmaxdeliveries" type="number" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.planogram_key }}</label><select v-model="form.visualcode" class="form-select" :disabled="isView"><option :value="null">{{ t.select }}</option><option v-for="option in optionSets.planogramOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.forward_factor }}</label><input v-model="form.forwardcoverfactor" type="number" step="0.01" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.control_invoice_transactions }}</label><select v-model="form.invoicelimiter" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.zip }}</label><input v-model="form.customerzip" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.enforce_promotion }}</label><select v-model="form.enforcepromotion" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.enable_draft_copy }}</label><select v-model="form.enabledraftcopy" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t.print_outlet_item_code }}</label><select v-model="form.printoutletitemcode" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div v-if="config.useGracePeriod" class="col-md-4"><label class="form-label">{{ t.grace_period }}</label><input v-model="form.graceperiod" type="number" min="0" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.allow_cash_if_credit_exceeds }}</label><select v-model="form.allowcashoncreditexceed" class="form-select" :disabled="isView"><option v-for="option in optionSets.yesNoOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t.memo_1 }}</label><input v-model="form.memo1" class="form-control" :readonly="isView" /></div>
            <div class="col-md-6"><label class="form-label">{{ t.memo_2 }}</label><input v-model="form.memo2" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-6"><label class="form-label">{{ t.tra_registered_name }}</label><input v-model="form.traname" class="form-control" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-6"><label class="form-label">{{ t.tra_registered_name_arabic }}</label><input v-model="form.tranamearabic" class="form-control" dir="rtl" :readonly="isView" /></div>
            <div v-if="!config.templateMode" class="col-md-4"><label class="form-label">{{ t.tax_registration }}</label><input v-model="form.taxregistrationnumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ config.templateMode ? t.tax_option : t.tax_print }}</label><select v-model="form.customertaxidoptions" class="form-select" :disabled="isView"><option v-for="option in optionSets.taxPrintOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
            <div v-if="!config.templateMode" class="col-md-4"><label class="form-label">{{ t.apply_tax }}</label><select v-model="form.applytax" class="form-select" :disabled="isView"><option v-for="option in optionSets.applyTaxOptions" :key="option.id" :value="option.id">{{ option.label }}</option></select></div>
          </div>
        </div>

        <div v-if="config.showContactTab" id="tab-contact" class="tab-pane" role="tabpanel">
          <div class="row g-4 mb-3">
            <div class="col-md-4"><label class="form-label">{{ t.shop_telephone }}</label><input v-model="form.shoptelephonenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.shop_fax }}</label><input v-model="form.shopfaxnumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.owner_name }}</label><input v-model="form.ownername" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.owner_land_line }}</label><input v-model="form.ownerlandlinenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.owner_mobile }}</label><input v-model="form.ownermobilenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.contact_person_name }}</label><input v-model="form.contactname" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.contact_person_land_line }}</label><input v-model="form.contactpersonlandlinenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.contact_person_mobile }}</label><input v-model="form.contactpersonmobilenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.contact_person_email }}</label><input v-model="form.contactpersonemail" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.purchase_manager_name }}</label><input v-model="form.purchasemanagername" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.purchase_manager_landline }}</label><input v-model="form.purchasemanagerlandlinenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.purchase_manager_mobile }}</label><input v-model="form.purchasemanagermobilenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.purchase_manager_email }}</label><input v-model="form.purchasemanageremail" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.warehouse_manager_name }}</label><input v-model="form.warehousemanagername" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.warehouse_manager_land_line }}</label><input v-model="form.warehousemanagerlandlinenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.warehouse_manager_mobile }}</label><input v-model="form.warehousemanagermobilenumber" class="form-control" :readonly="isView" /></div>
            <div class="col-md-4"><label class="form-label">{{ t.warehouse_manager_email }}</label><input v-model="form.warehousemanageremail" class="form-control" :readonly="isView" /></div>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

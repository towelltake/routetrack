<script setup>
import axios from "axios";
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  debitNoteRouteData: { type: Object, required: true },
  initialMeta: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));
const isView = computed(() => props.mode === "view");
const pageTitle = computed(() =>
  isView.value ? t.view_route_debit_note : t.create_route_debit_note,
);

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
  documentnumber: props.initialMeta.documentnumber ?? props.debitNoteRouteData.documentnumber ?? "",
  invoicenumber: props.initialMeta.invoicenumber ?? props.debitNoteRouteData.invoicenumber ?? "",
});

const form = useForm({
  date: props.filters.date,
  routecode: props.debitNoteRouteData.routecode ?? "",
  salesmancode: props.debitNoteRouteData.salesmancode ?? "",
  amount: props.debitNoteRouteData.amount ?? "",
  remarks1: props.debitNoteRouteData.remarks1 ?? "",
  remarks2: props.debitNoteRouteData.remarks2 ?? "",
  erpreferencenumber: props.debitNoteRouteData.erpreferencenumber ?? "",
});

const routeLabel = computed(() => {
  if (isView.value) {
    return props.debitNoteRouteData.routeLabel || "-";
  }

  return props.routeOptions.find((option) => String(option.id) === String(form.routecode))?.label || "-";
});

async function loadRouteMeta() {
  if (!form.routecode || isView.value) {
    routeMeta.value = {
      salesmancode: "",
      salesmanname: "",
      documentnumber: "",
      invoicenumber: "",
    };
    form.salesmancode = "";
    return;
  }

  const { data } = await axios.get("/account/transaction/debit-note/route/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
    documentnumber: data.documentnumber ?? "",
    invoicenumber: data.invoicenumber ?? "",
  };

  form.salesmancode = data.salesmancode ?? "";
}

function submit() {
  if (isView.value) {
    return;
  }

  form.post("/account/transaction/debit-note/route");
}

function backToOverview() {
  router.get("/account/transaction/debit-note/route", { date: props.filters.date });
}

function removeRecord() {
  if (!props.debitNoteRouteData.transactionkey || !window.confirm(t.route_debit_note_delete_confirm)) {
    return;
  }

  router.delete(`/account/transaction/debit-note/route/${props.debitNoteRouteData.transactionkey}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.debit_note_route_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToOverview">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button v-if="!isView" class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
        <button v-else-if="canDelete" class="btn btn-alt-danger" @click="removeRecord">
          <i class="fa fa-trash me-1"></i> {{ t.delete }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.debit_note_route_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_date }}</label>
          <input v-model="form.date" type="date" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no }}</label>
          <input :value="isView ? debitNoteRouteData.documentnumber : routeMeta.documentnumber" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.invoice_number }}</label>
          <input :value="isView ? debitNoteRouteData.invoicenumber : routeMeta.invoicenumber" class="form-control" readonly />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.routecode" class="form-select" @change="loadRouteMeta">
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <input v-else :value="routeLabel" class="form-control" readonly />
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">{{ form.errors.routecode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <input
            :value="isView ? `${debitNoteRouteData.salesmancode} - ${debitNoteRouteData.salesmanname || '-'}` : routeMeta.salesmanname ? `${routeMeta.salesmancode} - ${routeMeta.salesmanname}` : ''"
            class="form-control"
            readonly
          />
          <div v-if="form.errors.salesmancode" class="text-danger fs-sm mt-1">{{ form.errors.salesmancode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.amount }} <span v-if="!isView" class="text-danger">*</span></label>
          <input v-model="form.amount" type="number" step="0.01" class="form-control" :readonly="isView" />
          <div v-if="form.errors.amount" class="text-danger fs-sm mt-1">{{ form.errors.amount }}</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_1 }}</label>
          <input v-model="form.remarks1" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_2 }}</label>
          <input v-model="form.remarks2" class="form-control" :readonly="isView" />
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

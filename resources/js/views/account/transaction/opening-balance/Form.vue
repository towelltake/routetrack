<script setup>
import axios from "axios";
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  openingBalanceData: { type: Object, required: true },
  initialMeta: { type: Object, required: true },
});

const openingBalanceData = props.openingBalanceData;

const page = usePage();
const t = page.props.translations.ui;
const permission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canDelete = computed(() => !!(permission.value.all || permission.value.delete));
const isView = computed(() => props.mode === "view");
const pageTitle = computed(() =>
  isView.value ? t.view_opening_balance : t.create_opening_balance,
);

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
  documentnumber: props.initialMeta.documentnumber ?? props.openingBalanceData.documentnumber ?? "",
  invoicenumber: props.initialMeta.invoicenumber ?? props.openingBalanceData.invoicenumber ?? "",
  customerOptions: props.initialMeta.customerOptions ?? [],
});

const form = useForm({
  date: props.filters.date,
  routecode: props.openingBalanceData.routecode ?? "",
  salesmancode: props.openingBalanceData.salesmancode ?? "",
  customercode: props.openingBalanceData.customercode ?? "",
  amount: props.openingBalanceData.amount ?? "",
  remarks1: props.openingBalanceData.remarks1 ?? "",
  remarks2: props.openingBalanceData.remarks2 ?? "",
  erpreferencenumber: props.openingBalanceData.erpreferencenumber ?? "",
});

const routeLabel = computed(() => {
  if (isView.value) {
    return props.openingBalanceData.routeLabel || "-";
  }

  const selected = props.routeOptions.find((option) => String(option.id) === String(form.routecode));

  return selected?.label || "-";
});

const customerLabel = computed(() => {
  if (isView.value) {
    return props.openingBalanceData.customerLabel || "-";
  }

  const selected = routeMeta.value.customerOptions.find(
    (option) => String(option.id) === String(form.customercode),
  );

  return selected?.label || "-";
});

async function loadRouteMeta(resetCustomer = true) {
  if (!form.routecode || isView.value) {
    routeMeta.value = {
      salesmancode: "",
      salesmanname: "",
      documentnumber: "",
      invoicenumber: "",
      customerOptions: [],
    };
    form.salesmancode = "";
    if (resetCustomer) {
      form.customercode = "";
    }
    return;
  }

  const { data } = await axios.get("/account/transaction/opening-balance/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
    documentnumber: data.documentnumber ?? "",
    invoicenumber: data.invoicenumber ?? "",
    customerOptions: data.customerOptions ?? [],
  };

  form.salesmancode = data.salesmancode ?? "";

  if (resetCustomer) {
    form.customercode = "";
  }
}

function submit() {
  if (isView.value) {
    return;
  }

  form.post("/account/transaction/opening-balance");
}

function backToOverview() {
  router.get("/account/transaction/opening-balance", { date: props.filters.date });
}

function removeRecord() {
  if (!props.openingBalanceData.transactionkey || !window.confirm(t.opening_balance_delete_confirm)) {
    return;
  }

  router.delete(`/account/transaction/opening-balance/${props.openingBalanceData.transactionkey}`, {
    data: { date: props.filters.date },
  });
}
</script>

<template>
  <Head :title="pageTitle" />

    <BasePageHeading
    :title="pageTitle"
    :subtitle="t.opening_balance_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToOverview">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button v-if="!isView" class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
        <button
          v-else-if="canDelete"
          class="btn btn-alt-danger"
          @click="removeRecord"
        >
          <i class="fa fa-trash me-1"></i> {{ t.delete }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.opening_balance_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.selected_date }}</label>
          <input v-model="form.date" type="date" class="form-control" :readonly="isView" />
          <div v-if="form.errors.date" class="text-danger fs-sm mt-1">{{ form.errors.date }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.document_no }}</label>
          <input
            :value="isView ? openingBalanceData.documentnumber : routeMeta.documentnumber"
            class="form-control"
            readonly
          />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.invoice_number }}</label>
          <input
            :value="isView ? openingBalanceData.invoicenumber : routeMeta.invoicenumber"
            class="form-control"
            readonly
          />
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span v-if="!isView" class="text-danger">*</span></label>
          <select
            v-if="!isView"
            v-model="form.routecode"
            class="form-select"
            @change="loadRouteMeta()"
          >
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <input v-else :value="routeLabel" class="form-control" readonly />
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">{{ form.errors.routecode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <input
            :value="isView ? `${openingBalanceData.salesmancode} - ${openingBalanceData.salesmanname || '-'}` : routeMeta.salesmanname ? `${routeMeta.salesmancode} - ${routeMeta.salesmanname}` : ''"
            class="form-control"
            readonly
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer }} <span v-if="!isView" class="text-danger">*</span></label>
          <select v-if="!isView" v-model="form.customercode" class="form-select">
            <option value="">{{ t.select_customer }}</option>
            <option
              v-for="option in routeMeta.customerOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
          <input v-else :value="customerLabel" class="form-control" readonly />
          <div v-if="form.errors.customercode" class="text-danger fs-sm mt-1">{{ form.errors.customercode }}</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.amount }} <span v-if="!isView" class="text-danger">*</span></label>
          <input
            v-model="form.amount"
            type="number"
            step="0.01"
            class="form-control"
            :readonly="isView"
          />
          <div v-if="form.errors.amount" class="text-danger fs-sm mt-1">{{ form.errors.amount }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_1 }}</label>
          <input v-model="form.remarks1" class="form-control" :readonly="isView" />
          <div v-if="form.errors.remarks1" class="text-danger fs-sm mt-1">{{ form.errors.remarks1 }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark_2 }}</label>
          <input v-model="form.remarks2" class="form-control" :readonly="isView" />
          <div v-if="form.errors.remarks2" class="text-danger fs-sm mt-1">{{ form.errors.remarks2 }}</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" :readonly="isView" />
          <div v-if="form.errors.erpreferencenumber" class="text-danger fs-sm mt-1">
            {{ form.errors.erpreferencenumber }}
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

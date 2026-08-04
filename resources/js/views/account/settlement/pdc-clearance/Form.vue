<script setup>
import axios from "axios";
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  routeOptions: { type: Array, required: true },
  bankOptions: { type: Array, required: true },
  initialMeta: { type: Object, required: true },
  rows: { type: Array, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const formPermission = computed(
  () => page.props.auth?.formPermissions?.["account transaction"] ?? {},
);
const canCreate = computed(() => !!(formPermission.value.all || formPermission.value.create));

const routeMeta = ref({
  salesmancode: props.initialMeta.salesmancode ?? "",
  salesmanname: props.initialMeta.salesmanname ?? "",
  customerOptions: props.initialMeta.customerOptions ?? [],
});

const rows = ref(props.rows ?? []);
const selectedRefs = ref([]);

const form = useForm({
  date: props.filters.date,
  routecode: "",
  salesmancode: "",
  customercode: 0,
  bankcode: "",
  bank_date: "",
  erpreferencenumber: "",
  bankreferenceno: "",
  remark: "",
  selected_refs: [],
});

const routeLabel = computed(
  () => props.routeOptions.find((option) => String(option.id) === String(form.routecode))?.label || "-",
);

const salesmanLabel = computed(() =>
  routeMeta.value.salesmancode
    ? `${routeMeta.value.salesmancode} - ${routeMeta.value.salesmanname || "-"}`
    : "",
);

async function loadRouteMeta() {
  if (!form.routecode) {
    routeMeta.value = { salesmancode: "", salesmanname: "", customerOptions: [] };
    form.salesmancode = "";
    form.customercode = 0;
    return;
  }

  const { data } = await axios.get("/account/settlement/pdc-clearance/route-meta", {
    params: { routecode: form.routecode },
  });

  routeMeta.value = {
    salesmancode: data.salesmancode ?? "",
    salesmanname: data.salesmanname ?? "",
    customerOptions: data.customerOptions ?? [],
  };
  form.salesmancode = data.salesmancode ?? "";
  form.customercode = 0;
}

async function populateRows() {
  const { data } = await axios.get("/account/settlement/pdc-clearance/populate", {
    params: {
      routecode: form.routecode,
      salesmancode: form.salesmancode,
      customercode: form.customercode,
      date: form.date,
    },
  });

  rows.value = data.rows ?? [];
  selectedRefs.value = [];
}

function toggleSelection(transactionRef, checked) {
  if (checked) {
    if (!selectedRefs.value.includes(transactionRef)) {
      selectedRefs.value.push(transactionRef);
    }
    return;
  }

  selectedRefs.value = selectedRefs.value.filter((value) => value !== transactionRef);
}

function submitClear() {
  form.selected_refs = selectedRefs.value;
  form.post("/account/settlement/pdc-clearance/clear");
}

function submitBounce() {
  form.selected_refs = selectedRefs.value;
  form.post("/account/settlement/pdc-clearance/bounce");
}

function backToOverview() {
  router.get("/account/settlement/pdc-clearance", { date: props.filters.date });
}
</script>

<template>
  <Head :title="t.create_pdc_clearance" />

  <BasePageHeading
    :title="t.create_pdc_clearance"
    :subtitle="t.pdc_clearance_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToOverview">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button class="btn btn-alt-primary" :disabled="!form.routecode" @click="populateRows">
          <i class="fa fa-rotate me-1"></i> {{ t.populate }}
        </button>
        <button v-if="canCreate" class="btn btn-primary" :disabled="!selectedRefs.length || form.processing" @click="submitClear">
          <i class="fa fa-check me-1"></i> {{ t.clear }}
        </button>
        <button v-if="canCreate" class="btn btn-alt-danger" :disabled="!selectedRefs.length || form.processing" @click="submitBounce">
          <i class="fa fa-xmark me-1"></i> {{ t.bounce }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.pdc_clearance_details">
      <div class="row g-4 mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.route }} <span class="text-danger">*</span></label>
          <select v-model="form.routecode" class="form-select" @change="loadRouteMeta">
            <option value="">{{ t.select_route }}</option>
            <option v-for="option in routeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="form.errors.routecode" class="text-danger fs-sm mt-1">{{ form.errors.routecode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <input :value="salesmanLabel" class="form-control" readonly />
          <div v-if="form.errors.salesmancode" class="text-danger fs-sm mt-1">{{ form.errors.salesmancode }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.date }}</label>
          <input v-model="form.date" type="date" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer }}</label>
          <select v-model="form.customercode" class="form-select">
            <option :value="0">{{ t.all_customers }}</option>
            <option v-for="option in routeMeta.customerOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.pending_cheques" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 48px">{{ t.select_row }}</th>
              <th>{{ t.trxn_no }}</th>
              <th>{{ t.customer }}</th>
              <th>{{ t.cheque_no }}</th>
              <th>{{ t.cheque_date }}</th>
              <th>{{ t.bank_master }}</th>
              <th class="text-end">{{ t.cheque_amount }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">{{ t.no_pending_cheque_records_found }}</td>
            </tr>
            <tr v-for="row in rows" :key="row.transaction_ref">
              <td>
                <input
                  class="form-check-input"
                  type="checkbox"
                  :checked="selectedRefs.includes(row.transaction_ref)"
                  @change="toggleSelection(row.transaction_ref, $event.target.checked)"
                />
              </td>
              <td>{{ row.transactionno }}</td>
              <td>{{ row.customercode }} - {{ row.customername || "-" }}</td>
              <td>{{ row.checknumber || "-" }}</td>
              <td>{{ row.checkdate || "-" }}</td>
              <td>{{ row.bankname || "-" }}</td>
              <td class="text-end">{{ row.checkamount }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="form.errors.selected_refs" class="text-danger fs-sm mt-2">{{ form.errors.selected_refs }}</div>
    </BaseBlock>

    <BaseBlock :title="t.clearance_reference" class="mt-4">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.bank_master }}</label>
          <select v-model="form.bankcode" class="form-select">
            <option value="">{{ t.select_bank }}</option>
            <option v-for="option in bankOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.date }}</label>
          <input v-model="form.bank_date" type="date" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.erp_reference_no }}</label>
          <input v-model="form.erpreferencenumber" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.bank_reference_no }}</label>
          <input v-model="form.bankreferenceno" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.remark }}</label>
          <input v-model="form.remark" class="form-control" />
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

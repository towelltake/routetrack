<script setup>
import axios from "axios";
import { computed, reactive, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  detailData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const locale = page.props.locale;

const isView = computed(() => props.mode === "view");
const isEdit = computed(() => props.mode === "edit");
const hasExistingContext = computed(() => !!props.detailData?.context);

const pageError = ref("");
const pageSuccess = ref(page.props.flash?.success ?? "");
const loadingSalesman = ref(false);
const loadingPackage = ref(false);
const addingLine = ref(false);
const savingLine = ref(false);
const deletingLineId = ref(null);
const editingLineId = ref(null);

const header = reactive({
  salesmancode: props.detailData?.context?.salesmancode?.toString?.() ?? "",
  routecode: props.detailData?.context?.routecode?.toString?.() ?? "",
  routename: routeLabel(props.detailData?.context),
  salesmantargetdays: Number(props.detailData?.context?.salesmantargetdays ?? 1),
});

const lineForm = reactive({
  targettype: "",
  fromdate: "",
  todate: "",
  packagenumber: "",
  quantity: "",
  commision: "",
  insentivepercent: "",
  insentive: "",
  is_case: false,
});

const editForm = reactive({
  primary_key: null,
  fromdate: "",
  todate: "",
  quantity: "",
  commision: "",
  insentivepercent: "",
  insentive: "",
});

const lines = ref(props.detailData?.lines ?? []);
const packageAllowsCase = ref(false);

const headingTitle = computed(() => {
  if (props.mode === "create") return t.create_target_commission;
  if (props.mode === "view") return t.target_commission;
  return t.edit_target_commission;
});

const salesmanLocked = computed(() => isEdit.value || isView.value || hasExistingContext.value);
const canAddLine = computed(() => !isView.value && !!header.salesmancode && !!header.routecode);

function routeLabel(record) {
  if (!record) return "";
  return locale === "ar"
    ? (record.arbroutename || record.routename || "")
    : (record.routename || record.arbroutename || "");
}

function packageLabel(record) {
  return locale === "ar"
    ? (record.arbpackagedescription || record.packagedescription || "")
    : (record.packagedescription || record.arbpackagedescription || "");
}

function applyAutoToDate() {
  if (!lineForm.fromdate) {
    lineForm.todate = "";
    return;
  }

  const startDate = new Date(`${lineForm.fromdate}T00:00:00`);
  const targetDays = Number(header.salesmantargetdays || 1);
  const offsetDays = targetDays === 2 ? 32 : 6;
  const endDate = new Date(startDate);
  endDate.setDate(endDate.getDate() + offsetDays);

  if (endDate.getMonth() !== startDate.getMonth()) {
    endDate.setMonth(startDate.getMonth() + 1, 0);
  }

  lineForm.todate = endDate.toISOString().slice(0, 10);
}

async function loadSalesmanMeta() {
  resetAddForm();
  lines.value = [];
  pageError.value = "";

  if (!header.salesmancode) {
    header.routecode = "";
    header.routename = "";
    header.salesmantargetdays = 1;
    return;
  }

  loadingSalesman.value = true;

  try {
    const { data } = await axios.get(`${props.formMeta.salesmanMetaUrl}/${header.salesmancode}`);
    header.routecode = data.route?.routecode?.toString?.() ?? "";
    header.routename = routeLabel(data.route);
    header.salesmantargetdays = Number(data.route?.salesmantargetdays ?? 1);
    lines.value = data.lines ?? [];
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_load_route_information;
  } finally {
    loadingSalesman.value = false;
  }
}

async function handlePackageChange() {
  packageAllowsCase.value = false;
  lineForm.is_case = false;

  if (!lineForm.packagenumber || Number(lineForm.packagenumber) === 1) {
    return;
  }

  loadingPackage.value = true;

  try {
    const { data } = await axios.get(`${props.formMeta.packageStatusUrl}/${lineForm.packagenumber}`);
    packageAllowsCase.value = !!data.allow_case;
  } catch (error) {
    pageError.value = error?.response?.data?.message ?? t.failed_check_package_status;
  } finally {
    loadingPackage.value = false;
  }
}

function handleTargetTypeChange() {
  if ([3, 4, 5].includes(Number(lineForm.targettype))) {
    lineForm.packagenumber = "1";
    packageAllowsCase.value = false;
    lineForm.is_case = false;
  }
}

function resetAddForm() {
  lineForm.targettype = "";
  lineForm.fromdate = "";
  lineForm.todate = "";
  lineForm.packagenumber = "";
  lineForm.quantity = "";
  lineForm.commision = "";
  lineForm.insentivepercent = "";
  lineForm.insentive = "";
  lineForm.is_case = false;
  packageAllowsCase.value = false;
}

async function addLine() {
  pageError.value = "";
  pageSuccess.value = "";

  if (!header.salesmancode || !header.routecode) {
    pageError.value = t.select_salesman_first;
    return;
  }

  addingLine.value = true;

  try {
    const { data } = await axios.post(props.formMeta.lineStoreUrl, {
      salesmancode: header.salesmancode ? Number(header.salesmancode) : null,
      routecode: header.routecode ? Number(header.routecode) : null,
      targettype: lineForm.targettype ? Number(lineForm.targettype) : null,
      fromdate: lineForm.fromdate,
      todate: lineForm.todate,
      packagenumber: lineForm.packagenumber ? Number(lineForm.packagenumber) : null,
      quantity: Number(lineForm.quantity || 0),
      commision: Number(lineForm.commision || 0),
      insentivepercent: Number(lineForm.insentivepercent || 0),
      insentive: Number(lineForm.insentive || 0),
      is_case: lineForm.is_case ? 1 : 0,
    });

    lines.value = data.lines ?? [];
    pageSuccess.value = t.target_line_added_successfully;
    resetAddForm();
  } catch (error) {
    pageError.value = extractError(error, t.failed_add_target_line);
  } finally {
    addingLine.value = false;
  }
}

function startEdit(line) {
  editingLineId.value = line.primary_key;
  editForm.primary_key = line.primary_key;
  editForm.fromdate = line.fromdate;
  editForm.todate = line.todate;
  editForm.quantity = line.quantity;
  editForm.commision = line.commision;
  editForm.insentivepercent = line.insentivepercent;
  editForm.insentive = line.insentive;
}

function cancelEdit() {
  editingLineId.value = null;
  editForm.primary_key = null;
}

async function saveEdit() {
  if (!editForm.primary_key) return;

  pageError.value = "";
  pageSuccess.value = "";
  savingLine.value = true;

  try {
    const { data } = await axios.put(`${props.formMeta.lineUpdateBaseUrl}/${editForm.primary_key}`, {
      fromdate: editForm.fromdate,
      todate: editForm.todate,
      quantity: Number(editForm.quantity || 0),
      commision: Number(editForm.commision || 0),
      insentivepercent: Number(editForm.insentivepercent || 0),
      insentive: Number(editForm.insentive || 0),
    });

    lines.value = data.lines ?? [];
    editingLineId.value = null;
    pageSuccess.value = t.target_line_updated_successfully;
  } catch (error) {
    pageError.value = extractError(error, t.failed_update_target_line);
  } finally {
    savingLine.value = false;
  }
}

async function removeLine(line) {
  if (!window.confirm(t.delete_target_line_confirm)) {
    return;
  }

  deletingLineId.value = line.primary_key;
  pageError.value = "";
  pageSuccess.value = "";

  try {
    const { data } = await axios.delete(`${props.formMeta.lineDestroyBaseUrl}/${line.primary_key}`);
    lines.value = data.lines ?? [];
    pageSuccess.value = t.target_line_deleted_successfully;
  } catch (error) {
    pageError.value = extractError(error, t.failed_delete_target_line);
  } finally {
    deletingLineId.value = null;
  }
}

function extractError(error, fallback) {
  const message = error?.response?.data?.message;
  const validation = error?.response?.data?.errors;
  if (validation) {
    const firstKey = Object.keys(validation)[0];
    if (firstKey && validation[firstKey]?.length) {
      return validation[firstKey][0];
    }
  }
  return message || fallback;
}

function backToIndex() {
  router.get("/inventory/targetcommission");
}
</script>

<template>
  <Head :title="headingTitle" />

  <BasePageHeading :title="headingTitle" :subtitle="t.target_commission_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <Link
          v-if="isView && detailData?.context?.primary_key"
          class="btn btn-alt-primary"
          :href="`/inventory/targetcommission/${detailData.context.primary_key}/edit`"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </Link>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.target_commission">
      <div class="row g-4 mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.salesman }}</label>
          <select v-model="header.salesmancode" class="form-select" :disabled="salesmanLocked || loadingSalesman" @change="loadSalesmanMeta">
            <option value="">{{ t.select_salesman }}</option>
            <option v-for="salesman in lookupOptions.salesmen" :key="salesman.id" :value="String(salesman.id)">
              {{ salesman.label }}
            </option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.route }}</label>
          <input :value="header.routename" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.cycle }}</label>
          <input :value="Number(header.salesmantargetdays) === 2 ? t.monthly : t.weekly" class="form-control" readonly />
        </div>

        <div v-if="pageError" class="col-12">
          <div class="alert alert-danger mb-0">{{ pageError }}</div>
        </div>
        <div v-if="pageSuccess" class="col-12">
          <div class="alert alert-success mb-0">{{ pageSuccess }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.create_target_commission" class="mt-4">
      <div class="row g-4 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.target_on }}</label>
          <select v-model="lineForm.targettype" class="form-select" :disabled="!canAddLine || isView" @change="handleTargetTypeChange">
            <option value="">{{ t.select_target_on }}</option>
            <option v-for="option in lookupOptions.targetTypes" :key="option.id" :value="String(option.id)">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.from_date }}</label>
          <input v-model="lineForm.fromdate" type="date" class="form-control" :disabled="!canAddLine || isView" @change="applyAutoToDate" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.to_date }}</label>
          <input v-model="lineForm.todate" type="date" class="form-control" :disabled="!canAddLine || isView" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.target_group }}</label>
          <select v-model="lineForm.packagenumber" class="form-select" :disabled="!canAddLine || isView || loadingPackage || [3,4,5].includes(Number(lineForm.targettype))" @change="handlePackageChange">
            <option value="">{{ t.select_target_group }}</option>
            <option v-for="option in lookupOptions.packages" :key="option.id" :value="String(option.id)">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.target_value }}</label>
          <input v-model="lineForm.quantity" type="number" step="0.001" min="0" class="form-control" :disabled="!canAddLine || isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.commission }}</label>
          <input v-model="lineForm.commision" type="number" step="0.001" class="form-control" :disabled="!canAddLine || isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.incentive_percent }}</label>
          <input v-model="lineForm.insentivepercent" type="number" step="0.001" class="form-control" :disabled="!canAddLine || isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.incentive }}</label>
          <input v-model="lineForm.insentive" type="number" step="0.001" class="form-control" :disabled="!canAddLine || isView" />
        </div>
        <div class="col-md-2">
          <div class="form-check mt-4 pt-2">
            <input id="is_case" v-model="lineForm.is_case" class="form-check-input" type="checkbox" :disabled="!packageAllowsCase || isView" />
            <label class="form-check-label" for="is_case">{{ t.is_case }}</label>
          </div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" :disabled="!canAddLine || isView || addingLine" @click="addLine">
            <i class="fa fa-plus me-1"></i> {{ addingLine ? t.adding : t.add }}
          </button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.target_lines" class="mt-4">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 130px">{{ t.from_date }}</th>
              <th style="width: 130px">{{ t.to_date }}</th>
              <th>{{ t.target_on }}</th>
              <th>{{ t.group }}</th>
              <th class="text-end" style="width: 110px">{{ t.target_value }}</th>
              <th class="text-end" style="width: 110px">{{ t.commission }}</th>
              <th class="text-end" style="width: 110px">{{ t.incentive_percent }}</th>
              <th class="text-end" style="width: 110px">{{ t.incentive }}</th>
              <th class="text-end" style="width: 120px">{{ t.achieved }}</th>
              <th v-if="!isView" class="text-center" style="width: 120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lines.length">
              <td :colspan="isView ? 9 : 10" class="text-center text-muted py-4">{{ t.no_target_lines }}</td>
            </tr>
            <tr v-for="line in lines" :key="line.primary_key">
              <td>
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.fromdate" type="date" class="form-control form-control-sm" />
                </template>
                <template v-else>{{ line.fromdate }}</template>
              </td>
              <td>
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.todate" type="date" class="form-control form-control-sm" />
                </template>
                <template v-else>{{ line.todate }}</template>
              </td>
              <td>{{ line.targettype_label }}</td>
              <td>{{ packageLabel(line) }}</td>
              <td class="text-end">
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.quantity" type="number" step="0.001" class="form-control form-control-sm text-end" />
                </template>
                <template v-else>{{ line.quantity }}</template>
              </td>
              <td class="text-end">
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.commision" type="number" step="0.001" class="form-control form-control-sm text-end" />
                </template>
                <template v-else>{{ line.commision }}</template>
              </td>
              <td class="text-end">
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.insentivepercent" type="number" step="0.001" class="form-control form-control-sm text-end" />
                </template>
                <template v-else>{{ line.insentivepercent }}</template>
              </td>
              <td class="text-end">
                <template v-if="editingLineId === line.primary_key">
                  <input v-model="editForm.insentive" type="number" step="0.001" class="form-control form-control-sm text-end" />
                </template>
                <template v-else>{{ line.insentive }}</template>
              </td>
              <td class="text-end">{{ line.achieveamount }}</td>
              <td v-if="!isView" class="text-center text-nowrap">
                <template v-if="editingLineId === line.primary_key">
                  <button class="btn btn-sm btn-alt-success me-1" :disabled="savingLine" @click="saveEdit">
                    <i class="fa fa-check"></i>
                  </button>
                  <button class="btn btn-sm btn-alt-secondary" @click="cancelEdit">
                    <i class="fa fa-xmark"></i>
                  </button>
                </template>
                <template v-else>
                  <button class="btn btn-sm btn-alt-secondary me-1" @click="startEdit(line)">
                    <i class="fa fa-pen"></i>
                  </button>
                  <button class="btn btn-sm btn-alt-danger" :disabled="deletingLineId === line.primary_key" @click="removeLine(line)">
                    <i class="fa fa-trash"></i>
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>

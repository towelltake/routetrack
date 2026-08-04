<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  contractData: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();
const isView   = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const isEdit   = computed(() => props.mode === "edit");

const pageTitle = computed(() => {
  if (isCreate.value) return t.create_supervisor_free_contract;
  if (isView.value)   return t.view_supervisor_free_contract;
  return t.edit_supervisor_free_contract;
});
const pageSubtitle = computed(() => t.supervisor_free_contract_note);

// ── Header form ────────────────────────────────────────────────────────────
const headerForm = useForm({
  supervisorcode: props.contractData.supervisorcode ?? "",
  depotcode:      props.contractData.depotcode ?? "",
  startdate:      props.contractData.startdate ?? "",
  enddate:        props.contractData.enddate ?? "",
  active:         props.contractData.active ?? 1,
  remarks:        props.contractData.remarks ?? "",
});

function submitHeader() {
  if (isView.value) return;
  if (isCreate.value) {
    headerForm.post(props.formMeta.baseUrl);
  } else {
    headerForm.put(`${props.formMeta.baseUrl}/${props.contractData.contractid}`);
  }
}

// ── Add-item form ──────────────────────────────────────────────────────────
const selectedItemId  = ref("");
const selectedUpc     = ref("");
const addItemForm = useForm({ itemcode: "", freequantity: "" });

function onItemSelect() {
  const opt = props.formMeta.itemOptions.find((o) => o.id === Number(selectedItemId.value));
  if (opt) {
    addItemForm.itemcode = opt.id;
    selectedUpc.value    = opt.unitspercase ?? "";
  } else {
    addItemForm.itemcode = "";
    selectedUpc.value    = "";
  }
  addItemForm.freequantity = "";
  addItemForm.clearErrors();
}

function submitAddItem() {
  addItemForm.post(`${props.formMeta.baseUrl}/${props.contractData.contractid}/items`, {
    preserveScroll: true,
    onSuccess: () => {
      selectedItemId.value     = "";
      selectedUpc.value        = "";
      addItemForm.itemcode     = "";
      addItemForm.freequantity = "";
      addItemForm.clearErrors();
    },
  });
}

// ── Per-row qty editing ────────────────────────────────────────────────────
const editingItem   = ref(null);   // itemcode being edited
const editQtyValue  = ref("");

function startEditQty(item) {
  editingItem.value  = item.itemcode;
  editQtyValue.value = item.freequantity;
}

function cancelEditQty() {
  editingItem.value  = null;
  editQtyValue.value = "";
}

function saveItemQty(item) {
  router.put(
    `${props.formMeta.baseUrl}/${props.contractData.contractid}/items/${item.itemcode}`,
    { freequantity: Number(editQtyValue.value) },
    {
      preserveScroll: true,
      onSuccess: () => { editingItem.value = null; editQtyValue.value = ""; },
    },
  );
}

// ── Remove item ────────────────────────────────────────────────────────────
const confirmingRemove = ref(null);

function removeItem() {
  router.delete(
    `${props.formMeta.baseUrl}/${props.contractData.contractid}/items/${confirmingRemove.value.itemcode}`,
    { preserveScroll: true, onSuccess: () => { confirmingRemove.value = null; } },
  );
}

// ── Flash ──────────────────────────────────────────────────────────────────
const flash = computed(() => page.props.flash ?? {});

function formatDate(v) {
  if (!v) return "-";
  const d = new Date(v);
  return isNaN(d.getTime()) ? v : d.toLocaleDateString("en-GB");
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="pageSubtitle">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && contractData.contractid && can('supervisor free contract', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${contractData.contractid}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button
          v-if="!isView"
          class="btn btn-primary"
          :disabled="headerForm.processing"
          @click="submitHeader"
        >
          <i class="fa fa-floppy-disk me-1"></i>
          {{ headerForm.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <!-- Flash messages -->
    <div v-if="flash.success" class="alert alert-success alert-dismissible d-flex align-items-center mb-4" role="alert">
      <i class="fa fa-circle-check me-2"></i>
      <div>{{ flash.success }}</div>
    </div>
    <div v-if="flash.error" class="alert alert-danger alert-dismissible d-flex align-items-center mb-4" role="alert">
      <i class="fa fa-circle-exclamation me-2"></i>
      <div>{{ flash.error }}</div>
    </div>

    <!-- ── Contract Header ─────────────────────────────────────────────── -->
    <BaseBlock :title="t.contract_details">
      <div class="row g-4 mb-4">
        <!-- Contract ID -->
        <div class="col-md-2" v-if="!isCreate">
          <label class="form-label">{{ t.contract_id }}</label>
          <input :value="contractData.contractid" class="form-control" readonly />
        </div>

        <!-- Supervisor (locked after creation) -->
        <div class="col-md-4">
          <label class="form-label">
            {{ t.supervisor }} <span v-if="isCreate" class="text-danger">*</span>
          </label>
          <select
            v-model="headerForm.supervisorcode"
            class="form-select"
            :disabled="isView || !isCreate"
          >
            <option value="">{{ t.select_supervisor }}</option>
            <option v-for="sv in formMeta.supervisors" :key="sv.id" :value="sv.id">
              {{ sv.label }}
            </option>
          </select>
          <div v-if="headerForm.errors.supervisorcode" class="text-danger fs-sm mt-1">
            {{ headerForm.errors.supervisorcode }}
          </div>
        </div>

        <!-- Depot (locked after creation) -->
        <div class="col-md-3">
          <label class="form-label">{{ t.depot }}</label>
          <select
            v-model="headerForm.depotcode"
            class="form-select"
            :disabled="isView || !isCreate"
          >
            <option value="">{{ t.select_depot }}</option>
            <option v-for="dp in formMeta.depots" :key="dp.id" :value="dp.id">
              {{ dp.label }}
            </option>
          </select>
        </div>

        <!-- Active -->
        <div class="col-md-3 d-flex align-items-end pb-1">
          <div class="form-check">
            <input
              v-model="headerForm.active"
              :true-value="1"
              :false-value="0"
              type="checkbox"
              class="form-check-input"
              id="chk-active"
              :disabled="isView"
            />
            <label class="form-check-label" for="chk-active">{{ t.active }}</label>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <!-- Start Date -->
        <div class="col-md-3">
          <label class="form-label">{{ t.start_date }} <span class="text-danger">*</span></label>
          <input v-model="headerForm.startdate" type="date" class="form-control" :readonly="isView" />
          <div v-if="headerForm.errors.startdate" class="text-danger fs-sm mt-1">{{ headerForm.errors.startdate }}</div>
        </div>

        <!-- End Date -->
        <div class="col-md-3">
          <label class="form-label">{{ t.end_date }} <span class="text-danger">*</span></label>
          <input v-model="headerForm.enddate" type="date" class="form-control" :readonly="isView" />
          <div v-if="headerForm.errors.enddate" class="text-danger fs-sm mt-1">{{ headerForm.errors.enddate }}</div>
        </div>

        <!-- Remarks -->
        <div class="col-md-6">
          <label class="form-label">{{ t.remarks }}</label>
          <input v-model="headerForm.remarks" class="form-control" :readonly="isView" maxlength="200" />
        </div>
      </div>

      <div v-if="isCreate" class="alert alert-info d-flex align-items-center mt-2 mb-0" role="alert">
        <i class="fa fa-circle-info me-2"></i>
        {{ t.save_contract_first_then_add_free_goods }}
      </div>
    </BaseBlock>

    <!-- ── Items Grid ──────────────────────────────────────────────────── -->
    <BaseBlock v-if="!isCreate" :title="t.free_goods_items">

      <!-- Add Item Row (edit mode only) -->
      <div v-if="isEdit" class="row g-3 mb-4 align-items-end border-bottom pb-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.item }} <span class="text-danger">*</span></label>
          <select v-model="selectedItemId" class="form-select" @change="onItemSelect">
            <option value="">{{ t.select_item }}</option>
            <option v-for="opt in formMeta.itemOptions" :key="opt.id" :value="opt.id">
              {{ opt.label }}
            </option>
          </select>
          <div v-if="addItemForm.errors.itemcode" class="text-danger fs-sm mt-1">
            {{ addItemForm.errors.itemcode }}
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.upc }}</label>
          <input :value="selectedUpc" class="form-control" readonly />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.free_qty }} <span class="text-danger">*</span></label>
          <input
            v-model="addItemForm.freequantity"
            type="number"
            min="1"
            class="form-control"
          />
          <div v-if="addItemForm.errors.freequantity" class="text-danger fs-sm mt-1">
            {{ addItemForm.errors.freequantity }}
          </div>
        </div>
        <div class="col-md-3">
          <button
            class="btn btn-alt-primary w-100"
            :disabled="addItemForm.processing"
            @click="submitAddItem"
          >
            <i class="fa fa-plus me-1"></i>
            {{ addItemForm.processing ? t.adding : t.add_item }}
          </button>
        </div>
      </div>

      <!-- Items Table -->
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm mb-0">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>{{ t.item_code }}</th>
              <th>{{ t.description }}</th>
              <th class="text-center">{{ t.upc }}</th>
              <th class="text-center">{{ t.free_qty }}</th>
              <th class="text-center">{{ t.given_qty }}</th>
              <th class="text-center">{{ t.balance_qty }}</th>
              <th v-if="isEdit" class="text-center" style="width:120px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!contractData.items.length">
              <td :colspan="isEdit ? 8 : 7" class="text-center text-muted py-4">
                {{ t.no_items_added_yet }}
              </td>
            </tr>
            <tr v-for="(item, idx) in contractData.items" :key="item.itemcode">
              <td class="text-muted">{{ idx + 1 }}</td>
              <td>
                <span class="fw-semibold">{{ item.displaycode }}</span>
              </td>
              <td>{{ item.itemshortdescription || "-" }}</td>
              <td class="text-center">{{ item.unitspercase ?? "-" }}</td>

              <!-- Free Qty: inline edit when editing this row -->
              <td class="text-center">
                <template v-if="isEdit && editingItem === item.itemcode">
                  <input
                    v-model="editQtyValue"
                    type="number"
                    :min="item.usedqty || 1"
                    class="form-control form-control-sm text-center"
                    style="width:80px;display:inline-block"
                  />
                </template>
                <template v-else>{{ item.freequantity }}</template>
              </td>

              <td class="text-center">{{ item.usedqty }}</td>
              <td class="text-center">{{ item.balanceqty }}</td>

              <!-- Actions (edit mode) -->
              <td v-if="isEdit" class="text-center text-nowrap">
                <template v-if="editingItem === item.itemcode">
                  <button class="btn btn-sm btn-alt-success me-1" @click="saveItemQty(item)" :title="t.save">
                    <i class="fa fa-check"></i>
                  </button>
                  <button class="btn btn-sm btn-alt-secondary" @click="cancelEditQty" :title="t.cancel">
                    <i class="fa fa-xmark"></i>
                  </button>
                </template>
                <template v-else>
                  <button class="btn btn-sm btn-alt-secondary me-1" @click="startEditQty(item)" :title="t.edit_qty">
                    <i class="fa fa-pen"></i>
                  </button>
                  <button
                    class="btn btn-sm btn-alt-danger"
                    :disabled="!item.can_delete"
                    :title="item.can_delete ? t.remove_item : t.cannot_remove_item_already_issued"
                    @click="item.can_delete && (confirmingRemove = item)"
                  >
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

  <!-- Remove item confirm modal -->
  <div v-if="confirmingRemove" class="modal fade show d-block" style="background:rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> {{ t.remove_item }}</h5>
          <button class="btn-close" @click="confirmingRemove = null"></button>
        </div>
        <div class="modal-body">
          {{ t.remove_item_from_contract.replace(':item', confirmingRemove.displaycode) }}
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingRemove = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="removeItem">{{ t.remove }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

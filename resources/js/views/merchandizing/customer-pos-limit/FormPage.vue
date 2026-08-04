<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  limitData: { type: Object, required: true },
});

const { can } = usePermissions();
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_customer_pos_limit;
  return isView.value ? t.view_customer_pos_limit : t.edit_customer_pos_limit;
});

const form = useForm({
  primary_key: props.limitData.primary_key ?? null,
  customercode: props.limitData.customercode ?? "",
  poslimit: props.limitData.poslimit ?? "",
});

function syncFormFromProps() {
  form.defaults({
    primary_key: props.limitData.primary_key ?? null,
    customercode: props.limitData.customercode ?? "",
    poslimit: props.limitData.poslimit ?? "",
  });
  form.reset();
  form.clearErrors();
}

const rows = computed(() => props.limitData.items ?? []);
const detailForm = useForm({
  itemcode: "",
  quantity: "",
  serialnumber: "",
});
const detailEditForm = useForm({
  itemcode: "",
  quantity: "",
  serialnumber: "",
});
const editingDetailId = ref(null);

const selectedItem = computed(() => props.formMeta.itemOptions.find((option) => option.id === Number(detailForm.itemcode)) ?? null);

watch(
  () => [props.mode, props.limitData],
  () => {
    syncFormFromProps();
  },
  { immediate: true, deep: true },
);

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.primary_key}`);
}

function addDetail() {
  detailForm.post(`${props.formMeta.baseUrl}/${form.primary_key}/details`, {
    preserveScroll: true,
    onSuccess: () => {
      detailForm.reset();
    },
  });
}

function startEdit(detail) {
  editingDetailId.value = detail.table_pk;
  detailEditForm.itemcode = detail.itemcode;
  detailEditForm.quantity = detail.quantity ?? "";
  detailEditForm.serialnumber = detail.serialnumber ?? "";
  detailEditForm.clearErrors();
}

function cancelEdit() {
  editingDetailId.value = null;
  detailEditForm.reset();
  detailEditForm.clearErrors();
}

function saveDetail(detail) {
  detailEditForm.put(`${props.formMeta.baseUrl}/${form.primary_key}/details/${detail.table_pk}`, {
    preserveScroll: true,
    onSuccess: () => {
      cancelEdit();
    },
  });
}

function removeDetail(detail) {
  if (!window.confirm(t.remove_pos_item_confirm.replace(':item', detail.itemdescription))) {
    return;
  }

  router.delete(`${props.formMeta.baseUrl}/${form.primary_key}/details/${detail.table_pk}`, {
    preserveScroll: true,
  });
}

function inventoryLabel(type) {
  return Number(type) === 1 ? t.inventory : t.value_label;
}

function errorFor(field) {
  return form.errors[field];
}

function detailError(field) {
  return detailForm.errors[field] ?? detailForm.errors[`detail.${field}`];
}

function detailEditError(detail, field) {
  return detailEditForm.errors[field] ?? detailEditForm.errors[`detail_edit.${detail.table_pk}.${field}`];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.customer_pos_limit_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('customer pos limit', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${limitData.primary_key}/edit`)"
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
    <BaseBlock :title="t.customer_pos_limit">
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <label class="form-label">{{ t.customer }} <span class="text-danger">*</span></label>
          <select v-model="form.customercode" class="form-select" :disabled="!isCreate || isView">
            <option value="">{{ t.select }}</option>
            <option v-for="option in formMeta.customerOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('customercode')" class="text-danger fs-sm mt-1">{{ errorFor("customercode") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.pos_limit }} <span class="text-danger">*</span></label>
          <input v-model="form.poslimit" type="number" min="1" class="form-control" :readonly="isView" />
          <div v-if="errorFor('poslimit')" class="text-danger fs-sm mt-1">{{ errorFor("poslimit") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.pos_balance }}</label>
          <input :value="limitData.posbalance ?? ''" class="form-control" readonly />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.assigned_pos">
      <div v-if="isCreate" class="alert alert-info mb-0">
        {{ t.save_customer_pos_limit_first_then_add_pos_items }}
      </div>

      <template v-else>
        <div v-if="!isView" class="border rounded p-3 mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">{{ t.pos }} <span class="text-danger">*</span></label>
              <select v-model="detailForm.itemcode" class="form-select">
                <option value="">{{ t.select }}</option>
                <option v-for="option in formMeta.itemOptions" :key="option.id" :value="option.id">
                  {{ option.label }}
                </option>
              </select>
              <div v-if="detailError('itemcode')" class="text-danger fs-sm mt-1">{{ detailError("itemcode") }}</div>
            </div>
            <div v-if="selectedItem && selectedItem.inventorytype === 0" class="col-md-3">
              <label class="form-label">{{ t.quantity }} <span class="text-danger">*</span></label>
              <input v-model="detailForm.quantity" type="number" min="1" class="form-control" />
              <div v-if="detailError('quantity')" class="text-danger fs-sm mt-1">{{ detailError("quantity") }}</div>
            </div>
            <div v-else-if="selectedItem" class="col-md-3">
              <label class="form-label">{{ t.serial_number }} <span class="text-danger">*</span></label>
              <input v-model="detailForm.serialnumber" maxlength="20" class="form-control" />
              <div v-if="detailError('serialnumber')" class="text-danger fs-sm mt-1">{{ detailError("serialnumber") }}</div>
            </div>
            <div v-if="selectedItem" class="col-md-2">
              <label class="form-label">{{ t.inventory_type }}</label>
              <input :value="inventoryLabel(selectedItem.inventorytype)" class="form-control" readonly />
            </div>
            <div class="col-md-2">
              <button class="btn btn-primary w-100" :disabled="detailForm.processing || !detailForm.itemcode" @click="addDetail">
                <i class="fa fa-plus me-1"></i> {{ t.add }}
              </button>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-vcenter fs-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t.pos_code }}</th>
                <th>{{ t.description }}</th>
                <th>{{ t.inventory_type }}</th>
                <th>{{ t.quantity }}</th>
                <th>{{ t.serial_number }}</th>
                <th v-if="!isView" class="text-center" style="width:140px">{{ t.actions }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!rows.length">
                <td :colspan="isView ? 6 : 7" class="text-center text-muted py-4">{{ t.no_pos_items_assigned }}</td>
              </tr>
              <tr v-for="(detail, index) in rows" :key="detail.table_pk">
                <td>{{ index + 1 }}</td>
                <td>{{ detail.itemcode }}</td>
                <td>{{ detail.itemdescription }}</td>
                <td>{{ inventoryLabel(detail.inventorytype) }}</td>
                <td>
                  <template v-if="editingDetailId === detail.table_pk && detail.inventorytype === 0">
                    <input v-model="detailEditForm.quantity" type="number" min="1" class="form-control form-control-sm" />
                    <div v-if="detailEditError(detail, 'quantity')" class="text-danger fs-sm mt-1">{{ detailEditError(detail, "quantity") }}</div>
                  </template>
                  <template v-else>
                    {{ detail.quantity ?? "-" }}
                  </template>
                </td>
                <td>
                  <template v-if="editingDetailId === detail.table_pk && detail.inventorytype === 1">
                    <input v-model="detailEditForm.serialnumber" maxlength="20" class="form-control form-control-sm" />
                    <div v-if="detailEditError(detail, 'serialnumber')" class="text-danger fs-sm mt-1">{{ detailEditError(detail, "serialnumber") }}</div>
                  </template>
                  <template v-else>
                    {{ detail.serialnumber || "-" }}
                  </template>
                </td>
                <td v-if="!isView" class="text-center text-nowrap">
                  <template v-if="editingDetailId === detail.table_pk">
                    <button class="btn btn-sm btn-alt-success me-1" :disabled="detailEditForm.processing" @click="saveDetail(detail)">
                      <i class="fa fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-alt-secondary" @click="cancelEdit">
                      <i class="fa fa-times"></i>
                    </button>
                  </template>
                  <template v-else>
                    <button v-if="can('customer pos limit', 'edit')" class="btn btn-sm btn-alt-secondary me-1" @click="startEdit(detail)">
                      <i class="fa fa-pen"></i>
                    </button>
                    <button v-if="can('customer pos limit', 'delete')" class="btn btn-sm btn-alt-danger" @click="removeDetail(detail)">
                      <i class="fa fa-trash"></i>
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  groupMeta: { type: Object, required: true },
  groupData: { type: Object, required: true },
  itemOptions: { type: Array, required: true },
  assignedItems: { type: Array, default: () => [] },
  workflowMeta: { type: Object, required: true },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() => (isCreate.value ? t.create_loyalty_group : isView.value ? t.view_loyalty_group : t.edit_loyalty_group));
const pageSubtitle = computed(() => t.loyalty_group_note);
const detailsTitle = computed(() => t.loyalty_group_details);

const form = useForm({
  groupnumber: props.groupData.groupnumber ?? "",
  groupdescription: props.groupData.groupdescription ?? "",
  arbgroupdescription: props.groupData.arbgroupdescription ?? "",
  items: props.assignedItems.map((item) => ({
    itemcode: Number(item.itemcode),
    displaycode: item.displaycode,
    itemshortdescription: item.itemshortdescription,
    itemqty: props.workflowMeta.showItemQuantity ? Number(item.itemqty ?? 0) : 0,
  })),
});

const selectedItemCode = ref("");
const itemQuantity = ref("");

const itemMap = computed(() => {
  const map = {};
  for (const option of props.itemOptions) {
    map[option.id] = option;
  }
  return map;
});

function addItem() {
  const itemcode = Number(selectedItemCode.value);
  const option = itemMap.value[itemcode];

  if (!itemcode || !option) {
    form.setError("items", t.item_code_required);
    return;
  }

  const existing = form.items.find((item) => Number(item.itemcode) === itemcode);
  if (existing) {
    form.setError("items", t.item_code_already_added);
    return;
  }

  form.clearErrors("items");
  form.items.push({
    itemcode,
    displaycode: option.code,
    itemshortdescription: option.description,
    itemqty: props.workflowMeta.showItemQuantity ? Number(itemQuantity.value || 0) : 0,
  });

  selectedItemCode.value = "";
  itemQuantity.value = "";
}

function removeItem(index) {
  form.items.splice(index, 1);
}

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post(props.groupMeta.baseUrl);
    return;
  }

  form.put(`${props.groupMeta.baseUrl}/${form.groupnumber}`);
}

function errorFor(field) {
  return form.errors[field];
}

function formatQuantity(value) {
  const number = Number(value ?? 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(2);
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="pageSubtitle">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(groupMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can(groupMeta.permission, 'edit')"
          class="btn btn-primary"
          @click="router.get(`${groupMeta.baseUrl}/${form.groupnumber}/edit`)"
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
    <BaseBlock :title="detailsTitle">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.group_number }}</label>
          <input v-model="form.groupnumber" class="form-control" readonly />
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.groupdescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('groupdescription')" class="text-danger fs-sm mt-1">{{ errorFor("groupdescription") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbgroupdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbgroupdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbgroupdescription") }}</div>
        </div>
      </div>

      <div v-if="!isView" class="row g-4 align-items-end mb-4">
        <div class="col-md-7">
          <label class="form-label">{{ t.item_code }}</label>
          <select v-model="selectedItemCode" class="form-select">
            <option value="">{{ t.select }}</option>
            <option v-for="option in itemOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div v-if="workflowMeta.showItemQuantity" class="col-md-3">
          <label class="form-label">{{ t.quantity }}</label>
          <input v-model="itemQuantity" type="number" min="0" step="0.01" class="form-control" />
        </div>
        <div :class="workflowMeta.showItemQuantity ? 'col-md-2' : 'col-md-5'">
          <button class="btn btn-alt-primary w-100" @click="addItem">
            <i class="fa fa-plus me-1"></i> {{ t.add }}
          </button>
        </div>
        <div v-if="errorFor('items')" class="col-12 text-danger fs-sm">{{ errorFor("items") }}</div>
      </div>

      <div v-if="groupData.createddate" class="mb-3 text-muted fs-sm">
        {{ t.created_date }}: {{ groupData.createddate }}
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 80px">#</th>
              <th>{{ t.item_code }}</th>
              <th>{{ t.item_description }}</th>
              <th v-if="workflowMeta.showItemQuantity" class="text-center" style="width: 150px">{{ t.item_quantity }}</th>
              <th v-if="!isView" class="text-center" style="width: 100px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td :colspan="workflowMeta.showItemQuantity ? (isView ? 4 : 5) : (isView ? 3 : 4)" class="text-center text-muted py-4">
                {{ t.no_items_assigned }}
              </td>
            </tr>
            <tr v-for="(item, index) in form.items" :key="item.itemcode">
              <td class="text-muted">{{ index + 1 }}</td>
              <td class="fw-semibold">{{ item.displaycode }}</td>
              <td>{{ item.itemshortdescription }}</td>
              <td v-if="workflowMeta.showItemQuantity" class="text-center">
                <input
                  v-if="!isView"
                  v-model="item.itemqty"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control form-control-sm text-center"
                />
                <span v-else>{{ formatQuantity(item.itemqty) }}</span>
              </td>
              <td v-if="!isView" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeItem(index)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>

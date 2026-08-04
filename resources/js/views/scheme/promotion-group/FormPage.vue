<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  groupMeta: { type: Object, required: true },
  groupData: { type: Object, required: true },
  itemGroupOptions: { type: Array, required: true },
  assignedItems: { type: Array, default: () => [] },
  itemGroupItemsUrl: { type: String, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const { can } = usePermissions();
const t = usePage().props.translations.ui;
const pageTitle = computed(() => {
  const prefix = isCreate.value ? "create" : isView.value ? "view" : "edit";
  return props.groupMeta.permission === "qualification group"
    ? t[`${prefix}_qualification_group`]
    : t[`${prefix}_assignment_group`];
});
const pageSubtitle = computed(() =>
  props.groupMeta.permission === "qualification group" ? t.qualification_group_note : t.assignment_group_note,
);
const detailsTitle = computed(() =>
  props.groupMeta.permission === "qualification group" ? t.qualification_group_details : t.assignment_group_details,
);
const usedByLabel = computed(() =>
  props.groupMeta.permission === "qualification group" ? t.qualified_items : t.assigned_items,
);

const form = useForm({
  groupnumber: props.groupData.groupnumber ?? "",
  groupdescription: props.groupData.groupdescription ?? "",
  arbgroupdescription: props.groupData.arbgroupdescription ?? "",
  itemcodes: props.groupData.itemcodes?.length ? [...props.groupData.itemcodes] : props.assignedItems.map((item) => item.id),
});

const selectedItemGroup = ref(null);
const availableItems = ref([]);
const selectedItemsToAdd = ref([]);
const assignedList = ref(
  props.assignedItems.map((item) => ({
    id: Number(item.id),
    label: item.label,
  })),
);
const loadingItems = ref(false);

watch(
  assignedList,
  (items) => {
    form.itemcodes = items.map((item) => Number(item.id));
  },
  { deep: true, immediate: true },
);

watch(selectedItemGroup, async (value) => {
  selectedItemsToAdd.value = [];

  if (!value) {
    availableItems.value = [];
    return;
  }

  loadingItems.value = true;

  try {
    const response = await fetch(`${props.itemGroupItemsUrl}?item_group=${value}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error("Failed to load items");
    }

    const payload = await response.json();
    availableItems.value = Array.isArray(payload) ? payload.map((item) => ({ id: Number(item.id), label: item.label })) : [];
  } catch (error) {
    availableItems.value = [];
  } finally {
    loadingItems.value = false;
  }
});

const availableItemsFiltered = computed(() => {
  const assignedIds = new Set(assignedList.value.map((item) => Number(item.id)));

  return availableItems.value.filter((item) => !assignedIds.has(Number(item.id)));
});

function addItems() {
  if (!selectedItemsToAdd.value.length) {
    return;
  }

  const map = new Map(assignedList.value.map((item) => [Number(item.id), item]));
  for (const itemId of selectedItemsToAdd.value) {
    const match = availableItems.value.find((entry) => Number(entry.id) === Number(itemId));
    if (match) {
      map.set(Number(match.id), match);
    }
  }

  assignedList.value = Array.from(map.values()).sort((left, right) => left.label.localeCompare(right.label));
  selectedItemsToAdd.value = [];
}

function removeItem(itemId) {
  assignedList.value = assignedList.value.filter((item) => Number(item.id) !== Number(itemId));
}

function clearItems() {
  assignedList.value = [];
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
          <label class="form-label">{{ t.group_description }} <span class="text-danger">*</span></label>
          <input v-model="form.groupdescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('groupdescription')" class="text-danger fs-sm mt-1">{{ errorFor("groupdescription") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbgroupdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbgroupdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbgroupdescription") }}</div>
        </div>
      </div>

      <div v-if="!isView" class="border rounded p-3 bg-body-light mb-4">
        <div class="row g-4 align-items-end">
          <div class="col-md-4">
            <label class="form-label">{{ t.item_group }}</label>
            <select v-model="selectedItemGroup" class="form-select">
              <option :value="null">{{ t.select }}</option>
              <option v-for="option in itemGroupOptions" :key="option.id" :value="option.id">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ t.items }}</label>
            <select v-model="selectedItemsToAdd" class="form-select" multiple size="8" :disabled="!selectedItemGroup || loadingItems">
              <option v-for="item in availableItemsFiltered" :key="item.id" :value="item.id">
                {{ item.label }}
              </option>
            </select>
            <div class="form-text" v-if="loadingItems">{{ t.loading_items }}</div>
            <div class="form-text" v-else>{{ t.select_items_from_group }}</div>
          </div>
          <div class="col-md-2">
            <button class="btn btn-alt-primary w-100" :disabled="!selectedItemsToAdd.length" @click="addItems">
              <i class="fa fa-plus me-1"></i> {{ t.add }}
            </button>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ usedByLabel }}</h5>
        <button v-if="!isView && assignedList.length" class="btn btn-sm btn-alt-danger" @click="clearItems">
          <i class="fa fa-trash me-1"></i> {{ t.delete_all }}
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 80px">#</th>
              <th>{{ t.item }}</th>
              <th v-if="!isView" class="text-center" style="width: 120px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!assignedList.length">
              <td :colspan="isView ? 2 : 3" class="text-center text-muted py-4">{{ t.no_items_assigned }}</td>
            </tr>
            <tr v-for="(item, index) in assignedList" :key="item.id">
              <td class="text-muted">{{ index + 1 }}</td>
              <td>{{ item.label }}</td>
              <td v-if="!isView" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeItem(item.id)">
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

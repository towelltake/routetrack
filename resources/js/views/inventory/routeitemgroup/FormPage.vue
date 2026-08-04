<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  routeItemGroupData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  formMeta: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() =>
  isCreate.value ? t.create_route_item_group : isView.value ? t.route_item_group : t.edit_route_item_group,
);

const form = useForm({
  routeitemgrpcode: props.routeItemGroupData.routeitemgrpcode ?? "",
  description: props.routeItemGroupData.description ?? "",
  itemgroupcode: String(props.routeItemGroupData.itemgroupcode ?? 0),
  transferstatus: props.routeItemGroupData.transferstatus ?? 1,
  items: (props.lookupOptions.selectedItems ?? []).map((item) => item.id),
});

const selectedItems = ref([...(props.lookupOptions.selectedItems ?? [])]);
const sourceItems = ref([]);
const activeAvailable = ref([]);
const activeSelected = ref([]);
const loadingItems = ref(false);
const itemLoadError = ref("");

const availableItems = computed(() => {
  const selectedIds = new Set(selectedItems.value.map((item) => item.id));
  return sourceItems.value.filter((item) => !selectedIds.has(item.id));
});

watch(() => form.itemgroupcode, () => {
  loadItems();
});

async function loadItems() {
  loadingItems.value = true;
  itemLoadError.value = "";

  try {
    const params = new URLSearchParams({
      item_group: String(form.itemgroupcode || 0),
    });

    const response = await fetch(`${props.formMeta.itemOptionsUrl}?${params.toString()}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(t.failed_to_load_items);
    }

    const payload = await response.json();
    sourceItems.value = payload.items ?? [];
  } catch (error) {
    itemLoadError.value = error instanceof Error ? error.message : t.failed_to_load_items;
  } finally {
    loadingItems.value = false;
  }
}

onMounted(() => {
  loadItems();
});

function moveSelectedToRight() {
  const moving = availableItems.value.filter((item) => activeAvailable.value.includes(item.id));
  if (!moving.length) return;
  selectedItems.value = [...selectedItems.value, ...moving];
  form.items = selectedItems.value.map((item) => item.id);
  activeAvailable.value = [];
}

function moveAllToRight() {
  selectedItems.value = [...selectedItems.value, ...availableItems.value];
  form.items = selectedItems.value.map((item) => item.id);
  activeAvailable.value = [];
}

function moveSelectedToLeft() {
  if (!activeSelected.value.length) return;
  const ids = new Set(activeSelected.value);
  selectedItems.value = selectedItems.value.filter((item) => !ids.has(item.id));
  form.items = selectedItems.value.map((item) => item.id);
  activeSelected.value = [];
}

function moveAllToLeft() {
  selectedItems.value = [];
  form.items = [];
  activeSelected.value = [];
}

function submit() {
  if (isView.value) {
    return;
  }

  form.items = selectedItems.value.map((item) => item.id);

  if (isCreate.value) {
    form.post("/inventory/routeitemgroup");
    return;
  }

  form.put(`/inventory/routeitemgroup/${form.routeitemgrpcode}`);
}

function backToIndex() {
  router.get("/inventory/routeitemgroup");
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.route_item_group_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back ?? "Back" }}
        </button>
        <button
          v-if="isView && can('route item group', 'edit')"
          class="btn btn-primary"
          @click="router.get(`/inventory/routeitemgroup/${form.routeitemgrpcode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit ?? "Edit" }}
        </button>
        <button
          v-else-if="!isView"
          class="btn btn-primary"
          :disabled="form.processing"
          @click="submit"
        >
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? (t.saving ?? "Saving...") : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.route_item_group">
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.routeitemgrpcode" class="form-control" readonly />
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.descriptions }} <span class="text-danger">*</span></label>
          <input v-model="form.description" class="form-control" :readonly="isView" />
          <div v-if="form.errors.description" class="text-danger fs-sm mt-1">{{ form.errors.description }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.transferstatus" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.statusOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.transferstatus" class="text-danger fs-sm mt-1">{{ form.errors.transferstatus }}</div>
        </div>

        <div class="col-md-5">
          <label class="form-label">{{ t.item_group }}</label>
          <select v-model="form.itemgroupcode" class="form-select" :disabled="isView || loadingItems">
            <option v-for="option in lookupOptions.itemGroups" :key="option.id" :value="String(option.id)">
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.itemgroupcode" class="text-danger fs-sm mt-1">{{ form.errors.itemgroupcode }}</div>
        </div>

        <div class="col-12">
          <h5 class="mb-0">{{ t.item_selection }}</h5>
          <p class="text-muted fs-sm mb-0">{{ t.item_selection_note }}</p>
        </div>

        <div v-if="itemLoadError" class="col-12">
          <div class="alert alert-danger mb-0">{{ itemLoadError }}</div>
        </div>

        <div class="col-lg-5">
          <label class="form-label">{{ t.available_items }}</label>
          <select
            v-model="activeAvailable"
            class="form-select"
            multiple
            size="15"
            :disabled="isView || loadingItems"
            @dblclick="moveSelectedToRight"
          >
            <option v-for="item in availableItems" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
        </div>

        <div class="col-lg-2">
          <div class="d-grid gap-2 h-100 align-content-center pt-lg-4">
            <button class="btn btn-alt-secondary" :disabled="isView || loadingItems || !availableItems.length" @click="moveSelectedToRight">
              &gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="isView || loadingItems || !availableItems.length" @click="moveAllToRight">
              &gt;&gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="isView || loadingItems || !selectedItems.length" @click="moveSelectedToLeft">
              &lt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="isView || loadingItems || !selectedItems.length" @click="moveAllToLeft">
              &lt;&lt;
            </button>
          </div>
        </div>

        <div class="col-lg-5">
          <label class="form-label">{{ t.selected_items }}</label>
          <select
            v-model="activeSelected"
            class="form-select"
            multiple
            size="15"
            :disabled="isView || loadingItems"
            @dblclick="moveSelectedToLeft"
          >
            <option v-for="item in selectedItems" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
          <div v-if="!selectedItems.length" class="text-muted fs-sm mt-2">{{ t.no_selected_items }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

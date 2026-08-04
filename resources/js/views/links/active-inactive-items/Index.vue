<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  available: { type: Boolean, default: false },
  formMeta: { type: Object, required: true },
  optionSets: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;

const form = ref({
  item_group: "",
});

const allItems = ref([]);
const inactiveItems = ref([]);
const activeAvailable = ref([]);
const activeSelected = ref([]);
const loading = ref(false);
const errorMessage = ref("");

const itemGroupOptions = computed(() => props.optionSets?.itemGroupOptions ?? []);
const availableItems = computed(() => {
  const inactiveIds = new Set(inactiveItems.value.map((item) => item.id));
  return allItems.value.filter((item) => !inactiveIds.has(item.id));
});
const canSave = computed(() => can(props.formMeta.permission, "edit"));

async function loadData() {
  errorMessage.value = "";

  if (form.value.item_group === "") {
    errorMessage.value = t.item_group_required;
    return;
  }

  loading.value = true;

  try {
    const params = new URLSearchParams({
      item_group: String(form.value.item_group),
    });

    const response = await fetch(`${props.formMeta.loadUrl}?${params.toString()}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) {
      throw new Error(t.unable_to_load_item_data);
    }

    const payload = await response.json();
    allItems.value = payload.items ?? [];
    inactiveItems.value = payload.inactiveItems ?? [];
    activeAvailable.value = [];
    activeSelected.value = [];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : t.unable_to_load_item_data;
  } finally {
    loading.value = false;
  }
}

function moveSelectedToRight() {
  const moving = availableItems.value.filter((item) => activeAvailable.value.includes(item.id));
  if (!moving.length) return;
  inactiveItems.value = [...inactiveItems.value, ...moving];
  activeAvailable.value = [];
}

function moveAllToRight() {
  inactiveItems.value = [...allItems.value];
  activeAvailable.value = [];
}

function moveSelectedToLeft() {
  if (!activeSelected.value.length) return;
  const ids = new Set(activeSelected.value);
  inactiveItems.value = inactiveItems.value.filter((item) => !ids.has(item.id));
  activeSelected.value = [];
}

function moveAllToLeft() {
  inactiveItems.value = [];
  activeSelected.value = [];
}

function saveLinks() {
  errorMessage.value = "";

  if (form.value.item_group === "") {
    errorMessage.value = t.item_group_required;
    return;
  }

  router.post(
    props.formMeta.saveUrl,
    {
      item_group: form.value.item_group,
      items: inactiveItems.value.map((item) => item.id),
    },
    {
      preserveScroll: true,
    },
  );
}

function resetPage() {
  router.get(props.formMeta.indexUrl);
}
</script>

<template>
  <Head :title="t.active_in_active_items" />

  <BasePageHeading :title="t.active_in_active_items" :subtitle="t.active_in_active_items_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="resetPage">
          <i class="fa fa-rotate-left me-1"></i> {{ t.cancel }}
        </button>
        <button v-if="canSave" class="btn btn-primary" :disabled="loading" @click="saveLinks">
          <i class="fa fa-floppy-disk me-1"></i> {{ t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <div v-if="!available" class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="fa fa-triangle-exclamation me-2"></i>
      <div>{{ t.legacy_active_inactive_items_link_required }}</div>
    </div>

    <div v-if="errorMessage" class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="fa fa-circle-exclamation me-2"></i>
      <div>{{ errorMessage }}</div>
    </div>

    <BaseBlock :title="t.active_in_active_items">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">{{ t.item_group }}</label>
          <select v-model="form.item_group" class="form-select" :disabled="!available || loading">
            <option value="">{{ t.select_placeholder }}</option>
            <option v-for="option in itemGroupOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
        </div>

        <div class="col-lg-2 d-grid">
          <button class="btn btn-alt-primary" :disabled="!available || loading" @click="loadData">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            {{ t.load_data }}
          </button>
        </div>
      </div>

      <div class="row g-3 pt-4 mb-3">
        <div class="col-lg-5">
          <label class="form-label">{{ t.active_item }}</label>
          <select
            v-model="activeAvailable"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToRight"
          >
            <option v-for="item in availableItems" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
        </div>

        <div class="col-lg-2">
          <div class="d-grid gap-2 h-100 align-content-center pt-lg-4">
            <button class="btn btn-alt-secondary" :disabled="loading || !availableItems.length" @click="moveSelectedToRight">
              &gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !availableItems.length" @click="moveAllToRight">
              &gt;&gt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !inactiveItems.length" @click="moveSelectedToLeft">
              &lt;
            </button>
            <button class="btn btn-alt-secondary" :disabled="loading || !inactiveItems.length" @click="moveAllToLeft">
              &lt;&lt;
            </button>
          </div>
        </div>

        <div class="col-lg-5">
          <label class="form-label">{{ t.non_active_item }}</label>
          <select
            v-model="activeSelected"
            class="form-select"
            multiple
            size="15"
            :disabled="loading"
            @dblclick="moveSelectedToLeft"
          >
            <option v-for="item in inactiveItems" :key="item.id" :value="item.id">
              {{ item.label }}
            </option>
          </select>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  posData: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_pos_master;
  return isView.value ? t.view_pos_master : t.edit_pos_master;
});

const form = useForm({
  itemcode: props.posData.itemcode ?? "",
  alternatecode: props.posData.alternatecode ?? "",
  itemdescription: props.posData.itemdescription ?? "",
  arbitemdescription: props.posData.arbitemdescription ?? "",
  itemvalue: props.posData.itemvalue ?? "0.0000",
  inventorytype: props.posData.inventorytype ?? 0,
  activestatus: props.posData.activestatus ?? 1,
});

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.itemcode}`);
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="formMeta.subtitle">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button v-if="!isView" class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.pos_master">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.pos_code }}</label>
          <input v-model="form.itemcode" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activestatus" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.inventory_type }}</label>
          <select v-model="form.inventorytype" class="form-select" :disabled="isView">
            <option v-for="option in formMeta.inventoryTypeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('inventorytype')" class="text-danger fs-sm mt-1">{{ errorFor("inventorytype") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.item_value }}</label>
          <input v-model="form.itemvalue" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('itemvalue')" class="text-danger fs-sm mt-1">{{ errorFor("itemvalue") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-4">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input v-model="form.alternatecode" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('alternatecode')" class="text-danger fs-sm mt-1">{{ errorFor("alternatecode") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.itemdescription" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('itemdescription')" class="text-danger fs-sm mt-1">{{ errorFor("itemdescription") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbitemdescription" maxlength="50" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbitemdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbitemdescription") }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  taxData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() =>
  isCreate.value ? `${t.create} ${t.tax}` : isView.value ? `${t.view} ${t.tax}` : `${t.edit} ${t.tax}`,
);

const form = useForm({
  taxcode: props.taxData.taxcode ?? "",
  taxdescription: props.taxData.taxdescription ?? "",
  arbtaxdescription: props.taxData.arbtaxdescription ?? "",
  taxtype: props.taxData.taxtype ?? 1,
  taxpercentage: props.taxData.taxpercentage ?? 0,
  taxbase: props.taxData.taxbase ?? 1,
  cdat: props.taxData.cdat ?? null,
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/account/tax");
    return;
  }

  form.put(`/account/tax/${form.taxcode}`);
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.tax_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/tax')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/account/tax/${form.taxcode}/edit`)"
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
    <BaseBlock :title="t.tax_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.taxcode" class="form-control" readonly />
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.taxdescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('taxdescription')" class="text-danger fs-sm mt-1">{{ errorFor("taxdescription") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbtaxdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbtaxdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbtaxdescription") }}</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.tax_type }} <span class="text-danger">*</span></label>
          <select v-model="form.taxtype" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.taxTypeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('taxtype')" class="text-danger fs-sm mt-1">{{ errorFor("taxtype") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.tax_percentage }} <span class="text-danger">*</span></label>
          <input v-model="form.taxpercentage" type="number" step="0.0001" class="form-control" :readonly="isView" />
          <div v-if="errorFor('taxpercentage')" class="text-danger fs-sm mt-1">{{ errorFor("taxpercentage") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.tax_base }} <span class="text-danger">*</span></label>
          <select v-model="form.taxbase" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.taxBaseOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('taxbase')" class="text-danger fs-sm mt-1">{{ errorFor("taxbase") }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  surveyData: { type: Object, required: true },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const isLookupField = computed(() => Number(form.surveyrectype) === 7);
const isNumericField = computed(() => Number(form.surveyrectype) === 1);
const needsResponseLength = computed(() => Number(form.surveyrectype) === 1 || Number(form.surveyrectype) === 2);
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_survey_definition;
  return isView.value ? t.view_survey_definition : t.edit_survey_definition;
});

const form = useForm({
  surveydefkey: props.surveyData.surveydefkey ?? "",
  surveyindex: props.surveyData.surveyindex ?? "",
  surveyprompt: props.surveyData.surveyprompt ?? "",
  arbsurveyprompt: props.surveyData.arbsurveyprompt ?? "",
  surveyrectype: props.surveyData.surveyrectype ?? "",
  responselength: props.surveyData.responselength ?? 0,
  responsedecimalpos: props.surveyData.responsedecimalpos ?? 0,
  lookuptype: props.surveyData.lookuptype ?? "",
  lookupindex: props.surveyData.lookupindex ?? 0,
  activestatus: props.surveyData.activestatus ?? 1,
});

const surveyTypes = computed(() => props.formMeta.surveyTypes ?? {});
const lookupTypes = computed(() => props.formMeta.lookupTypes ?? {});

function onSurveyTypeChange() {
  if (!needsResponseLength.value) {
    form.responselength = 0;
  }
  if (!isNumericField.value) {
    form.responsedecimalpos = 0;
  }
  if (!isLookupField.value) {
    form.lookuptype = "";
    form.lookupindex = 0;
  }
}

function onLookupTypeChange() {
  if (Number(form.lookuptype) !== 0) {
    form.lookupindex = 0;
  }
}

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.surveydefkey}`);
}

function managerQuery(lookupindex = form.lookupindex || 0) {
  return {
    return_mode: isCreate.value ? "create" : "edit",
    return_id: isCreate.value ? undefined : form.surveydefkey,
    surveyindex: form.surveyindex || 0,
    surveyprompt: form.surveyprompt || "",
    arbsurveyprompt: form.arbsurveyprompt || "",
    surveyrectype: form.surveyrectype || "",
    responselength: form.responselength || 0,
    responsedecimalpos: form.responsedecimalpos || 0,
    lookuptype: form.lookuptype ?? "",
    lookupindex: lookupindex || 0,
    activestatus: form.activestatus ?? 1,
  };
}

function openLookupCreate() {
  router.get("/merchandizing/survey/lookup-index/create", managerQuery(0));
}

function openLookupEdit() {
  if (!form.lookupindex) return;
  router.get(`/merchandizing/survey/lookup-index/${form.lookupindex}/edit`, managerQuery(form.lookupindex));
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
        <button
          v-if="isView && can('survey', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.surveydefkey}/edit`)"
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
    <BaseBlock :title="t.survey_definition_details">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.surveydefkey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.survey_order_index }} <span class="text-danger">*</span></label>
          <input v-model="form.surveyindex" type="number" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('surveyindex')" class="text-danger fs-sm mt-1">{{ errorFor("surveyindex") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.survey_prompt }} <span class="text-danger">*</span></label>
          <input v-model="form.surveyprompt" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('surveyprompt')" class="text-danger fs-sm mt-1">{{ errorFor("surveyprompt") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <label class="form-label">{{ t.survey_prompt_arabic }}</label>
          <input v-model="form.arbsurveyprompt" maxlength="200" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbsurveyprompt')" class="text-danger fs-sm mt-1">{{ errorFor("arbsurveyprompt") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.survey_type }} <span class="text-danger">*</span></label>
          <select v-model="form.surveyrectype" class="form-select" :disabled="isView" @change="onSurveyTypeChange">
            <option value="">{{ t.select }}</option>
            <option v-for="(label, key) in surveyTypes" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
          <div v-if="errorFor('surveyrectype')" class="text-danger fs-sm mt-1">{{ errorFor("surveyrectype") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activestatus" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.response_length }}</label>
          <input
            v-model="form.responselength"
            type="number"
            min="0"
            max="5"
            class="form-control"
            :readonly="isView || !needsResponseLength"
          />
          <div v-if="errorFor('responselength')" class="text-danger fs-sm mt-1">{{ errorFor("responselength") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.response_decimal }}</label>
          <input
            v-model="form.responsedecimalpos"
            type="number"
            min="0"
            max="3"
            class="form-control"
            :readonly="isView || !isNumericField"
          />
          <div v-if="errorFor('responsedecimalpos')" class="text-danger fs-sm mt-1">{{ errorFor("responsedecimalpos") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.lookup_type }}</label>
          <select v-model="form.lookuptype" class="form-select" :disabled="isView || !isLookupField" @change="onLookupTypeChange">
            <option value="">{{ t.select }}</option>
            <option v-for="(label, key) in lookupTypes" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
          <div v-if="errorFor('lookuptype')" class="text-danger fs-sm mt-1">{{ errorFor("lookuptype") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.lookup_index }}</label>
          <input v-model="form.lookupindex" class="form-control" readonly />
          <div v-if="errorFor('lookupindex')" class="text-danger fs-sm mt-1">{{ errorFor("lookupindex") }}</div>
        </div>
      </div>

      <div v-if="isLookupField && !isView" class="border rounded p-3 bg-body-light">
        <div class="row g-3 align-items-end">
          <div class="col-md-6 text-muted fs-sm">
            {{ t.normal_lookup_help }}
          </div>
          <div class="col-md-3">
            <button class="btn btn-alt-primary w-100" :disabled="Number(form.lookuptype) !== 0" @click="openLookupCreate">
              <i class="fa fa-plus me-1"></i> {{ t.add_lookup }}
            </button>
          </div>
          <div class="col-md-3">
            <button class="btn btn-alt-secondary w-100" :disabled="Number(form.lookuptype) !== 0 || !form.lookupindex" @click="openLookupEdit">
              <i class="fa fa-pen me-1"></i> {{ t.edit_lookup }}
            </button>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

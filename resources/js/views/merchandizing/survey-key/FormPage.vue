<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  keyData: { type: Object, required: true },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_survey_key;
  return isView.value ? t.view_survey_key : t.edit_survey_key;
});

const form = useForm({
  surveykey: props.keyData.surveykey ?? "",
  surveydescription: props.keyData.surveydescription ?? "",
  arbsurveydescription: props.keyData.arbsurveydescription ?? "",
  surveyplankey: props.keyData.surveyplankey ?? "",
  activestatus: props.keyData.activestatus ?? 1,
});

const rows = computed(() => props.keyData.items ?? []);

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.surveykey}`);
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.survey_key_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('survey key', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.surveykey}/edit`)"
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
    <BaseBlock :title="t.survey_key">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.survey_key }}</label>
          <input v-model="form.surveykey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activestatus" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.surveydescription" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('surveydescription')" class="text-danger fs-sm mt-1">{{ errorFor("surveydescription") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbsurveydescription" maxlength="200" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbsurveydescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbsurveydescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.survey_plan }} <span class="text-danger">*</span></label>
          <select v-model="form.surveyplankey" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="option in formMeta.surveyPlanOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('surveyplankey')" class="text-danger fs-sm mt-1">{{ errorFor("surveyplankey") }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.survey_snapshot">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.sequence }}</th>
              <th>{{ t.mandatory }}</th>
              <th>{{ t.survey_prompt }}</th>
              <th>{{ t.arabic_prompt }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">
                {{ isCreate ? t.save_survey_key_to_generate_snapshot : t.no_survey_snapshot_found }}
              </td>
            </tr>
            <tr v-for="(item, index) in rows" :key="`${item.surveyindex}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ item.surveysequencenumber ?? "-" }}</td>
              <td>{{ item.surveymandatory ? t.yes : t.no }}</td>
              <td>{{ item.surveydescription }}</td>
              <td>{{ item.arbsurveydescription || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>
</template>

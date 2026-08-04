<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  planData: { type: Object, required: true },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_survey_plan;
  return isView.value ? t.view_survey_plan : t.edit_survey_plan;
});

const form = useForm({
  surveyplankey: props.planData.surveyplankey ?? "",
  surveysequencenumber: props.planData.surveysequencenumber ?? 0,
  surveymandatory: props.planData.surveymandatory ?? 0,
  surveydescription: props.planData.surveydescription ?? "",
  arbsurveydescription: props.planData.arbsurveydescription ?? "",
  remarks: props.planData.remarks ?? "",
  items: props.planData.items ?? [],
});

const definitionMap = computed(() => {
  const map = {};
  for (const option of props.formMeta.surveyDefinitionOptions ?? []) {
    map[option.id] = option;
  }
  return map;
});

const draftDefinition = ref("");

function addItem() {
  if (!draftDefinition.value) {
    form.setError("items", t.survey_definition_required);
    return;
  }

  const option = definitionMap.value[draftDefinition.value];
  if (!option) return;

  const duplicate = form.items.some((item) => Number(item.surveydefkey) === Number(option.id));
  if (duplicate) {
    form.setError("items", t.survey_definition_already_added);
    return;
  }

  form.clearErrors("items");
  form.items.push({
    surveydefkey: Number(option.id),
    surveyindex: option.surveyindex,
    surveyprompt: option.surveyprompt,
    label: option.label,
  });
  draftDefinition.value = "";
}

function removeItem(index) {
  form.items.splice(index, 1);
}

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.surveyplankey}`);
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.survey_plan_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('survey plan', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.surveyplankey}/edit`)"
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
    <BaseBlock :title="t.survey_plan">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.plan_key }}</label>
          <input v-model="form.surveyplankey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.sequence_number }} <span class="text-danger">*</span></label>
          <input v-model="form.surveysequencenumber" type="number" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('surveysequencenumber')" class="text-danger fs-sm mt-1">{{ errorFor("surveysequencenumber") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.mandatory }}</label>
          <select v-model="form.surveymandatory" class="form-select" :disabled="isView">
            <option :value="0">{{ t.no }}</option>
            <option :value="1">{{ t.yes }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.remarks }}</label>
          <input v-model="form.remarks" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('remarks')" class="text-danger fs-sm mt-1">{{ errorFor("remarks") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.surveydescription" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('surveydescription')" class="text-danger fs-sm mt-1">{{ errorFor("surveydescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbsurveydescription" maxlength="200" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbsurveydescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbsurveydescription") }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.survey_definitions">
      <div v-if="!isView" class="row g-4 align-items-end mb-4">
        <div class="col-md-10">
          <label class="form-label">{{ t.survey_definition }} <span class="text-danger">*</span></label>
          <select v-model="draftDefinition" class="form-select">
            <option value="">{{ t.select }}</option>
            <option v-for="option in formMeta.surveyDefinitionOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('items')" class="text-danger fs-sm mt-1">{{ errorFor("items") }}</div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-alt-primary w-100" @click="addItem">
            <i class="fa fa-plus me-1"></i> {{ t.add }}
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.survey_index }}</th>
              <th>{{ t.survey_prompt }}</th>
              <th>{{ t.survey_definition_key }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td :colspan="isView ? 4 : 5" class="text-center text-muted py-4">{{ t.no_survey_definitions_added }}</td>
            </tr>
            <tr v-for="(item, index) in form.items" :key="`${item.surveydefkey}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ item.surveyindex ?? "-" }}</td>
              <td>{{ item.surveyprompt || item.label }}</td>
              <td>{{ item.surveydefkey }}</td>
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

<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  planData: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const { can } = usePermissions();
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => (isCreate.value ? t.create_loyalty_plan : isView.value ? t.view_loyalty_plan : t.edit_loyalty_plan));
const pageSubtitle = computed(() => t.loyalty_plan_note);
const types = computed(() => props.formMeta.optionSets?.types ?? {});
const groupMap = computed(() => {
  const map = {};
  for (const option of props.formMeta.qualificationGroups ?? []) {
    map[option.id] = option;
  }
  return map;
});

const form = useForm({
  loyaltyplanid: props.planData.loyaltyplanid ?? "",
  description: props.planData.description ?? "",
  arbdescription: props.planData.arbdescription ?? "",
  active: props.planData.active ?? 1,
  remarks: props.planData.remarks ?? "",
  items: props.planData.items ?? [],
});

const draftGroup = ref("");
const draft = useForm({
  qualificationgroup: "",
  qualificationlabel: "",
  type: 0,
  value: "",
  points: "",
});

function syncFormFromProps() {
  form.defaults({
    loyaltyplanid: props.planData.loyaltyplanid ?? "",
    description: props.planData.description ?? "",
    arbdescription: props.planData.arbdescription ?? "",
    active: props.planData.active ?? 1,
    remarks: props.planData.remarks ?? "",
    items: props.planData.items ?? [],
  });
  form.reset();
  form.clearErrors();
}

watch(
  () => [props.mode, props.planData],
  () => {
    syncFormFromProps();
    resetDraft();
  },
  { immediate: true, deep: true },
);

function resetDraft() {
  draft.defaults({
    qualificationgroup: "",
    qualificationlabel: "",
    type: 0,
    value: "",
    points: "",
  });
  draft.reset();
  draft.clearErrors();
  draftGroup.value = "";
}

function applySelectedGroup() {
  const option = groupMap.value[draftGroup.value];
  if (!option) {
    resetDraft();
    return;
  }

  draft.qualificationgroup = Number(option.id);
  draft.qualificationlabel = option.label;
}

function addItem() {
  if (!draft.qualificationgroup && draft.qualificationgroup !== 0) {
    draft.setError("qualificationgroup", t.qualification_group_required);
    return;
  }

  const duplicate = form.items.some(
    (item) => Number(item.qualificationgroup) === Number(draft.qualificationgroup) && Number(item.type) === Number(draft.type),
  );

  if (duplicate) {
    draft.setError("qualificationgroup", t.qualification_group_type_already_added);
    return;
  }

  form.items.push({
    qualificationgroup: Number(draft.qualificationgroup),
    qualificationlabel: draft.qualificationlabel,
    type: Number(draft.type),
    value: draft.value,
    points: draft.points,
  });

  resetDraft();
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

  form.put(`${props.formMeta.baseUrl}/${form.loyaltyplanid}`);
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
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('loyalty plan', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.loyaltyplanid}/edit`)"
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
    <BaseBlock :title="t.loyalty_plan">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.plan_key }}</label>
          <input v-model="form.loyaltyplanid" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.active" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.description" class="form-control" :readonly="isView" />
          <div v-if="errorFor('description')" class="text-danger fs-sm mt-1">{{ errorFor("description") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbdescription" class="form-control" dir="rtl" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.remarks }}</label>
          <input v-model="form.remarks" class="form-control" :readonly="isView" />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="!isCreate || isView" :title="t.details">
      <div v-if="!isView" class="row g-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.qualification_group }} <span class="text-danger">*</span></label>
          <select v-model="draftGroup" class="form-select" @change="applySelectedGroup">
            <option value="">{{ t.select }}</option>
            <option v-for="option in formMeta.qualificationGroups" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="draft.errors.qualificationgroup" class="text-danger fs-sm mt-1">{{ draft.errors.qualificationgroup }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.type }}</label>
          <select v-model="draft.type" class="form-select">
            <option v-for="(label, key) in types" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.amount_qty }}</label>
          <input v-model="draft.value" class="form-control" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.points }}</label>
          <input v-model="draft.points" class="form-control" />
        </div>
      </div>

      <div v-if="!isView" class="d-flex justify-content-center pt-4">
        <button class="btn btn-alt-primary px-4" @click="addItem">
          <i class="fa fa-plus me-1"></i> {{ t.add }}
        </button>
      </div>

      <div class="table-responsive pt-4">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.qualification_group }}</th>
              <th>{{ t.type }}</th>
              <th>{{ t.amount_qty }}</th>
              <th>{{ t.points }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td :colspan="isView ? 5 : 6" class="text-center text-muted py-4">{{ t.no_qualification_rules_added }}</td>
            </tr>
            <tr v-for="(item, index) in form.items" :key="`${item.qualificationgroup}-${item.type}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ item.qualificationlabel || item.qualificationgroup }}</td>
              <td>{{ types[item.type] || item.type }}</td>
              <td><input v-model="item.value" class="form-control form-control-sm" :readonly="isView" /></td>
              <td><input v-model="item.points" class="form-control form-control-sm" :readonly="isView" /></td>
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

    <BaseBlock v-else :title="t.details">
      <div class="text-muted fs-sm">
        {{ t.save_loyalty_plan_first_then_reopen_add_rules }}
      </div>
    </BaseBlock>
  </div>
</template>

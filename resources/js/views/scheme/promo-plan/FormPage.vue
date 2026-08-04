<script setup>
import { computed, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  planData: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const { can } = usePermissions();
const t = usePage().props.translations.ui;

const pageTitle = computed(() => (isCreate.value ? t.create_promo_plan : isView.value ? t.view_promo_plan : t.edit_promo_plan));
const pageSubtitle = computed(() => t.promo_plan_note);

const form = useForm({
  plannumber: props.planData.plannumber ?? "",
  plandescription: props.planData.plandescription ?? "",
  arbplandescription: props.planData.arbplandescription ?? "",
  promotiontypecode: props.planData.promotiontypecode ?? "",
  rangebasis: props.planData.rangebasis ?? "",
  amountbasis: props.planData.amountbasis ?? "",
  exclusionoption: props.planData.exclusionoption ?? 0,
  qualificationgroup: props.planData.qualificationgroup ?? "",
  assignmentgroup: props.planData.assignmentgroup ?? "",
  assignmentnumber: props.planData.assignmentnumber ?? "",
  activeindicator: props.planData.activeindicator ?? 1,
  iscase: !!props.planData.iscase,
  onetimeuse: !!props.planData.onetimeuse,
  repeatrange: !!props.planData.repeatrange,
  enforcepromotion: !!props.planData.enforcepromotion,
  casepromotion: !!props.planData.casepromotion,
  ranges: props.planData.ranges?.length
    ? props.planData.ranges.map((range) => ({
        rangelow: range.rangelow ?? "",
        rangehigh: range.rangehigh ?? "",
        repeatingrange: Number(range.repeatingrange ?? 0),
        promotionamount: range.promotionamount ?? "",
      }))
    : [{ rangelow: "", rangehigh: "", repeatingrange: 0, promotionamount: "" }],
});

const promotionTypes = computed(() => props.formMeta.optionSets?.promotionTypes ?? {});
const amountBasis = computed(() => props.formMeta.optionSets?.amountBasis ?? {});
const exclusionOptions = computed(() => props.formMeta.optionSets?.exclusionOptions ?? {});
const qualificationGroups = computed(() => props.formMeta.qualificationGroups ?? []);
const assignmentGroups = computed(() => props.formMeta.assignmentGroups ?? []);
const assignmentValueOptions = computed(() =>
  assignmentGroups.value.filter((option) => Number(option.id) !== 1),
);

const rangeBasisOptions = computed(() => {
  const options = [
    { id: 0, label: t.no_qualification_default },
    { id: 1, label: t.qualification_on_quantity },
    { id: 2, label: t.qualification_on_amount },
  ];

  if (Number(form.promotiontypecode) === 7 && props.formMeta.optionSets?.fixedQualificationOptionEnabled) {
    options.push({ id: 3, label: t.fixed_qualification_fixed_assignment });
  }

  if (Number(form.promotiontypecode) === 7 && props.formMeta.optionSets?.rangedFixedAssignmentOptionEnabled) {
    options.push({ id: 4, label: t.ranged_qualification_fixed_assignment });
  }

  return options;
});

const usesAssignmentValue = computed(() => Number(form.promotiontypecode) === 0);
const showRanges = computed(() => Number(form.rangebasis) !== 3);
const canEditRanges = computed(() => !isView.value && props.formMeta.supportsRanges && showRanges.value);

watch(
  () => form.promotiontypecode,
  (value) => {
    if (Number(value) === 0) {
      form.assignmentgroup = "";
    }

    if (Number(value) !== 7) {
      form.casepromotion = false;
      if (Number(form.rangebasis) === 3 || Number(form.rangebasis) === 4) {
        form.rangebasis = 1;
      }
    }
  },
);

watch(
  () => form.rangebasis,
  (value) => {
    if (Number(value) === 3) {
      form.ranges = [{ rangelow: "", rangehigh: "", repeatingrange: 0, promotionamount: "" }];
    }
  },
);

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }

  form.put(`${props.formMeta.baseUrl}/${form.plannumber}`);
}

function addRange() {
  form.ranges.push({
    rangelow: "",
    rangehigh: "",
    repeatingrange: 0,
    promotionamount: "",
  });
}

function removeRange(index) {
  if (form.ranges.length === 1) {
    form.ranges[0] = {
      rangelow: "",
      rangehigh: "",
      repeatingrange: 0,
      promotionamount: "",
    };
    return;
  }

  form.ranges.splice(index, 1);
}

function errorFor(field) {
  return form.errors[field];
}

function assignmentValueLabel(value) {
  const matched = assignmentValueOptions.value.find((option) => Number(option.id) === Number(value));
  return matched?.label ?? value ?? "-";
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
          v-if="isView && can('promo plan', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.plannumber}/edit`)"
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
    <BaseBlock :title="t.promo_plan">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.plan_number }}</label>
          <input v-model="form.plannumber" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.promotion_type }} <span class="text-danger">*</span></label>
          <select v-model="form.promotiontypecode" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="(label, key) in promotionTypes" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
          <div v-if="errorFor('promotiontypecode')" class="text-danger fs-sm mt-1">{{ errorFor("promotiontypecode") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activeindicator" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.plandescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('plandescription')" class="text-danger fs-sm mt-1">{{ errorFor("plandescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbplandescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbplandescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbplandescription") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.range_basis }}</label>
          <select v-model="form.rangebasis" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="option in rangeBasisOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="errorFor('rangebasis')" class="text-danger fs-sm mt-1">{{ errorFor("rangebasis") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.amount_basis }}</label>
          <select v-model="form.amountbasis" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="(label, key) in amountBasis" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
          <div v-if="errorFor('amountbasis')" class="text-danger fs-sm mt-1">{{ errorFor("amountbasis") }}</div>
        </div>
        <div class="col-md-4 d-none">
          <label class="form-label">{{ t.exclusion_option }}</label>
          <select v-model="form.exclusionoption" class="form-select" :disabled="isView">
            <option v-for="(label, key) in exclusionOptions" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <label class="form-label">{{ t.qualification_group }} <span class="text-danger">*</span></label>
          <select v-model="form.qualificationgroup" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="option in qualificationGroups" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="errorFor('qualificationgroup')" class="text-danger fs-sm mt-1">{{ errorFor("qualificationgroup") }}</div>
        </div>
        <div v-if="!usesAssignmentValue" class="col-md-6">
          <label class="form-label">{{ t.assignment_group }} <span class="text-danger">*</span></label>
          <select v-model="form.assignmentgroup" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="option in assignmentGroups" :key="option.id" :value="option.id">{{ option.label }}</option>
          </select>
          <div v-if="errorFor('assignmentgroup')" class="text-danger fs-sm mt-1">{{ errorFor("assignmentgroup") }}</div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-3">
          <div class="form-check mt-2">
            <input id="enforcepromotion" v-model="form.enforcepromotion" class="form-check-input" type="checkbox" :disabled="isView" />
            <label class="form-check-label" for="enforcepromotion">{{ t.enforce_promotion }}</label>
          </div>
        </div>
        <div v-if="Number(form.promotiontypecode) === 7" class="col-md-3">
          <div class="form-check mt-2">
            <input id="casepromotion" v-model="form.casepromotion" class="form-check-input" type="checkbox" :disabled="isView" />
            <label class="form-check-label" for="casepromotion">{{ t.case_promotion }}</label>
          </div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="showRanges" :title="t.details">
      <template #options>
        <button v-if="canEditRanges" class="btn btn-sm btn-primary" @click="addRange">
          <i class="fa fa-plus me-1"></i> {{ t.add }}
        </button>
      </template>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.range_low }}</th>
              <th>{{ t.range_high }}</th>
              <th>{{ t.range_repeat }}</th>
              <th>{{ usesAssignmentValue ? t.assignment : t.promo_value }}</th>
              <th v-if="canEditRanges" class="text-center" style="width: 100px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(range, index) in form.ranges" :key="index">
              <td class="text-muted">{{ index + 1 }}</td>
              <td>
                <input v-model="range.rangelow" type="number" step="0.01" min="0" class="form-control form-control-sm" :readonly="isView" />
                <div v-if="errorFor(`ranges.${index}.rangelow`)" class="text-danger fs-sm mt-1">{{ errorFor(`ranges.${index}.rangelow`) }}</div>
              </td>
              <td>
                <input v-model="range.rangehigh" type="number" step="0.01" min="0" class="form-control form-control-sm" :readonly="isView || Number(range.repeatingrange) === 1" />
                <div v-if="errorFor(`ranges.${index}.rangehigh`)" class="text-danger fs-sm mt-1">{{ errorFor(`ranges.${index}.rangehigh`) }}</div>
              </td>
              <td>
                <select v-model="range.repeatingrange" class="form-select form-select-sm" :disabled="isView">
                  <option :value="0">{{ t.repeat_no }}</option>
                  <option :value="1">{{ t.repeat_yes }}</option>
                </select>
                <div v-if="errorFor(`ranges.${index}.repeatingrange`)" class="text-danger fs-sm mt-1">{{ errorFor(`ranges.${index}.repeatingrange`) }}</div>
              </td>
              <td>
                <template v-if="usesAssignmentValue">
                  <select v-if="!isView" v-model="range.promotionamount" class="form-select form-select-sm">
                    <option value="">{{ t.select }}</option>
                    <option v-for="option in assignmentValueOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                  </select>
                  <div v-else class="form-control-plaintext">{{ assignmentValueLabel(range.promotionamount) }}</div>
                </template>
                <template v-else>
                  <input v-model="range.promotionamount" type="number" step="0.0001" min="0" class="form-control form-control-sm" :readonly="isView" />
                </template>
                <div v-if="errorFor(`ranges.${index}.promotionamount`)" class="text-danger fs-sm mt-1">{{ errorFor(`ranges.${index}.promotionamount`) }}</div>
              </td>
              <td v-if="canEditRanges" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeRange(index)">
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

<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  keyData: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const { can } = usePermissions();
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => (isCreate.value ? t.create_pricing_key : isView.value ? t.view_pricing_key : t.edit_pricing_key));
const pageSubtitle = computed(() => t.pricing_key_note);

const form = useForm({
  pricingplankey: props.keyData.pricingplankey ?? "",
  description: props.keyData.description ?? "",
  arbdescription: props.keyData.arbdescription ?? "",
  activeindicator: props.keyData.activeindicator ?? 1,
  selected_plan_ids: [],
  assigned_plans: props.keyData.assignedPlans?.map((plan) => ({
    primary_key: plan.primary_key,
    customerpricingkey: plan.customerpricingkey,
    description: plan.description,
    arbdescription: plan.arbdescription,
    startdate: plan.startdate,
    enddate: plan.enddate,
    contractno: plan.contractno,
    active: plan.active,
  })) ?? [],
});

const selectedPlanIds = ref([]);

function submit() {
  if (isView.value) return;

  form.selected_plan_ids = selectedPlanIds.value;

  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }

  form.transform((data) => ({
    ...data,
    _method: "put",
  })).post(`${props.formMeta.baseUrl}/${form.pricingplankey}`);
}

function addSelectedPlans() {
  const selected = new Set(selectedPlanIds.value.map((id) => Number(id)));
  const today = new Date();
  const todayValue = today.toISOString().slice(0, 10);
  const nextYear = new Date(today);
  nextYear.setFullYear(nextYear.getFullYear() + 1);
  const nextYearValue = nextYear.toISOString().slice(0, 10);

  for (const option of props.formMeta.availablePlans ?? []) {
    if (!selected.has(Number(option.id))) {
      continue;
    }

    const exists = form.assigned_plans.some((plan) => Number(plan.customerpricingkey) === Number(option.id));
    if (exists) {
      continue;
    }

    form.assigned_plans.push({
      primary_key: null,
      customerpricingkey: Number(option.id),
      description: option.description,
      arbdescription: option.arbdescription,
      startdate: option.startdate || todayValue,
      enddate: option.enddate || nextYearValue,
      contractno: option.contractno || "",
      active: option.active ?? 1,
    });
  }

  selectedPlanIds.value = [];
}

function removeAssignedPlan(index) {
  form.assigned_plans.splice(index, 1);
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
          v-if="isView && can('pricing key', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.pricingplankey}/edit`)"
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
    <BaseBlock :title="t.pricing_key">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.pricing_key }}</label>
          <input v-model="form.pricingplankey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activeindicator" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
          <div v-if="errorFor('activeindicator')" class="text-danger fs-sm mt-1">{{ errorFor("activeindicator") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.description" class="form-control" :readonly="isView" />
          <div v-if="errorFor('description')" class="text-danger fs-sm mt-1">{{ errorFor("description") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbdescription") }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.plan_selection">
      <div v-if="!formMeta.supportsPlans" class="alert alert-warning d-flex align-items-center mb-0" role="alert">
        <i class="fa fa-triangle-exclamation me-2"></i>
        <div><code>pricingplanheader1</code> {{ t.pricingplanheader1_required_for_plan_selection_workflow }}</div>
      </div>

      <div v-else-if="isCreate" class="text-muted fs-sm">
        {{ t.save_pricing_key_first_then_reopen_add_plans }}
      </div>

      <template v-else>
        <div class="table-responsive">
          <table class="table table-hover table-vcenter fs-sm">
            <thead>
              <tr>
                <th style="width: 60px"></th>
                <th>{{ t.plan_number }}</th>
                <th>{{ t.description }}</th>
                <th>{{ t.arabic_description }}</th>
                <th>{{ t.type }}</th>
                <th>{{ t.status }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!(formMeta.availablePlans?.length)">
                <td colspan="6" class="text-center text-muted py-4">{{ t.no_pricing_plans_available }}</td>
              </tr>
              <tr v-for="plan in formMeta.availablePlans" :key="plan.id">
                <td>
                  <input
                    v-model="selectedPlanIds"
                    class="form-check-input"
                    type="checkbox"
                    :value="plan.id"
                    :disabled="isView"
                  />
                </td>
                <td>{{ plan.id }}</td>
                <td>{{ plan.description || "-" }}</td>
                <td>{{ plan.arbdescription || "-" }}</td>
                <td>{{ plan.typeLabel || "-" }}</td>
                <td>{{ plan.active ? t.status_active : t.status_inactive }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!isView" class="d-flex justify-content-center pt-3">
          <button class="btn btn-alt-primary px-4" :disabled="!selectedPlanIds.length" @click="addSelectedPlans">
            <i class="fa fa-plus me-1"></i> {{ t.add }}
          </button>
        </div>
      </template>
    </BaseBlock>

    <BaseBlock :title="t.pricing_key">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.plan_number }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.arabic_description }}</th>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.contract_no }}</th>
              <th>{{ t.status }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.assigned_plans.length">
              <td :colspan="isView ? 8 : 9" class="text-center text-muted py-4">{{ t.no_pricing_plans_assigned }}</td>
            </tr>
            <tr v-for="(plan, index) in form.assigned_plans" :key="`${plan.customerpricingkey}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ plan.customerpricingkey }}</td>
              <td>{{ plan.description || "-" }}</td>
              <td>{{ plan.arbdescription || "-" }}</td>
              <td>
                <input v-model="plan.startdate" type="date" class="form-control form-control-sm" :readonly="isView" />
                <div v-if="errorFor(`assigned_plans.${index}.startdate`)" class="text-danger fs-sm mt-1">
                  {{ errorFor(`assigned_plans.${index}.startdate`) }}
                </div>
              </td>
              <td>
                <input v-model="plan.enddate" type="date" class="form-control form-control-sm" :readonly="isView" />
                <div v-if="errorFor(`assigned_plans.${index}.enddate`)" class="text-danger fs-sm mt-1">
                  {{ errorFor(`assigned_plans.${index}.enddate`) }}
                </div>
              </td>
              <td>{{ plan.contractno || "-" }}</td>
              <td>
                <span class="badge" :class="plan.active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ plan.active ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td v-if="!isView" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeAssignedPlan(index)">
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

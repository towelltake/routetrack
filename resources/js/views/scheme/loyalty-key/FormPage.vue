<script setup>
import { computed, ref, watch } from "vue";
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
const pageTitle = computed(() => (isCreate.value ? t.create_loyalty_key : isView.value ? t.view_loyalty_key : t.edit_loyalty_key));
const pageSubtitle = computed(() => t.loyalty_key_note);

const form = useForm({
  loyaltykeyid: props.keyData.loyaltykeyid ?? "",
  description: props.keyData.description ?? "",
  arabicdescription: props.keyData.arabicdescription ?? "",
  active: props.keyData.active ?? 1,
  remarks: props.keyData.remarks ?? "",
  selected_plan_ids: [],
  assigned_plans: props.keyData.assignedPlans?.map((plan) => ({
    primarykey: plan.primarykey,
    loyaltyplanid: plan.loyaltyplanid,
    description: plan.description,
    arbdescription: plan.arbdescription,
    startdate: plan.startdate,
    enddate: plan.enddate,
    active: plan.active,
  })) ?? [],
});

const selectedPlanIds = ref([]);

function syncFormFromProps() {
  form.defaults({
    loyaltykeyid: props.keyData.loyaltykeyid ?? "",
    description: props.keyData.description ?? "",
    arabicdescription: props.keyData.arabicdescription ?? "",
    active: props.keyData.active ?? 1,
    remarks: props.keyData.remarks ?? "",
    selected_plan_ids: [],
    assigned_plans: props.keyData.assignedPlans?.map((plan) => ({
      primarykey: plan.primarykey,
      loyaltyplanid: plan.loyaltyplanid,
      description: plan.description,
      arbdescription: plan.arbdescription,
      startdate: plan.startdate,
      enddate: plan.enddate,
      active: plan.active,
    })) ?? [],
  });
  form.reset();
  form.clearErrors();
  selectedPlanIds.value = [];
}

watch(
  () => [props.mode, props.keyData],
  () => {
    syncFormFromProps();
  },
  { immediate: true, deep: true },
);

function submit() {
  if (isView.value) return;

  form.selected_plan_ids = selectedPlanIds.value;

  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }

  form.put(`${props.formMeta.baseUrl}/${form.loyaltykeyid}`);
}

function addSelectedPlans() {
  const selected = new Set(selectedPlanIds.value.map((id) => Number(id)));

  for (const option of props.formMeta.availablePlans ?? []) {
    if (!selected.has(Number(option.id))) {
      continue;
    }

    const exists = form.assigned_plans.some((plan) => Number(plan.loyaltyplanid) === Number(option.id));
    if (exists) {
      continue;
    }

    form.assigned_plans.push({
      primarykey: null,
      loyaltyplanid: Number(option.id),
      description: option.description,
      arbdescription: option.arbdescription,
      startdate: new Date().toISOString().slice(0, 10),
      enddate: new Date().toISOString().slice(0, 10),
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
          v-if="isView && can('loyalty key', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.loyaltykeyid}/edit`)"
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
    <BaseBlock :title="t.loyalty_key">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.loyalty_key }}</label>
          <input v-model="form.loyaltykeyid" class="form-control" readonly />
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
          <input v-model="form.arabicdescription" class="form-control" dir="rtl" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.remarks }}</label>
          <input v-model="form.remarks" class="form-control" :readonly="isView" />
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.plan_selection">
      <div v-if="!formMeta.supportsPlans" class="alert alert-warning d-flex align-items-center mb-0" role="alert">
        <i class="fa fa-triangle-exclamation me-2"></i>
        <div><code>loyaltyplanheader</code> {{ t.loyaltyplanheader_required_for_plan_selection_workflow }}</div>
      </div>

      <div v-else-if="isCreate" class="text-muted fs-sm">
        {{ t.save_loyalty_key_first_then_reopen_add_plans }}
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
                <th>{{ t.status }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!(formMeta.availablePlans?.length)">
                <td colspan="5" class="text-center text-muted py-4">{{ t.no_loyalty_plans_available }}</td>
              </tr>
              <tr v-for="plan in formMeta.availablePlans" :key="plan.id">
                <td>
                  <input v-model="selectedPlanIds" class="form-check-input" type="checkbox" :value="plan.id" :disabled="isView" />
                </td>
                <td>{{ plan.id }}</td>
                <td>{{ plan.description || "-" }}</td>
                <td>{{ plan.arbdescription || "-" }}</td>
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

    <BaseBlock :title="t.plans">
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
              <th>{{ t.status }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.assigned_plans.length">
              <td :colspan="isView ? 7 : 8" class="text-center text-muted py-4">{{ t.no_loyalty_plans_assigned }}</td>
            </tr>
            <tr v-for="(plan, index) in form.assigned_plans" :key="`${plan.loyaltyplanid}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ plan.loyaltyplanid }}</td>
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

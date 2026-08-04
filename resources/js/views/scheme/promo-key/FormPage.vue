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
const isCreate = computed(() => props.mode === "create");
const { can } = usePermissions();
const t = usePage().props.translations.ui;
const pageTitle = computed(() => (isCreate.value ? t.create_promo_key : isView.value ? t.view_promo_key : t.edit_promo_key));
const pageSubtitle = computed(() => t.promo_key_note);

const form = useForm({
  promotionkey: props.keyData.promotionkey ?? "",
  description: props.keyData.description ?? "",
  arbdescription: props.keyData.arbdescription ?? "",
  type: props.keyData.type ?? 1,
  activeindicator: props.keyData.activeindicator ?? 1,
  selected_plan_ids: [],
  assigned_plans:
    props.keyData.assignedPlans?.map((plan) => ({
      primary_key: plan.primary_key,
      plannumber: plan.plannumber,
      plandescription: plan.plandescription,
      arbplandescription: plan.arbplandescription,
      qualification_label: plan.qualification_label,
      assignment_label: plan.assignment_label,
      startdate: plan.startdate,
      enddate: plan.enddate,
      active: plan.active,
    })) ?? [],
});

const selectedPlanIds = ref([]);
const promotionTypes = computed(() => props.formMeta.optionSets?.promotionTypes ?? {});
const statusLabels = computed(() => props.formMeta.optionSets?.statusLabels ?? {});

function submit() {
  if (isView.value) return;

  form.selected_plan_ids = selectedPlanIds.value;

  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }

  form.put(`${props.formMeta.baseUrl}/${form.promotionkey}`);
}

function addSelectedPlans() {
  const selected = new Set(selectedPlanIds.value.map((id) => Number(id)));

  for (const plan of props.formMeta.availablePlans ?? []) {
    if (!selected.has(Number(plan.id))) {
      continue;
    }

    const exists = form.assigned_plans.some((row) => Number(row.plannumber) === Number(plan.id));
    if (exists) {
      continue;
    }

    form.assigned_plans.push({
      primary_key: null,
      plannumber: Number(plan.id),
      plandescription: plan.description || "",
      arbplandescription: plan.arbdescription || "",
      qualification_label: plan.qualification_label || "",
      assignment_label: plan.assignment_label || "",
      startdate: new Date().toISOString().slice(0, 10),
      enddate: new Date().toISOString().slice(0, 10),
      active: 1,
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
          v-if="isView && can('promo key', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.promotionkey}/edit`)"
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
    <BaseBlock :title="t.promotion_key">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.promotion_key }}</label>
          <input v-model="form.promotionkey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.type }}</label>
          <select v-model="form.type" class="form-select" :disabled="isView">
            <option v-for="(label, key) in promotionTypes" :key="key" :value="Number(key)">
              {{ label }}
            </option>
          </select>
          <div v-if="errorFor('type')" class="text-danger fs-sm mt-1">{{ errorFor("type") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activeindicator" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
          <div v-if="errorFor('activeindicator')" class="text-danger fs-sm mt-1">
            {{ errorFor("activeindicator") }}
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-6 mb-3">
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

    <BaseBlock :title="t.promo_plan_selection">
      <div v-if="!formMeta.supportsPlans" class="alert alert-warning d-flex align-items-center mb-0" role="alert">
        <i class="fa fa-triangle-exclamation me-2"></i>
        <div><code>promoplandetail</code> {{ t.promoplandetail_required_for_plan_selection_workflow }}</div>
      </div>

      <div v-else-if="isCreate" class="text-muted fs-sm">
        {{ t.save_promo_key_first_then_reopen_attach_plans }}
      </div>

      <template v-else>
        <div class="table-responsive">
          <table class="table table-hover table-vcenter fs-sm">
            <thead>
              <tr>
                <th style="width: 60px"></th>
                <th>{{ t.promo_plan }}</th>
                <th>{{ t.description }}</th>
                <th>{{ t.qualification_group }}</th>
                <th>{{ t.assignment_group }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!(formMeta.availablePlans?.length)">
                <td colspan="5" class="text-center text-muted py-4">{{ t.no_promo_plans_available }}</td>
              </tr>
              <tr v-for="plan in formMeta.availablePlans" :key="plan.id">
                <td>
                  <input v-model="selectedPlanIds" class="form-check-input" type="checkbox" :value="plan.id" :disabled="isView" />
                </td>
                <td>{{ plan.plannumber }}</td>
                <td>
                  <div class="fw-semibold">{{ plan.description || "-" }}</div>
                  <div class="text-muted">{{ plan.arbdescription || "-" }}</div>
                </td>
                <td>{{ plan.qualification_label || "-" }}</td>
                <td>{{ plan.assignment_label || "-" }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!isView" class="d-flex justify-content-center pt-3 mb-3">
          <button class="btn btn-alt-primary px-4" :disabled="!selectedPlanIds.length" @click="addSelectedPlans">
            <i class="fa fa-plus me-1"></i> {{ t.add }}
          </button>
        </div>
      </template>
    </BaseBlock>

    <BaseBlock :title="t.promo_key_plans">
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.promo_plan }}</th>
              <th>{{ t.start_date }}</th>
              <th>{{ t.end_date }}</th>
              <th>{{ t.description }}</th>
              <th>{{ t.qualification_group }}</th>
              <th>{{ t.assignment_group }}</th>
              <th>{{ t.status }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.assigned_plans.length">
              <td :colspan="isView ? 8 : 9" class="text-center text-muted py-4">{{ t.no_promo_plans_assigned }}</td>
            </tr>
            <tr v-for="(plan, index) in form.assigned_plans" :key="`${plan.plannumber}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ plan.plannumber }}</td>
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
                <div class="fw-semibold">{{ plan.plandescription || "-" }}</div>
                <div class="text-muted">{{ plan.arbplandescription || "-" }}</div>
              </td>
              <td>{{ plan.qualification_label || "-" }}</td>
              <td>{{ plan.assignment_label || "-" }}</td>
              <td>
                <span class="badge" :class="plan.active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                  {{ statusLabels[plan.active] || (plan.active ? t.status_active : t.status_inactive) }}
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

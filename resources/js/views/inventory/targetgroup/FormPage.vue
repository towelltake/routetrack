<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  workflowMeta: { type: Object, required: true },
  groupData: { type: Object, required: true },
});

const { can } = usePermissions();
const t = usePage().props.translations.ui;
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() =>
  isCreate.value ? t.create_target_group : isView.value ? t.view_target_group : t.edit_target_group,
);

const form = useForm({
  packagecode: props.groupData.packagecode ?? "",
  alternatecode: props.groupData.alternatecode ?? "",
  packagedescription: props.groupData.packagedescription ?? "",
  arbpackagedescription: props.groupData.arbpackagedescription ?? "",
  activestatus: props.groupData.activestatus ?? 1,
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post(props.workflowMeta.baseUrl);
    return;
  }

  form.put(`${props.workflowMeta.baseUrl}/${form.packagecode}`);
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.target_group_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(workflowMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can(workflowMeta.permission, 'edit')"
          class="btn btn-primary"
          @click="router.get(`${workflowMeta.baseUrl}/${form.packagecode}/edit`)"
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
    <BaseBlock :title="t.target_group_details">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.packagecode" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.alternate_code }} <span class="text-danger" v-if="workflowMeta.useAlternateCode">*</span></label>
          <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
          <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">{{ form.errors.alternatecode }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.packagedescription" class="form-control" :readonly="isView" />
          <div v-if="form.errors.packagedescription" class="text-danger fs-sm mt-1">{{ form.errors.packagedescription }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.arab_description }}</label>
          <input v-model="form.arbpackagedescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="form.errors.arbpackagedescription" class="text-danger fs-sm mt-1">{{ form.errors.arbpackagedescription }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activestatus" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
          <div v-if="form.errors.activestatus" class="text-danger fs-sm mt-1">{{ form.errors.activestatus }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  instructionData: { type: Object, required: true },
});

const { can } = usePermissions();
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_pos_instruction;
  return isView.value ? t.view_pos_instruction : t.edit_pos_instruction;
});

const form = useForm({
  posinstructioncode: props.instructionData.posinstructioncode ?? "",
  alternatecode: props.instructionData.alternatecode ?? "",
  posinstructionname: props.instructionData.posinstructionname ?? "",
  arbposinstructionname: props.instructionData.arbposinstructionname ?? "",
});

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.posinstructioncode}`);
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
          v-if="isView && can('pos instruction', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.posinstructioncode}/edit`)"
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
    <BaseBlock :title="t.details">
      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.posinstructioncode" class="form-control" readonly />
        </div>
        <div class="col-md-8">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input v-model="form.alternatecode" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('alternatecode')" class="text-danger fs-sm mt-1">{{ errorFor("alternatecode") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.name }} <span class="text-danger">*</span></label>
          <input v-model="form.posinstructionname" maxlength="50" class="form-control" :readonly="isView" />
          <div v-if="errorFor('posinstructionname')" class="text-danger fs-sm mt-1">{{ errorFor("posinstructionname") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_name }}</label>
          <input v-model="form.arbposinstructionname" maxlength="50" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbposinstructionname')" class="text-danger fs-sm mt-1">{{ errorFor("arbposinstructionname") }}</div>
        </div>
      </div>

      <div v-if="!isCreate" class="row g-4">
        <div class="col-md-4">
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

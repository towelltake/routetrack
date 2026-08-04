<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  planogramData: { type: Object, required: true },
});

const { can } = usePermissions();
const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => {
  if (isCreate.value) return t.create_planogram;
  return isView.value ? t.view_planogram : t.edit_planogram;
});

const form = useForm({
  visualcode: props.planogramData.visualcode ?? "",
  visualdescription: props.planogramData.visualdescription ?? "",
  arbvisualdescription: props.planogramData.arbvisualdescription ?? "",
  remarks: props.planogramData.remarks ?? "",
  tempcode: props.planogramData.tempcode ?? "",
});

const uploadForm = useForm({
  visualcode: props.planogramData.visualcode ?? "",
  tempcode: props.planogramData.tempcode ?? "",
  imagedescription: "",
  image: null,
});

const uploadInput = ref(null);
const rows = computed(() => props.planogramData.images ?? []);

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }
  form.put(`${props.formMeta.baseUrl}/${form.visualcode}`);
}

function goBack() {
  if (!isCreate.value || !form.tempcode) {
    router.get(props.formMeta.indexUrl);
    return;
  }

  router.post(`${props.formMeta.baseUrl}/cleanup-temp`, { tempcode: form.tempcode });
}

function onFileChange(event) {
  uploadForm.image = event.target.files?.[0] ?? null;
}

function uploadImage() {
  uploadForm.post(`${props.formMeta.baseUrl}/images`, {
    preserveScroll: true,
    preserveState: true,
    forceFormData: true,
    onSuccess: () => {
      uploadForm.imagedescription = "";
      uploadForm.image = null;
      if (uploadInput.value) {
        uploadInput.value.value = "";
      }
    },
  });
}

function removeImage(row) {
  if (!window.confirm(t.remove_this_image_confirm)) {
    return;
  }

  if (isCreate.value) {
    router.delete(`${props.formMeta.baseUrl}/images/temp`, {
      data: { detail_id: row.visualdetail_id, tempcode: form.tempcode, visualcode: form.visualcode },
      preserveScroll: true,
      preserveState: true,
    });
    return;
  }

  router.delete(`${props.formMeta.baseUrl}/images/${row.visualdetail_id}`, {
    preserveScroll: true,
    preserveState: true,
  });
}

function errorFor(field) {
  return form.errors[field];
}

function uploadError(field) {
  return uploadForm.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.planogram_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="goBack">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('planogram', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.visualcode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button v-else-if="!isView" class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.planogram">
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.visualcode" class="form-control" readonly />
        </div>
        <div class="col-md-9">
          <label class="form-label">{{ t.planogram_description }} <span class="text-danger">*</span></label>
          <input v-model="form.visualdescription" maxlength="255" class="form-control" :readonly="isView" />
          <div v-if="errorFor('visualdescription')" class="text-danger fs-sm mt-1">{{ errorFor("visualdescription") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbvisualdescription" maxlength="255" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbvisualdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbvisualdescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.remarks }}</label>
          <input v-model="form.remarks" class="form-control" :readonly="isView" />
          <div v-if="errorFor('remarks')" class="text-danger fs-sm mt-1">{{ errorFor("remarks") }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.planogram_images">
      <div v-if="!isView" class="border rounded p-3 mb-4">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">{{ t.image_description }}</label>
            <input v-model="uploadForm.imagedescription" maxlength="255" class="form-control" />
            <div v-if="uploadError('imagedescription')" class="text-danger fs-sm mt-1">{{ uploadError("imagedescription") }}</div>
          </div>
          <div class="col-md-5">
            <label class="form-label">{{ t.image_label }} <span class="text-danger">*</span></label>
            <input ref="uploadInput" type="file" class="form-control" accept="image/*" @change="onFileChange" />
            <div v-if="uploadError('image')" class="text-danger fs-sm mt-1">{{ uploadError("image") }}</div>
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary w-100" :disabled="uploadForm.processing || !uploadForm.image" @click="uploadImage">
              <i class="fa fa-plus me-1"></i> {{ uploadForm.processing ? t.uploading : t.add_image }}
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width:70px">#</th>
              <th>{{ t.image_description }}</th>
              <th>{{ t.image_label }}</th>
              <th v-if="!isView" class="text-center" style="width:100px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td :colspan="isView ? 3 : 4" class="text-center text-muted py-4">{{ t.no_images_added }}</td>
            </tr>
            <tr v-for="(row, index) in rows" :key="row.visualdetail_id">
              <td>{{ index + 1 }}</td>
              <td>{{ row.imagedescription || "-" }}</td>
              <td>
                <a v-if="row.imageurl" :href="row.imageurl" target="_blank" rel="noreferrer">
                  <img :src="row.imageurl" :alt="row.imagedescription || `Planogram ${index + 1}`" style="max-width: 120px; max-height: 80px" />
                </a>
                <span v-else>-</span>
              </td>
              <td v-if="!isView" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeImage(row)">
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

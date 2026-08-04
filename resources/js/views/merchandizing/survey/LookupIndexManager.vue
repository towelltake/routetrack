<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  managerMeta: { type: Object, required: true },
  lookupHeader: { type: Object, required: true },
  lookupDetails: { type: Array, default: () => [] },
  returnContext: { type: Object, required: true },
});

const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => (isCreate.value ? t.create_survey_lookup : t.edit_survey_lookup));

const headerForm = useForm({
  description: props.lookupHeader.description ?? "",
  arbdescription: props.lookupHeader.arbdescription ?? "",
  ...props.returnContext,
});

const detailForm = useForm({
  description: "",
  arbdescription: "",
});

const editingDetail = ref(null);
const editForm = useForm({
  description: "",
  arbdescription: "",
});

const hasLookupIndex = computed(() => !isCreate.value);

function submitHeader() {
  if (isCreate.value) {
    headerForm.post(props.managerMeta.baseUrl);
    return;
  }
  headerForm.put(`${props.managerMeta.baseUrl}/${props.lookupHeader.transactionkey}`);
}

function submitDetail() {
  detailForm.post(`${props.managerMeta.baseUrl}/${props.lookupHeader.transactionkey}/details`, {
    preserveScroll: true,
    onSuccess: () => {
      detailForm.reset();
      detailForm.clearErrors();
    },
  });
}

function openEdit(detail) {
  editingDetail.value = detail;
  editForm.description = detail.description ?? "";
  editForm.arbdescription = detail.arbdescription ?? "";
  editForm.clearErrors();
}

function submitDetailEdit() {
  if (!editingDetail.value) return;
  editForm.put(`${props.managerMeta.baseUrl}/${props.lookupHeader.transactionkey}/details/${editingDetail.value.primary_key}`, {
    preserveScroll: true,
    onSuccess: () => {
      editingDetail.value = null;
    },
  });
}

function deleteDetail(detail) {
  router.delete(`${props.managerMeta.baseUrl}/${props.lookupHeader.transactionkey}/details/${detail.primary_key}`, {
    preserveScroll: true,
  });
}

function backToSurvey() {
  const query = {
    addlookupindex: 1,
    restore_lookup_context: props.returnContext.return_mode === "edit" ? 1 : undefined,
    surveyindex: props.returnContext.surveyindex,
    surveyprompt: props.returnContext.surveyprompt,
    arbsurveyprompt: props.returnContext.arbsurveyprompt,
    surveyrectype: props.returnContext.surveyrectype,
    responselength: props.returnContext.responselength,
    responsedecimalpos: props.returnContext.responsedecimalpos,
    lookuptype: props.returnContext.lookuptype,
    lookupindex: props.lookupHeader.transactionkey,
    activestatus: props.returnContext.activestatus,
  };

  if (props.returnContext.return_mode === "edit" && props.returnContext.return_id) {
    router.get(`/merchandizing/survey/${props.returnContext.return_id}/edit`, query);
    return;
  }

  router.get("/merchandizing/survey/create", query);
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="managerMeta.subtitle">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToSurvey">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back_to_survey }}
        </button>
        <button class="btn btn-primary" :disabled="headerForm.processing" @click="submitHeader">
          <i class="fa fa-floppy-disk me-1"></i> {{ headerForm.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.survey_lookup">
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.lookup_index }}</label>
          <input :value="lookupHeader.transactionkey" class="form-control" readonly />
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.lookup_description }} <span class="text-danger">*</span></label>
          <input v-model="headerForm.description" maxlength="50" class="form-control" />
          <div v-if="headerForm.errors.description" class="text-danger fs-sm mt-1">{{ headerForm.errors.description }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="headerForm.arbdescription" maxlength="50" class="form-control" dir="rtl" />
          <div v-if="headerForm.errors.arbdescription" class="text-danger fs-sm mt-1">{{ headerForm.errors.arbdescription }}</div>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="hasLookupIndex" :title="t.lookup_index_detail">
      <div class="row g-4 align-items-end mb-4">
        <div class="col-md-5">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="detailForm.description" maxlength="50" class="form-control" />
          <div v-if="detailForm.errors.description" class="text-danger fs-sm mt-1">{{ detailForm.errors.description }}</div>
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="detailForm.arbdescription" maxlength="50" class="form-control" dir="rtl" />
          <div v-if="detailForm.errors.arbdescription" class="text-danger fs-sm mt-1">{{ detailForm.errors.arbdescription }}</div>
        </div>
        <div class="col-md-2">
          <button class="btn btn-alt-primary w-100" :disabled="detailForm.processing" @click="submitDetail">
            <i class="fa fa-plus me-1"></i> {{ t.add }}
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.description }}</th>
              <th>{{ t.arabic_description }}</th>
              <th class="text-center" style="width:120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lookupDetails.length">
              <td colspan="4" class="text-center text-muted py-4">{{ t.no_lookup_details_found }}</td>
            </tr>
            <tr v-for="(detail, index) in lookupDetails" :key="detail.primary_key">
              <td class="text-muted">{{ index + 1 }}</td>
              <td>{{ detail.description }}</td>
              <td>{{ detail.arbdescription || "-" }}</td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-alt-secondary me-1" @click="openEdit(detail)">
                  <i class="fa fa-pen"></i>
                </button>
                <button class="btn btn-sm btn-alt-danger" @click="deleteDetail(detail)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>

  <div v-if="editingDetail" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t.edit_lookup_detail }}</h5>
          <button class="btn-close" @click="editingDetail = null"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ t.description }}</label>
            <input v-model="editForm.description" maxlength="50" class="form-control" />
            <div v-if="editForm.errors.description" class="text-danger fs-sm mt-1">{{ editForm.errors.description }}</div>
          </div>
          <div>
            <label class="form-label">{{ t.arabic_description }}</label>
            <input v-model="editForm.arbdescription" maxlength="50" class="form-control" dir="rtl" />
            <div v-if="editForm.errors.arbdescription" class="text-danger fs-sm mt-1">{{ editForm.errors.arbdescription }}</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="editingDetail = null">{{ t.cancel }}</button>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitDetailEdit">{{ t.save }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

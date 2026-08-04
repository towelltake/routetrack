<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({ taxes: Array });
const t = usePage().props.translations.ui;

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q
    ? props.taxes.filter(
        (record) =>
          record.taxdescription?.toLowerCase().includes(q) || record.taxcode?.toLowerCase().includes(q),
      )
    : props.taxes;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ taxcode: "", taxdescription: "", arbtaxdescription: "", pricecomponent: 0 });

function openAdd() {
  isEditing.value = false;
  form.reset();
  form.clearErrors();
  showModal.value = true;
}

function openEdit(record) {
  isEditing.value = true;
  editingId.value = record.taxcode;
  form.taxcode = record.taxcode;
  form.taxdescription = record.taxdescription;
  form.arbtaxdescription = record.arbtaxdescription ?? "";
  form.pricecomponent = record.pricecomponent ?? 0;
  form.clearErrors();
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  form.reset();
  form.clearErrors();
}

function submit() {
  const options = { preserveScroll: true, onSuccess: closeModal };
  if (isEditing.value) {
    form.put(`/basic/tax/${editingId.value}`, options);
    return;
  }

  form.post("/basic/tax", options);
}

function deleteRow() {
  router.delete(`/basic/tax/${confirmingDelete.value.taxcode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.tax_master" />
  <BasePageHeading :title="t.tax_master" :subtitle="t.tax_note">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> {{ t.add_tax }}</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock :title="t.tax_list">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search" style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>{{ t.tax_code }}</th><th>{{ t.description }}</th><th>{{ t.price_component }}</th><th class="text-center" style="width:100px">{{ t.actions }}</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="5" class="text-center text-muted py-4">{{ t.no_records_found }}</td></tr>
            <tr v-for="(record, index) in filtered" :key="record.taxcode">
              <td class="text-muted">{{ index + 1 }}</td>
              <td class="fw-semibold">{{ record.taxcode }}</td>
              <td>{{ record.taxdescription }}</td>
              <td><span v-if="record.pricecomponent" class="badge bg-info">{{ t.yes }}</span></td>
              <td class="text-center">
                <button class="btn btn-sm btn-alt-secondary me-1" @click="openEdit(record)"><i class="fa fa-pen"></i></button>
                <button class="btn btn-sm btn-alt-danger" @click="confirmingDelete = record"><i class="fa fa-trash"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseBlock>
  </div>

  <div v-if="showModal" class="modal fade show d-block" style="background:rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? t.edit_tax : t.create_tax }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-6">
              <label class="form-label">{{ t.tax_code }} <span class="text-danger">*</span></label>
              <input v-model="form.taxcode" class="form-control" :class="{'is-invalid': form.errors.taxcode}" :readonly="isEditing" maxlength="20" />
              <div class="invalid-feedback">{{ form.errors.taxcode }}</div>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input v-model="form.pricecomponent" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="pricecomp" />
                <label class="form-check-label" for="pricecomp">{{ t.price_component }}</label>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
              <input v-model="form.taxdescription" class="form-control" :class="{'is-invalid': form.errors.taxdescription}" />
              <div class="invalid-feedback">{{ form.errors.taxdescription }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">{{ t.arabic_description }}</label>
              <input v-model="form.arbtaxdescription" class="form-control" dir="rtl" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">{{ t.cancel }}</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ isEditing ? t.update : t.create }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background:rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}</h5><button class="btn-close" @click="confirmingDelete = null"></button></div>
        <div class="modal-body">{{ t.delete_tax_label }} <strong>{{ confirmingDelete.taxdescription }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

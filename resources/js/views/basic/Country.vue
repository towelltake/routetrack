<script setup>
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({ countries: Array, currencies: Array });
const t = usePage().props.translations.ui;

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.countries.filter((record) => record.countryname?.toLowerCase().includes(q)) : props.countries;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ alternatecode: "", countryname: "", arbcountryname: "", currencycode: null });

function openAdd() {
  isEditing.value = false;
  form.reset();
  form.clearErrors();
  showModal.value = true;
}

function openEdit(record) {
  isEditing.value = true;
  editingId.value = record.countrycode;
  form.alternatecode = record.alternatecode ?? "";
  form.countryname = record.countryname;
  form.arbcountryname = record.arbcountryname ?? "";
  form.currencycode = record.currencycode;
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
    form.put(`/basic/country/${editingId.value}`, options);
    return;
  }

  form.post("/basic/country", options);
}

function deleteRow() {
  router.delete(`/basic/country/${confirmingDelete.value.countrycode}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}

function currencyName(code) {
  return props.currencies.find((currency) => currency.currencycode === code)?.currencyname ?? "-";
}
</script>

<template>
  <Head :title="t.country_master" />
  <BasePageHeading :title="t.country_master" :subtitle="t.country_note">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> {{ t.add_country }}</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock :title="t.country_list">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" :placeholder="t.search" style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>{{ t.code }}</th><th>{{ t.country_name }}</th><th>{{ t.currency }}</th><th class="text-center" style="width:100px">{{ t.actions }}</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="5" class="text-center text-muted py-4">{{ t.no_records_found }}</td></tr>
            <tr v-for="(record, index) in filtered" :key="record.countrycode">
              <td class="text-muted">{{ index + 1 }}</td>
              <td>{{ record.alternatecode }}</td>
              <td class="fw-semibold">{{ record.countryname }}</td>
              <td>{{ currencyName(record.currencycode) }}</td>
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
        <div class="modal-header">
          <h5 class="modal-title">{{ isEditing ? t.edit_country : t.create_country }}</h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-6">
              <label class="form-label">{{ t.alternate_code }}</label>
              <input v-model="form.alternatecode" class="form-control" maxlength="20" />
            </div>
            <div class="col-6">
              <label class="form-label">{{ t.currency }}</label>
              <select v-model="form.currencycode" class="form-select">
                <option :value="null">- {{ t.select }} -</option>
                <option v-for="currency in currencies" :key="currency.currencycode" :value="currency.currencycode">{{ currency.currencyname }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">{{ t.country_name }} <span class="text-danger">*</span></label>
              <input v-model="form.countryname" class="form-control" :class="{'is-invalid': form.errors.countryname}" />
              <div class="invalid-feedback">{{ form.errors.countryname }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">{{ t.arb_country_name }}</label>
              <input v-model="form.arbcountryname" class="form-control" dir="rtl" />
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
        <div class="modal-body">{{ t.delete_country_label }} <strong>{{ confirmingDelete.countryname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

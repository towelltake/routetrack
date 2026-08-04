<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ regions: Array, countries: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.regions.filter(r => r.regionmstname?.toLowerCase().includes(q) || r.alternatecode?.toLowerCase().includes(q)) : props.regions;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ alternatecode: "", regionmstname: "", arbregionmstname: "", countrycode: null });

function openAdd() { isEditing.value = false; form.reset(); form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.regionmstcode;
  form.alternatecode = r.alternatecode ?? "";
  form.regionmstname = r.regionmstname;
  form.arbregionmstname = r.arbregionmstname ?? "";
  form.countrycode = r.countrycode ?? null;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/region/${editingId.value}`, opts) : form.post("/operation/region", opts);
}
function deleteRow() {
  router.delete(`/operation/region/${confirmingDelete.value.regionmstcode}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const countryName = (id) => props.countries?.find(c => c.countrycode === id)?.countryname ?? "—";
</script>

<template>
  <Head title="Region Master" />
  <BasePageHeading title="Region Master" subtitle="Manage regions">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Regions">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Region Name</th><th>Country</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.regionmstcode">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.alternatecode }}</td>
              <td class="fw-semibold">{{ r.regionmstname }}</td>
              <td>{{ countryName(r.countrycode) }}</td>
              <td class="text-center">
                <button class="btn btn-sm btn-alt-secondary me-1" @click="openEdit(r)"><i class="fa fa-pen"></i></button>
                <button class="btn btn-sm btn-alt-danger" @click="confirmingDelete = r"><i class="fa fa-trash"></i></button>
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
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Region" : "Add Region" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-6">
              <label class="form-label">Code</label>
              <input v-model="form.alternatecode" class="form-control" maxlength="50" />
            </div>
            <div class="col-6">
              <label class="form-label">Country</label>
              <select v-model="form.countrycode" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="c in countries" :key="c.countrycode" :value="c.countrycode">{{ c.countryname }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Region Name <span class="text-danger">*</span></label>
              <input v-model="form.regionmstname" class="form-control" :class="{'is-invalid':form.errors.regionmstname}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.regionmstname }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbregionmstname" class="form-control" dir="rtl" maxlength="50" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ isEditing ? "Update" : "Create" }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div v-if="confirmingDelete" class="modal fade show d-block" style="background:rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger"><i class="fa fa-triangle-exclamation me-1"></i> Delete</h5><button class="btn-close" @click="confirmingDelete=null"></button></div>
        <div class="modal-body">Delete <strong>{{ confirmingDelete.regionmstname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ vehicles: Array, companies: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.vehicles.filter(r => r.vandescription?.toLowerCase().includes(q) || r.code?.toLowerCase().includes(q) || r.vehicleregistration?.toLowerCase().includes(q)) : props.vehicles;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ code: "", vandescription: "", arbvandescription: "", vehicleregistration: "", vanmodel: "", vantype: "", companyid: null, statusflag: 1 });

function openAdd() { isEditing.value = false; form.reset(); form.statusflag = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.id;
  form.code = r.code ?? ""; form.vandescription = r.vandescription ?? ""; form.arbvandescription = r.arbvandescription ?? "";
  form.vehicleregistration = r.vehicleregistration ?? ""; form.vanmodel = r.vanmodel ?? ""; form.vantype = r.vantype ?? "";
  form.companyid = r.companyid ?? null; form.statusflag = r.statusflag ?? 1;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/vehicle/${editingId.value}`, opts) : form.post("/operation/vehicle", opts);
}
function deleteRow() {
  router.delete(`/operation/vehicle/${confirmingDelete.value.id}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const companyName = (id) => props.companies.find(c => c.id === id)?.companyname ?? "—";
</script>

<template>
  <Head title="Vehicle Master" />
  <BasePageHeading title="Vehicle Master" subtitle="Manage vehicles">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Vehicles">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Description</th><th>Reg. No.</th><th>Model</th><th>Company</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.id">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.code }}</td>
              <td class="fw-semibold">{{ r.vandescription }}</td>
              <td>{{ r.vehicleregistration }}</td>
              <td>{{ r.vanmodel }}</td>
              <td>{{ companyName(r.companyid) }}</td>
              <td><span class="badge" :class="r.statusflag ? 'bg-success' : 'bg-secondary'">{{ r.statusflag ? 'Active' : 'Inactive' }}</span></td>
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Vehicle" : "Add Vehicle" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-4">
              <label class="form-label">Code</label>
              <input v-model="form.code" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Reg. Number</label>
              <input v-model="form.vehicleregistration" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Company</label>
              <select v-model="form.companyid" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.companyname }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Description <span class="text-danger">*</span></label>
              <input v-model="form.vandescription" class="form-control" :class="{'is-invalid':form.errors.vandescription}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.vandescription }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">Arabic Description</label>
              <input v-model="form.arbvandescription" class="form-control" dir="rtl" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Model</label>
              <input v-model="form.vanmodel" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Type</label>
              <input v-model="form.vantype" class="form-control" maxlength="50" />
            </div>
            <div class="col-4 d-flex align-items-end">
              <div class="form-check">
                <input v-model="form.statusflag" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="vehicleactive" />
                <label class="form-check-label" for="vehicleactive">Active</label>
              </div>
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.vandescription }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

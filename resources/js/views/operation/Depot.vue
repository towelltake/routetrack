<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ depots: Array, companies: Array, regions: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.depots.filter(r => r.depotname?.toLowerCase().includes(q) || r.alternatedepotcode?.toLowerCase().includes(q)) : props.depots;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({
  alternatedepotcode: "", depotname: "", arbdepotname: "",
  cmpycode: null, regionmstcode: null, centralwh: 0,
  activestatus: 1, phonenumber: "", faxnumber: "",
});

function openAdd() { isEditing.value = false; form.reset(); form.activestatus = 1; form.centralwh = 0; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.depotcode;
  form.alternatedepotcode = r.alternatedepotcode ?? "";
  form.depotname = r.depotname;
  form.arbdepotname = r.arbdepotname ?? "";
  form.cmpycode = r.cmpycode ?? null;
  form.regionmstcode = r.regionmstcode ?? null;
  form.centralwh = r.centralwh ?? 0;
  form.activestatus = r.activestatus ?? 1;
  form.phonenumber = r.phonenumber ?? "";
  form.faxnumber = r.faxnumber ?? "";
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/depot/${editingId.value}`, opts) : form.post("/operation/depot", opts);
}
function deleteRow() {
  router.delete(`/operation/depot/${confirmingDelete.value.depotcode}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const companyName = (id) => props.companies?.find(c => c.cmpycode === id)?.name ?? "—";
const regionName = (id) => props.regions?.find(r => r.regionmstcode === id)?.regionmstname ?? "—";
</script>

<template>
  <Head title="Depot Master" />
  <BasePageHeading title="Depot Master" subtitle="Manage depots">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Depots">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Depot Name</th><th>Company</th><th>Region</th><th>Central WH</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.depotcode">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.alternatedepotcode }}</td>
              <td class="fw-semibold">{{ r.depotname }}</td>
              <td>{{ companyName(r.cmpycode) }}</td>
              <td>{{ regionName(r.regionmstcode) }}</td>
              <td><span v-if="r.centralwh" class="badge bg-info">Yes</span></td>
              <td><span class="badge" :class="r.activestatus ? 'bg-success' : 'bg-secondary'">{{ r.activestatus ? 'Active' : 'Inactive' }}</span></td>
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
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Depot" : "Add Depot" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-4">
              <label class="form-label">Code</label>
              <input v-model="form.alternatedepotcode" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Depot Name <span class="text-danger">*</span></label>
              <input v-model="form.depotname" class="form-control" :class="{'is-invalid':form.errors.depotname}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.depotname }}</div>
            </div>
            <div class="col-4">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbdepotname" class="form-control" dir="rtl" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Company</label>
              <select v-model="form.cmpycode" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="c in companies" :key="c.cmpycode" :value="c.cmpycode">{{ c.name }}</option>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Region</label>
              <select v-model="form.regionmstcode" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="r in regions" :key="r.regionmstcode" :value="r.regionmstcode">{{ r.regionmstname }}</option>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Phone Number</label>
              <input v-model="form.phonenumber" class="form-control" maxlength="15" />
            </div>
            <div class="col-4">
              <label class="form-label">Fax Number</label>
              <input v-model="form.faxnumber" class="form-control" maxlength="15" />
            </div>
            <div class="col-12 d-flex gap-4">
              <div class="form-check">
                <input v-model="form.centralwh" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="depotcwh" />
                <label class="form-check-label" for="depotcwh">Central Warehouse</label>
              </div>
              <div class="form-check">
                <input v-model="form.activestatus" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="depotactive" />
                <label class="form-check-label" for="depotactive">Active</label>
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.depotname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

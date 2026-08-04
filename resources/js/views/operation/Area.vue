<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ areas: Array, depots: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.areas.filter(r => r.areaname?.toLowerCase().includes(q) || r.alternateareacode?.toLowerCase().includes(q)) : props.areas;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ alternateareacode: "", areaname: "", arbareaname: "", depotcode: null, activestatus: 1 });

function openAdd() { isEditing.value = false; form.reset(); form.activestatus = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.areacode;
  form.alternateareacode = r.alternateareacode ?? "";
  form.areaname = r.areaname;
  form.arbareaname = r.arbareaname ?? "";
  form.depotcode = r.depotcode ?? null;
  form.activestatus = r.activestatus ?? 1;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/area/${editingId.value}`, opts) : form.post("/operation/area", opts);
}
function deleteRow() {
  router.delete(`/operation/area/${confirmingDelete.value.areacode}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const depotName = (id) => props.depots?.find(d => d.depotcode === id)?.depotname ?? "—";
</script>

<template>
  <Head title="Area Master" />
  <BasePageHeading title="Area Master" subtitle="Manage areas">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Areas">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Area Name</th><th>Depot</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="6" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.areacode">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.alternateareacode }}</td>
              <td class="fw-semibold">{{ r.areaname }}</td>
              <td>{{ depotName(r.depotcode) }}</td>
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
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Area" : "Add Area" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-6">
              <label class="form-label">Code</label>
              <input v-model="form.alternateareacode" class="form-control" maxlength="30" />
            </div>
            <div class="col-6">
              <label class="form-label">Depot</label>
              <select v-model="form.depotcode" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="d in depots" :key="d.depotcode" :value="d.depotcode">{{ d.depotname }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Area Name <span class="text-danger">*</span></label>
              <input v-model="form.areaname" class="form-control" :class="{'is-invalid':form.errors.areaname}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.areaname }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbareaname" class="form-control" dir="rtl" maxlength="50" />
            </div>
            <div class="col-12 d-flex align-items-center">
              <div class="form-check">
                <input v-model="form.activestatus" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="areaactive" />
                <label class="form-check-label" for="areaactive">Active</label>
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.areaname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

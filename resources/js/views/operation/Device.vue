<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ devices: Array, companies: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.devices.filter(r => r.deviceid?.toLowerCase().includes(q) || r.remarks?.toLowerCase().includes(q)) : props.devices;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ deviceid: "", remarks: "", companyid: null, statusflag: 1 });

function openAdd() { isEditing.value = false; form.reset(); form.statusflag = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.id;
  form.deviceid = r.deviceid ?? ""; form.remarks = r.remarks ?? "";
  form.companyid = r.companyid ?? null; form.statusflag = r.statusflag ?? 1;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/device/${editingId.value}`, opts) : form.post("/operation/device", opts);
}
function deleteRow() {
  router.delete(`/operation/device/${confirmingDelete.value.id}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const companyName = (id) => props.companies.find(c => c.id === id)?.companyname ?? "—";
</script>

<template>
  <Head title="Device Master" />
  <BasePageHeading title="Device Master" subtitle="Manage registered devices">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Devices">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Device ID</th><th>Remarks</th><th>Company</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="6" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.id">
              <td class="text-muted">{{ i + 1 }}</td>
              <td class="fw-semibold">{{ r.deviceid }}</td>
              <td>{{ r.remarks }}</td>
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
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Device" : "Add Device" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-8">
              <label class="form-label">Device ID <span class="text-danger">*</span></label>
              <input v-model="form.deviceid" class="form-control" :class="{'is-invalid':form.errors.deviceid}" maxlength="255" />
              <div class="invalid-feedback">{{ form.errors.deviceid }}</div>
            </div>
            <div class="col-4 d-flex align-items-end">
              <div class="form-check">
                <input v-model="form.statusflag" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="deviceactive" />
                <label class="form-check-label" for="deviceactive">Active</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Company</label>
              <select v-model="form.companyid" class="form-select">
                <option :value="null">— Select —</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.companyname }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Remarks</label>
              <input v-model="form.remarks" class="form-control" maxlength="255" />
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
        <div class="modal-body">Delete device <strong>{{ confirmingDelete.deviceid }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

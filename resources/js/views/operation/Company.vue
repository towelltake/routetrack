<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ companies: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.companies.filter(r => r.name?.toLowerCase().includes(q) || r.alternatecmpycode?.toLowerCase().includes(q)) : props.companies;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({
  alternatecmpycode: "", name: "", arbcompanyname: "", parentcompany: null,
  address: "", telephone: "", activestatus: 1,
});

function openAdd() { isEditing.value = false; form.reset(); form.activestatus = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.cmpycode;
  form.alternatecmpycode = r.alternatecmpycode ?? "";
  form.name = r.name;
  form.arbcompanyname = r.arbcompanyname ?? "";
  form.parentcompany = r.parentcompany ?? null;
  form.address = r.address ?? "";
  form.telephone = r.telephone ?? "";
  form.activestatus = r.activestatus ?? 1;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/company/${editingId.value}`, opts) : form.post("/operation/company", opts);
}
function deleteRow() {
  router.delete(`/operation/company/${confirmingDelete.value.cmpycode}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const parentName = (id) => id ? (props.companies.find(c => c.cmpycode === id)?.name ?? id) : "—";
</script>

<template>
  <Head title="Company Master" />
  <BasePageHeading title="Company Master" subtitle="Manage companies">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Companies">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:200px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Company Name</th><th>Telephone</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.cmpycode">
              <td class="text-muted">{{ i + 1 }}</td>
              <td class="fw-semibold">{{ r.name }}</td>
              <td>{{ r.telephone }}</td>
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
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Company" : "Add Company" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-4">
              <label class="form-label">Company Name <span class="text-danger">*</span></label>
              <input v-model="form.name" class="form-control" :class="{'is-invalid':form.errors.name}" maxlength="100" />
              <div class="invalid-feedback">{{ form.errors.name }}</div>
            </div>
            <div class="col-4">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbcompanyname" class="form-control" dir="rtl" maxlength="100" />
            </div>
            <div class="col-4">
              <label class="form-label">Telephone</label>
              <input v-model="form.telephone" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Address</label>
              <input v-model="form.address" class="form-control" maxlength="255" />
            </div>
            <div class="col-4">
              <label class="form-label">Parent Company</label>
              <select v-model="form.parentcompany" class="form-select">
                <option :value="null">— None —</option>
                <option v-for="c in companies" :key="c.cmpycode" :value="c.cmpycode" :disabled="isEditing && c.cmpycode === editingId">{{ c.name }}</option>
              </select>
            </div>
            <div class="col-12 d-flex align-items-center">
              <div class="form-check">
                <input v-model="form.activestatus" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="companyactive" />
                <label class="form-check-label" for="companyactive">Active</label>
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.name }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

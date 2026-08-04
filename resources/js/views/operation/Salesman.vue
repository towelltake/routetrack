<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ salesmans: Array, companies: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.salesmans.filter(r => r.salesmanname?.toLowerCase().includes(q) || r.code?.toLowerCase().includes(q)) : props.salesmans;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ code: "", salesmanname: "", arbsalesmanname: "", contactnumber: "", companyid: null, username: "", userpassword: "", statusflag: 1 });

function openAdd() { isEditing.value = false; form.reset(); form.statusflag = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.id;
  form.code = r.code ?? ""; form.salesmanname = r.salesmanname ?? ""; form.arbsalesmanname = r.arbsalesmanname ?? "";
  form.contactnumber = r.contactnumber ?? ""; form.companyid = r.companyid ?? null;
  form.username = r.username ?? ""; form.userpassword = "";
  form.statusflag = r.statusflag ?? 1; form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/operation/salesman/${editingId.value}`, opts) : form.post("/operation/salesman", opts);
}
function deleteRow() {
  router.delete(`/operation/salesman/${confirmingDelete.value.id}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}

const companyName = (id) => props.companies.find(c => c.id === id)?.companyname ?? "â€”";
</script>

<template>
  <Head title="Salesman Master" />
  <BasePageHeading title="Salesman Master" subtitle="Manage salesman records">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Salesman">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Contact</th><th>Username</th><th>Company</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.id">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.code }}</td>
              <td class="fw-semibold">{{ r.salesmanname }}</td>
              <td>{{ r.contactnumber }}</td>
              <td>{{ r.username }}</td>
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
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Salesman" : "Add Salesman" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-4">
              <label class="form-label">Code</label>
              <input v-model="form.code" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Contact Number</label>
              <input v-model="form.contactnumber" class="form-control" maxlength="50" />
            </div>
            <div class="col-4">
              <label class="form-label">Company</label>
              <select v-model="form.companyid" class="form-select">
                <option :value="null">â€” Select â€”</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.companyname }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Salesman Name <span class="text-danger">*</span></label>
              <input v-model="form.salesmanname" class="form-control" :class="{'is-invalid':form.errors.salesmanname}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.salesmanname }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbsalesmanname" class="form-control" dir="rtl" maxlength="50" />
            </div>
            <div class="col-6">
              <label class="form-label">Username</label>
              <input v-model="form.username" class="form-control" maxlength="255" autocomplete="off" />
            </div>
            <div class="col-6">
              <label class="form-label">Password{{ isEditing ? " (leave blank to keep)" : "" }}</label>
              <input v-model="form.userpassword" type="password" class="form-control" maxlength="255" autocomplete="new-password" />
            </div>
            <div class="col-12">
              <div class="form-check">
                <input v-model="form.statusflag" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="salesmanactive" />
                <label class="form-check-label" for="salesmanactive">Active</label>
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.salesmanname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

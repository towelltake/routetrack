<script setup>
import { ref, computed } from "vue";
import { useForm, router } from "@inertiajs/vue3";

const props = defineProps({ categories: Array });

const search = ref("");
const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return q ? props.categories.filter(r => r.categoryname?.toLowerCase().includes(q) || r.alternatecode?.toLowerCase().includes(q)) : props.categories;
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ alternatecode: "", categoryname: "", arbcategoryname: "", activestatus: 1 });

function openAdd() { isEditing.value = false; form.reset(); form.activestatus = 1; form.clearErrors(); showModal.value = true; }
function openEdit(r) {
  isEditing.value = true; editingId.value = r.categoryid;
  form.alternatecode = r.alternatecode ?? "";
  form.categoryname = r.categoryname;
  form.arbcategoryname = r.arbcategoryname ?? "";
  form.activestatus = r.activestatus ?? 1;
  form.clearErrors(); showModal.value = true;
}
function closeModal() { showModal.value = false; form.reset(); form.clearErrors(); }
function submit() {
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value ? form.put(`/organisation/category/${editingId.value}`, opts) : form.post("/organisation/category", opts);
}
function deleteRow() {
  router.delete(`/organisation/category/${confirmingDelete.value.categoryid}`, { preserveScroll: true, onSuccess: () => confirmingDelete.value = null });
}
</script>

<template>
  <Head title="Category Master" />
  <BasePageHeading title="Category Master" subtitle="Manage customer categories">
    <template #extra>
      <button class="btn btn-primary" @click="openAdd"><i class="fa fa-plus me-1"></i> Add</button>
    </template>
  </BasePageHeading>
  <div class="content">
    <BaseBlock title="Categories">
      <template #options>
        <input v-model="search" type="text" class="form-control form-control-sm" placeholder="Search..." style="width:180px" />
      </template>
      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead><tr><th>#</th><th>Code</th><th>Category Name</th><th>Status</th><th class="text-center" style="width:100px">Actions</th></tr></thead>
          <tbody>
            <tr v-if="!filtered.length"><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
            <tr v-for="(r, i) in filtered" :key="r.categoryid">
              <td class="text-muted">{{ i + 1 }}</td>
              <td>{{ r.alternatecode }}</td>
              <td class="fw-semibold">{{ r.categoryname }}</td>
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
        <div class="modal-header"><h5 class="modal-title">{{ isEditing ? "Edit Category" : "Add Category" }}</h5><button class="btn-close" @click="closeModal"></button></div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-6">
              <label class="form-label">Code</label>
              <input v-model="form.alternatecode" class="form-control" maxlength="50" />
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input v-model="form.activestatus" :true-value="1" :false-value="0" type="checkbox" class="form-check-input" id="categoryactive" />
                <label class="form-check-label" for="categoryactive">Active</label>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">Category Name <span class="text-danger">*</span></label>
              <input v-model="form.categoryname" class="form-control" :class="{'is-invalid':form.errors.categoryname}" maxlength="50" />
              <div class="invalid-feedback">{{ form.errors.categoryname }}</div>
            </div>
            <div class="col-6">
              <label class="form-label">Arabic Name</label>
              <input v-model="form.arbcategoryname" class="form-control" dir="rtl" maxlength="50" />
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
        <div class="modal-body">Delete <strong>{{ confirmingDelete.categoryname }}</strong>?</div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete=null">Cancel</button>
          <button class="btn btn-danger" @click="deleteRow">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

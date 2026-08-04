<script setup>
import { computed, ref, watch } from "vue";
import { useForm, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  userTypes: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.userTypes?.data ?? []);

const showModal = ref(false);
const isEditing = ref(false);
const isViewing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);

const form = useForm({ user_type: "" });
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

let searchDebounce = null;
watch(search, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    reloadList();
  }, 300);
});

watch(perPage, () => {
  reloadList();
});

function reloadList(page = 1) {
  router.get("/usermanagement/user-type", {
    search: search.value || undefined,
    per_page: perPage.value,
    page,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["userTypes", "filters"],
  });
}

function openAdd() {
  isEditing.value = false;
  isViewing.value = false;
  form.reset();
  form.clearErrors();
  showModal.value = true;
}

function openView(r) {
  isEditing.value = false;
  isViewing.value = true;
  editingId.value = r.id;
  form.user_type = r.user_type;
  form.clearErrors();
  showModal.value = true;
}

function openEdit(r) {
  isEditing.value = true;
  isViewing.value = false;
  editingId.value = r.id;
  form.user_type = r.user_type;
  form.clearErrors();
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  isViewing.value = false;
  form.reset();
  form.clearErrors();
}

function submit() {
  if (isViewing.value) return;
  const opts = { preserveScroll: true, onSuccess: closeModal };
  isEditing.value
    ? form.put(`/usermanagement/user-type/${editingId.value}`, opts)
    : form.post("/usermanagement/user-type", opts);
}

function deleteRow() {
  router.delete(`/usermanagement/user-type/${confirmingDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => (confirmingDelete.value = null),
  });
}
</script>

<template>
  <Head :title="t.user_type" />

  <BasePageHeading :title="t.user_type" :subtitle="t.user_type_note">
    <template #extra>
      <button v-if="can('user type', 'create')" class="btn btn-primary" @click="openAdd">
        <i class="fa fa-plus me-1"></i> {{ t.add_user_type }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.user_types">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 180px"
          />
          <select v-model="perPage" class="form-select form-select-sm" style="width: 90px">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </template>

      <div class="table-responsive">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t.user_type_name }}</th>
              <th class="text-center" style="width: 100px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="3" class="text-center text-muted py-4">{{ t.no_user_types_found }}</td>
            </tr>
            <tr v-for="r in rows" :key="r.id">
              <td class="text-muted">{{ r.id }}</td>
              <td class="fw-semibold">{{ r.user_type }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('user type')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(r)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('user type', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(r)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('user type', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  :title="t.delete"
                  @click="confirmingDelete = r"
                >
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center pt-3">
        <div class="text-muted fs-sm">
          {{ t.showing }} {{ userTypes.from ?? 0 }} {{ t.to }} {{ userTypes.to ?? 0 }} {{ t.of }} {{ userTypes.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!userTypes.prev_page_url"
            @click="reloadList((userTypes.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ userTypes.current_page || 1 }} / {{ userTypes.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!userTypes.next_page_url"
            @click="reloadList((userTypes.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <!-- Add / Edit Modal -->
  <div
    v-if="showModal"
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isViewing ? t.view_user_type : (isEditing ? t.edit_user_type : t.add_user_type) }}
          </h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-12">
              <label class="form-label">{{ t.user_type_name }} <span class="text-danger">*</span></label>
              <input
                v-model="form.user_type"
                class="form-control"
                :class="{ 'is-invalid': form.errors.user_type }"
                maxlength="50"
                :readonly="isViewing"
                autofocus
              />
              <div class="invalid-feedback">{{ form.errors.user_type }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">{{ t.cancel }}</button>
            <button v-if="!isViewing" type="submit" class="btn btn-primary" :disabled="form.processing">
              {{ isEditing ? t.update : t.create }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation -->
  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background: rgba(0, 0, 0, 0.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.delete_user_type_confirm }} <strong>{{ confirmingDelete.user_type }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

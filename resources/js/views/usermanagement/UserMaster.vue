<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  users: Object,
  filters: Object,
  nextUserId: Number,
  userTypes: Array,
  accessTypes: Array,
  accessOptions: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const showModal = ref(false);
const isEditing = ref(false);
const isViewing = ref(false);
const editingId = ref(null);
const confirmingDelete = ref(null);
const suppressAccessReset = ref(false);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view);
}

const rows = computed(() => props.users?.data ?? []);

const form = useForm({
  username: "",
  email: "",
  password: "",
  password_confirmation: "",
  usertypeid: null,
  accesstypeid: null,
  access_ids: [],
});

const currentCode = computed(() => ((isEditing.value || isViewing.value) ? editingId.value : props.nextUserId));
const currentAccessOptions = computed(() =>
  (props.accessOptions?.[form.accesstypeid] ?? []).map((option) => ({
    ...option,
    id: String(option.id),
  }))
);
const accessScopeKey = computed(() => `${form.accesstypeid ?? "none"}:${form.access_ids.join(",")}`);

function isAccessSelected(optionId) {
  return form.access_ids.includes(String(optionId));
}

watch(() => form.accesstypeid, () => {
  if (suppressAccessReset.value) {
    return;
  }

  form.access_ids = [];
});

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
  router.get("/usermanagement/user-master", {
    search: search.value || undefined,
    per_page: perPage.value,
    page,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["users", "filters"],
  });
}

function openAdd() {
  isEditing.value = false;
  isViewing.value = false;
  editingId.value = null;
  form.reset();
  form.clearErrors();
  showModal.value = true;
}

function openView(user) {
  isEditing.value = false;
  isViewing.value = true;
  editingId.value = user.userid;
  form.reset();
  form.clearErrors();
  suppressAccessReset.value = true;
  form.username = user.username;
  form.email = user.email ?? "";
  form.password = "";
  form.password_confirmation = "";
  form.usertypeid = user.usertypeid;
  form.accesstypeid = user.accesstypeid || null;
  showModal.value = true;
  nextTick(() => {
    form.access_ids = (user.access_ids || []).map((id) => String(id));
    suppressAccessReset.value = false;
  });
}

function openEdit(user) {
  isEditing.value = true;
  isViewing.value = false;
  editingId.value = user.userid;
  form.reset();
  form.clearErrors();
  suppressAccessReset.value = true;
  form.username = user.username;
  form.email = user.email ?? "";
  form.password = "";
  form.password_confirmation = "";
  form.usertypeid = user.usertypeid;
  form.accesstypeid = user.accesstypeid || null;
  showModal.value = true;
  nextTick(() => {
    form.access_ids = (user.access_ids || []).map((id) => String(id));
    suppressAccessReset.value = false;
  });
}

function closeModal() {
  showModal.value = false;
  isViewing.value = false;
  form.reset();
  form.clearErrors();
}

function submit() {
  if (isViewing.value) return;
  const options = { preserveScroll: true, onSuccess: closeModal };
  if (isEditing.value) {
    form.put(`/usermanagement/user-master/${editingId.value}`, options);
    return;
  }

  form.post("/usermanagement/user-master", options);
}

function deleteUser() {
  router.delete(`/usermanagement/user-master/${confirmingDelete.value.userid}`, {
    preserveScroll: true,
    onSuccess: () => (confirmingDelete.value = null),
  });
}
</script>

<template>
  <Head :title="t.user_master" />

  <BasePageHeading :title="t.user_master" :subtitle="t.user_master_note">
    <template #extra>
      <button v-if="can('users', 'create')" type="button" class="btn btn-primary" @click="openAdd">
        <i class="fa fa-plus me-1"></i> {{ t.add_user_master }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.user_master">
      <template #options>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 220px"
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
              <th>{{ t.code }}</th>
              <th>{{ t.username }}</th>
              <th>{{ t.user_type }}</th>
              <th>{{ t.access_type }}</th>
              <th class="text-center" style="width: 120px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_user_master_records_found }}</td>
            </tr>
            <tr v-for="user in rows" :key="user.userid">
              <td class="fw-semibold">{{ user.userid }}</td>
              <td>{{ user.username }}</td>
              <td>{{ user.usertypename }}</td>
              <td>{{ user.access_type }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('users')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="openView(user)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('users', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="openEdit(user)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('users', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  :title="t.delete"
                  @click="confirmingDelete = user"
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
          {{ t.showing }} {{ users.from ?? 0 }} {{ t.to }} {{ users.to ?? 0 }} {{ t.of }} {{ users.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!users.prev_page_url"
            @click="reloadList((users.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ users.current_page || 1 }} / {{ users.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!users.next_page_url"
            @click="reloadList((users.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div
    v-if="showModal"
    class="modal fade show d-block"
    tabindex="-1"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isViewing ? t.view_user_master : (isEditing ? t.edit_user_master : t.add_user_master) }}
          </h5>
          <button type="button" class="btn-close" @click="closeModal"></button>
        </div>

        <form @submit.prevent="submit">
          <div class="modal-body row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ t.code }}</label>
              <input :value="currentCode" class="form-control" readonly />
            </div>

            <div class="col-md-4">
              <label class="form-label">{{ t.username }} <span class="text-danger">*</span></label>
              <input
                v-model="form.username"
                type="text"
                maxlength="10"
                class="form-control"
                :class="{ 'is-invalid': form.errors.username }"
                :readonly="isViewing"
              />
              <div class="invalid-feedback">{{ form.errors.username }}</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">{{ t.email }}</label>
              <input
                v-model="form.email"
                type="email"
                maxlength="30"
                class="form-control"
                :class="{ 'is-invalid': form.errors.email }"
                :readonly="isViewing"
              />
              <div class="invalid-feedback">{{ form.errors.email }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">{{ t.password }} <span v-if="!isEditing && !isViewing" class="text-danger">*</span></label>
              <input
                v-model="form.password"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': form.errors.password }"
                :readonly="isViewing"
              />
              <div class="invalid-feedback">{{ form.errors.password }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">{{ t.confirm_password }}</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                class="form-control"
                :class="{ 'is-invalid': form.errors.password_confirmation }"
                :readonly="isViewing"
              />
              <div class="invalid-feedback">{{ form.errors.password_confirmation }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">{{ t.user_type }} <span class="text-danger">*</span></label>
              <select
                v-model="form.usertypeid"
                class="form-select"
                :class="{ 'is-invalid': form.errors.usertypeid }"
                :disabled="isViewing"
              >
                <option :value="null">- {{ t.select }} -</option>
                <option v-for="type in userTypes" :key="type.usertypeid" :value="type.usertypeid">
                  {{ type.usertypename }}
                </option>
              </select>
              <div class="invalid-feedback">{{ form.errors.usertypeid }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">{{ t.user_access }}</label>
              <select
                v-model="form.accesstypeid"
                class="form-select"
                :class="{ 'is-invalid': form.errors.accesstypeid }"
                :disabled="isViewing"
              >
                <option :value="null">- {{ t.select }} -</option>
                <option v-for="type in accessTypes" :key="type.id" :value="type.id">
                  {{ type.label }}
                </option>
              </select>
              <div class="invalid-feedback">{{ form.errors.accesstypeid }}</div>
            </div>

            <div class="col-12" v-if="form.accesstypeid">
              <label class="form-label">{{ t.access_scope }}</label>
              <select
                :key="accessScopeKey"
                v-model="form.access_ids"
                multiple
                size="8"
                class="form-select"
                :class="{ 'is-invalid': form.errors.access_ids }"
                :disabled="isViewing"
              >
                <option
                  v-for="option in currentAccessOptions"
                  :key="option.id"
                  :value="option.id"
                  :selected="isAccessSelected(option.id)"
                >
                  {{ option.label }}
                </option>
              </select>
              <div class="form-text">{{ t.use_ctrl_cmd_multiple }}</div>
              <div class="invalid-feedback d-block" v-if="form.errors.access_ids">{{ form.errors.access_ids }}</div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">{{ t.cancel }}</button>
            <button v-if="!isViewing" type="submit" class="btn btn-primary" :disabled="form.processing">
              <span v-if="form.processing">
                <i class="fa fa-circle-notch fa-spin me-1"></i> {{ t.saving }}
              </span>
              <span v-else>{{ isEditing ? t.update : t.create }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    tabindex="-1"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete_user_master }}
          </h5>
          <button type="button" class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.delete_user_confirm }} <strong>{{ confirmingDelete.username }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteUser">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

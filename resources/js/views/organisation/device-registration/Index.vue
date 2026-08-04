<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  devices: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.devices?.data ?? []);
const activeModal = ref(false);
const confirmingDelete = ref(null);
const { can } = usePermissions();
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  device_id: "",
  remarks: "",
});

function canViewAction(permission) {
  const details = page.props.auth?.formPermissions?.[permission];
  return !!(details?.all || details?.view || details?.read);
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

function reloadList(pageNumber = 1) {
  router.get(
    "/organisation/device-registration",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["devices", "filters"],
    },
  );
}

function openCreate() {
  form.reset();
  form.clearErrors();
  activeModal.value = true;
}

function closeModal() {
  activeModal.value = false;
  form.reset();
  form.clearErrors();
}

function submitCreate() {
  form.post("/organisation/device-registration", {
    preserveScroll: true,
    onSuccess: closeModal,
  });
}

function deleteRow() {
  router.delete(`/organisation/device-registration/${confirmingDelete.value.primary_key}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.device_registration" />

  <BasePageHeading :title="t.device_registration" :subtitle="t.manage_registered_devices">
    <template #extra>
      <button v-if="can('device registration', 'create')" class="btn btn-primary" @click="openCreate">
        <i class="fa fa-plus me-1"></i> {{ t.add }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.registered_devices">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="`${t.search}...`"
            style="width: 200px"
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
              <th>{{ t.id }}</th>
              <th>{{ t.device_id }}</th>
              <th>{{ t.remarks }}</th>
              <th class="text-center" style="width: 100px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.primary_key">
              <td class="text-muted">{{ (devices.from ?? 1) + index }}</td>
              <td>{{ record.primary_key }}</td>
              <td class="fw-semibold">{{ record.device_id }}</td>
              <td>{{ record.remarks || "-" }}</td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('device registration') && can('device registration', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  :title="t.delete_device_registration_label"
                  @click="confirmingDelete = record"
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
          {{ t.showing }} {{ devices.from ?? 0 }} {{ t.to }} {{ devices.to ?? 0 }} {{ t.of }} {{ devices.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!devices.prev_page_url"
            @click="reloadList((devices.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ devices.current_page || 1 }} / {{ devices.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!devices.next_page_url"
            @click="reloadList((devices.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
  </div>

  <div v-if="activeModal" class="modal fade show d-block" style="background: rgba(0,0,0,.45)">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t.add_device_registration }}</h5>
          <button class="btn-close" @click="closeModal"></button>
        </div>
        <form @submit.prevent="submitCreate">
          <div class="modal-body row g-3">
            <div class="col-12">
              <label class="form-label">{{ t.device_id }} <span class="text-danger">*</span></label>
              <input
                v-model="form.device_id"
                class="form-control"
                maxlength="50"
                :class="{ 'is-invalid': form.errors.device_id }"
              />
              <div class="invalid-feedback">{{ form.errors.device_id }}</div>
            </div>
            <div class="col-12">
              <label class="form-label">{{ t.remarks }} <span class="text-danger">*</span></label>
              <input
                v-model="form.remarks"
                class="form-control"
                maxlength="50"
                :class="{ 'is-invalid': form.errors.remarks }"
              />
              <div class="invalid-feedback">{{ form.errors.remarks }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-alt-secondary" @click="closeModal">{{ t.cancel }}</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ t.save }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div
    v-if="confirmingDelete"
    class="modal fade show d-block"
    style="background: rgba(0,0,0,.45)"
  >
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete_device_registration_label }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.device_delete_confirm }}
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete_device_registration_label }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  messages: Object,
  filters: Object,
});

const search = ref(props.filters?.search ?? "");
const perPage = ref(props.filters?.per_page ?? 10);
const rows = computed(() => props.messages?.data ?? []);
const confirmingDelete = ref(null);
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

function reloadList(pageNumber = 1) {
  router.get(
    "/account/customer-message",
    {
      search: search.value || undefined,
      per_page: perPage.value,
      page: pageNumber,
    },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ["messages", "filters"],
    },
  );
}

function deleteRow() {
  router.delete(`/account/customer-message/${confirmingDelete.value.messagekey}`, {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = null;
    },
  });
}
</script>

<template>
  <Head :title="t.customer_message" />

  <BasePageHeading :title="t.customer_message" :subtitle="t.customer_message_note">
    <template #extra>
      <button
        v-if="can('customer message', 'create')"
        class="btn btn-primary"
        @click="router.get('/account/customer-message/create')"
      >
        <i class="fa fa-plus me-1"></i> {{ t.create_customer_message }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.customer_message_overview">
      <template #options>
        <div class="d-flex gap-2">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="t.search"
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
              <th>#</th>
              <th>{{ t.message_key }}</th>
              <th>{{ t.message }}</th>
              <th>{{ t.alternate_code }}</th>
              <th>{{ t.status }}</th>
              <th class="text-center" style="width: 140px">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="6" class="text-center text-muted py-4">{{ t.no_records_found }}</td>
            </tr>
            <tr v-for="(record, index) in rows" :key="record.messagekey">
              <td class="text-muted">{{ (messages.from ?? 1) + index }}</td>
              <td>{{ record.messagekey }}</td>
              <td class="fw-semibold">{{ record.messagedescription }}</td>
              <td>{{ record.alternatecode || "-" }}</td>
              <td>
                <span
                  class="badge"
                  :class="record.activestatus ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                >
                  {{ record.activestatus ? t.status_active : t.status_inactive }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button
                  v-if="canViewAction('customer message')"
                  class="btn btn-sm btn-alt-info me-1"
                  :title="t.view"
                  @click="router.get(`/account/customer-message/${record.messagekey}`)"
                >
                  <i class="fa fa-eye"></i>
                </button>
                <button
                  v-if="can('customer message', 'edit')"
                  class="btn btn-sm btn-alt-secondary me-1"
                  :title="t.edit"
                  @click="router.get(`/account/customer-message/${record.messagekey}/edit`)"
                >
                  <i class="fa fa-pen"></i>
                </button>
                <button
                  v-if="can('customer message', 'delete')"
                  class="btn btn-sm btn-alt-danger"
                  :title="t.delete"
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
          {{ t.showing }} {{ messages.from ?? 0 }} {{ t.to }} {{ messages.to ?? 0 }} {{ t.of }}
          {{ messages.total ?? 0 }}
        </div>
        <div class="btn-group">
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!messages.prev_page_url"
            @click="reloadList((messages.current_page || 1) - 1)"
          >
            {{ t.previous }}
          </button>
          <button class="btn btn-sm btn-alt-secondary" disabled>
            {{ messages.current_page || 1 }} / {{ messages.last_page || 1 }}
          </button>
          <button
            class="btn btn-sm btn-alt-secondary"
            :disabled="!messages.next_page_url"
            @click="reloadList((messages.current_page || 1) + 1)"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </BaseBlock>
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
            <i class="fa fa-triangle-exclamation me-1"></i> {{ t.delete }}
          </h5>
          <button class="btn-close" @click="confirmingDelete = null"></button>
        </div>
        <div class="modal-body">
          {{ t.delete_message_confirm }} <strong>{{ confirmingDelete.messagedescription }}</strong>?
        </div>
        <div class="modal-footer">
          <button class="btn btn-alt-secondary" @click="confirmingDelete = null">{{ t.cancel }}</button>
          <button class="btn btn-danger" @click="deleteRow">{{ t.delete }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

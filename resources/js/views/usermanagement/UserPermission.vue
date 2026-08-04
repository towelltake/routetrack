<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import axios from "axios";

const props = defineProps({
  modules: Array,
  forms: Array,
  users: Array,
  userTypes: Array,
});
const page = usePage();
const t = page.props.translations.ui;

const permissionBy = ref("1");
const entityId = ref("");
const loading = ref(false);
const loaded = ref(false);
const saving = ref(false);
const searchTerm = ref("");
const selectedModules = ref(
  props.modules.map((module) => String(module.moduleid)),
);
const permsMap = ref({});

const entityList = computed(() =>
  permissionBy.value === "1" ? props.users : props.userTypes,
);
const entityKey = computed(() =>
  permissionBy.value === "1" ? "userid" : "usertypeid",
);
const entityLabel = computed(() =>
  permissionBy.value === "1" ? "username" : "usertypename",
);

function normalizedFormName(form) {
  return (form?.formname || "").trim().toLowerCase();
}

function displayFormDescription(form) {
  if (normalizedFormName(form) === "account customer authorize group") {
    return t.customer_authorize_group;
  }

  return form.formname || form.formdescription;
}

function supportsCreate(form) {
  return normalizedFormName(form) !== "user permission";
}

function supportsDelete(form) {
  return normalizedFormName(form) !== "user permission";
}

function isBasicCompanyForm(form) {
  const moduleName = (form?.modulename || "").trim().toLowerCase();
  const formName = normalizedFormName(form);
  const formDescription = (form?.formdescription || "").trim().toLowerCase();

  const isBasicModule = moduleName.includes("basic");
  const isCompanyForm =
    formName === "company" || formDescription === "manage companies";

  return isBasicModule && isCompanyForm;
}

const filteredForms = computed(() => {
  const selected = new Set(selectedModules.value);
  const search = searchTerm.value.trim().toLowerCase();

  const forms = props.forms
    .filter((form) => {
      const inModule = selected.has(String(form.moduleid));
      if (!inModule) {
        return false;
      }

      if (!search) {
        return true;
      }

      const moduleName = (form.modulename || "").toLowerCase();
      const formName = (form.formname || "").toLowerCase();
      const description = (form.formdescription || "").toLowerCase();

      return (
        moduleName.includes(search) ||
        formName.includes(search) ||
        description.includes(search)
      );
    })
    .slice()
    .sort((left, right) => {
      const leftRank = isBasicCompanyForm(left) ? 0 : 1;
      const rightRank = isBasicCompanyForm(right) ? 0 : 1;

      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      return 0;
    });

  const categoryIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer category",
  );
  const customerIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer",
  );
  const templateIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer template",
  );

  if (categoryIndex !== -1 && customerIndex !== -1 && customerIndex !== categoryIndex + 1) {
    const [customerForm] = forms.splice(customerIndex, 1);
    forms.splice(categoryIndex + 1, 0, customerForm);
  }

  const nextCustomerIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer",
  );
  const nextTemplateIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer template",
  );
  const authorizeGroupIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer authorize group",
  );

  if (
    nextCustomerIndex !== -1 &&
    nextTemplateIndex !== -1 &&
    nextTemplateIndex !== nextCustomerIndex + 1
  ) {
    const [templateForm] = forms.splice(nextTemplateIndex, 1);
    forms.splice(nextCustomerIndex + 1, 0, templateForm);
  }

  const nextReorderedTemplateIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer template",
  );
  const nextAuthorizeGroupIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer authorize group",
  );
  const customerSequenceIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer sequence",
  );

  if (
    nextReorderedTemplateIndex !== -1 &&
    nextAuthorizeGroupIndex !== -1 &&
    nextAuthorizeGroupIndex !== nextReorderedTemplateIndex + 1
  ) {
    const [authorizeGroupForm] = forms.splice(nextAuthorizeGroupIndex, 1);
    forms.splice(nextReorderedTemplateIndex + 1, 0, authorizeGroupForm);
  }

  const nextReorderedAuthorizeGroupIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer authorize group",
  );
  const nextCustomerSequenceIndex = forms.findIndex(
    (form) => normalizedFormName(form) === "account customer sequence",
  );

  if (
    nextReorderedAuthorizeGroupIndex !== -1 &&
    nextCustomerSequenceIndex !== -1 &&
    nextCustomerSequenceIndex !== nextReorderedAuthorizeGroupIndex + 1
  ) {
    const [customerSequenceForm] = forms.splice(nextCustomerSequenceIndex, 1);
    forms.splice(nextReorderedAuthorizeGroupIndex + 1, 0, customerSequenceForm);
  }

  return forms;
});

const allModulesSelected = computed(() => {
  return (
    props.modules.length > 0 &&
    selectedModules.value.length === props.modules.length
  );
});

watch(permissionBy, () => {
  entityId.value = "";
  permsMap.value = {};
  loaded.value = false;
});

watch(entityId, () => {
  permsMap.value = {};
  loaded.value = false;
  if (entityId.value) {
    loadPermissions();
  }
});

function defaultPerms() {
  return {
    read: false,
    view: false,
    create: false,
    write: false,
    delete: false,
    all: false,
  };
}

function ensurePerm(formid) {
  if (!permsMap.value[formid]) {
    permsMap.value[formid] = defaultPerms();
  }

  return permsMap.value[formid];
}

function syncAllFlag(formid) {
  const perm = ensurePerm(formid);
  const form = props.forms.find((item) => item.formid === formid);
  const createAllowed = form ? supportsCreate(form) : true;
  const deleteAllowed = form ? supportsDelete(form) : true;

  perm.all =
    perm.read &&
    perm.view &&
    perm.write &&
    (!createAllowed || perm.create) &&
    (!deleteAllowed || perm.delete);
}

function getPerm(formid, type) {
  return !!permsMap.value[formid]?.[type];
}

function setPerm(formid, type, checked) {
  const perm = ensurePerm(formid);
  const form = props.forms.find((item) => item.formid === formid);

  if (form) {
    if (type === "create" && !supportsCreate(form)) return;
    if (type === "delete" && !supportsDelete(form)) return;
  }

  perm[type] = checked;

  if (type === "all") {
    perm.read = checked;
    perm.view = checked;
    if (!form || supportsCreate(form)) {
      perm.create = checked;
    }
    perm.write = checked;
    if (!form || supportsDelete(form)) {
      perm.delete = checked;
    }
    return;
  }

  syncAllFlag(formid);
}

function toggleAllModules(checked) {
  selectedModules.value = checked
    ? props.modules.map((module) => String(module.moduleid))
    : [];
}

function toggleGlobal(type, checked) {
  filteredForms.value.forEach((form) => {
    setPerm(form.formid, type, checked);
  });
}

function isGlobalChecked(type) {
  return (
    filteredForms.value.length > 0 &&
    filteredForms.value.every((form) => getPerm(form.formid, type))
  );
}

async function loadPermissions() {
  if (!entityId.value) {
    return;
  }

  loading.value = true;

  try {
    const { data } = await axios.get("/usermanagement/user-permission/load", {
      params: {
        by: permissionBy.value,
        id: entityId.value,
      },
    });

    permsMap.value = data;
    loaded.value = true;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!entityId.value) {
    return;
  }

  saving.value = true;
  router.post("/usermanagement/user-permission/save", {
    permission_by: permissionBy.value,
    entity_id: entityId.value,
    permissions: permsMap.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: async () => {
      await loadPermissions();
    },
    onError: (errors) => {
      console.error(errors);
    },
    onFinish: () => {
      saving.value = false;
    },
  });
}
</script>

<template>
  <Head :title="t.user_permissions" />

  <BasePageHeading
    :title="t.user_permissions"
    :subtitle="t.user_permissions_note"
  />

  <div class="content">
    <BaseBlock :title="t.select_target">
      <div class="row g-3 align-items-end">
        <div class="col-md-3 mb-3">
          <label class="form-label fw-semibold">{{ t.permission_by }}</label>
          <select v-model="permissionBy" class="form-select">
            <option value="1">{{ t.users }}</option>
            <option value="2">{{ t.user_type }}</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label fw-semibold">
            {{ permissionBy === "1" ? t.choose_user : t.choose_user_type }}
          </label>
          <select v-model="entityId" class="form-select">
            <option value="">--- {{ t.select }} ---</option>
            <option
              v-for="entity in entityList"
              :key="entity[entityKey]"
              :value="String(entity[entityKey])"
            >
              {{ entity[entityLabel] }}
            </option>
          </select>
        </div>
        <div class="col-auto mb-3">
          <button
            class="btn btn-alt-secondary"
            :disabled="!entityId || loading"
            @click="loadPermissions"
          >
            <i class="fa fa-sync me-1" :class="{ 'fa-spin': loading }"></i>
            {{ loading ? t.loading : t.populate }}
          </button>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.modules">
      <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <label class="d-flex align-items-center gap-2 mb-0 fw-semibold">
          <input
            type="checkbox"
            :checked="allModulesSelected"
            @change="toggleAllModules($event.target.checked)"
          />
          {{ t.select_all_modules }}
        </label>
      </div>

      <div class="row g-2 mb-3">
        <div
          v-for="module in modules"
          :key="module.moduleid"
          class="col-sm-6 col-lg-3"
        >
          <label class="d-flex align-items-center gap-2 mb-0">
            <input
              v-model="selectedModules"
              type="checkbox"
              :value="String(module.moduleid)"
            />
            <span>{{ module.modulename }}</span>
          </label>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock :title="t.permissions">
      <template #options>
        <div class="permission-search">
          <input
            v-model="searchTerm"
            type="search"
            class="form-control"
            :placeholder="t.search_module_or_form"
          />
        </div>
      </template>

      <div v-if="!entityId" class="text-center text-muted py-5">
        {{ t.select_user_or_type_manage_permissions }}
      </div>

      <div v-else-if="loading" class="text-center text-muted py-5">
        <i class="fa fa-circle-notch fa-spin fa-2x"></i>
        <p class="mt-2 mb-0">{{ t.loading_permissions }}</p>
      </div>

      <template v-else>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th style="width: 25%">{{ t.module }}</th>
                <th style="width: 35%">{{ t.form }}</th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.read }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('read')"
                      @change="toggleGlobal('read', $event.target.checked)"
                    />
                  </div>
                </th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.view }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('view')"
                      @change="toggleGlobal('view', $event.target.checked)"
                    />
                  </div>
                </th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.create }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('create')"
                      @change="toggleGlobal('create', $event.target.checked)"
                    />
                  </div>
                </th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.edit }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('write')"
                      @change="toggleGlobal('write', $event.target.checked)"
                    />
                  </div>
                </th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.delete }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('delete')"
                      @change="toggleGlobal('delete', $event.target.checked)"
                    />
                  </div>
                </th>
                <th class="text-center" style="width: 8%">
                  <div class="permission-col">
                    <span>{{ t.all }}</span>
                    <input
                      type="checkbox"
                      :checked="isGlobalChecked('all')"
                      @change="toggleGlobal('all', $event.target.checked)"
                    />
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredForms.length === 0">
                <td colspan="8" class="text-center text-muted py-4">
                  {{ t.no_forms_matched_selected_modules_or_search }}
                </td>
              </tr>
              <tr v-for="form in filteredForms" :key="form.formid">
                <td>{{ form.modulename }}</td>
                <td>{{ displayFormDescription(form) }}</td>
                <td class="text-center">
                  <input
                    type="checkbox"
                    :checked="getPerm(form.formid, 'read')"
                    @change="
                      setPerm(form.formid, 'read', $event.target.checked)
                    "
                  />
                </td>
                <td class="text-center">
                  <input
                    type="checkbox"
                    :checked="getPerm(form.formid, 'view')"
                    @change="
                      setPerm(form.formid, 'view', $event.target.checked)
                    "
                  />
                </td>
                <td class="text-center">
                  <input
                    v-if="supportsCreate(form)"
                    type="checkbox"
                    :checked="getPerm(form.formid, 'create')"
                    @change="
                      setPerm(form.formid, 'create', $event.target.checked)
                    "
                  />
                </td>
                <td class="text-center">
                  <input
                    type="checkbox"
                    :checked="getPerm(form.formid, 'write')"
                    @change="
                      setPerm(form.formid, 'write', $event.target.checked)
                    "
                  />
                </td>
                <td class="text-center">
                  <input
                    v-if="supportsDelete(form)"
                    type="checkbox"
                    :checked="getPerm(form.formid, 'delete')"
                    @change="
                      setPerm(form.formid, 'delete', $event.target.checked)
                    "
                  />
                </td>
                <td class="text-center">
                  <input
                    type="checkbox"
                    :checked="getPerm(form.formid, 'all')"
                    @change="setPerm(form.formid, 'all', $event.target.checked)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3 mb-3">
          <button class="btn btn-primary px-4" :disabled="saving" @click="save">
            <i
              class="fa me-1"
              :class="saving ? 'fa-circle-notch fa-spin' : 'fa-save'"
            ></i>
            {{ saving ? t.saving : t.save_permissions }}
          </button>
        </div>
      </template>
    </BaseBlock>
  </div>
</template>

<style scoped>
.permission-search {
  min-width: 280px;
}

.permission-col {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
</style>

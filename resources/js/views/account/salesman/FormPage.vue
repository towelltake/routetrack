<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  salesmanData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() =>
  isCreate.value ? `${t.create} ${t.salesman}` : isView.value ? `${t.view} ${t.salesman}` : `${t.edit} ${t.salesman}`,
);

const form = useForm({
  ...props.salesmanData,
  userpassword_confirmation: props.salesmanData.userpassword ?? "",
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/account/salesman");
    return;
  }

  form.put(`/account/salesman/${form.salesmancode}`);
}

function formatDate(value) {
  if (!value) {
    return "-";
  }

  return new Date(value).toLocaleDateString("en-GB");
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.salesman_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/salesman')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/account/salesman/${form.salesmancode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button v-else class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4 mb-3">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.salesmancode" class="form-control" readonly />
        </div>

        <div class="col-md-5">
          <label class="form-label">{{ t.alternate_code }} <span class="text-danger">*</span></label>
          <input
            v-model="form.alternatesalesmancode"
            class="form-control"
            :readonly="isView"
          />
          <div v-if="form.errors.alternatesalesmancode" class="text-danger fs-sm mt-1">
            {{ form.errors.alternatesalesmancode }}
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.status }} <span class="text-danger">*</span></label>
          <select v-model="form.activestatus" class="form-select" :disabled="isView">
            <option
              v-for="option in optionSets.statusOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.name }} <span class="text-danger">*</span></label>
          <input v-model="form.salesmanname1" class="form-control" :readonly="isView" />
          <div v-if="form.errors.salesmanname1" class="text-danger fs-sm mt-1">
            {{ form.errors.salesmanname1 }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_name }}</label>
          <input
            v-model="form.arbsalesmanname1"
            class="form-control"
            :readonly="isView"
            dir="rtl"
          />
          <div v-if="form.errors.arbsalesmanname1" class="text-danger fs-sm mt-1">
            {{ form.errors.arbsalesmanname1 }}
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.contact_number }}</label>
          <input v-model="form.salesmanname2" class="form-control" :readonly="isView" />
          <div v-if="form.errors.salesmanname2" class="text-danger fs-sm mt-1">
            {{ form.errors.salesmanname2 }}
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.message_key }}</label>
          <select v-model="form.messagekey" class="form-select" :disabled="isView">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.messageOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.messagekey" class="text-danger fs-sm mt-1">
            {{ form.errors.messagekey }}
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.type }} <span class="text-danger">*</span></label>
          <select v-model="form.type" class="form-select" :disabled="isView">
            <option
              v-for="option in optionSets.typeOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.type" class="text-danger fs-sm mt-1">
            {{ form.errors.type }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.parent_company }} <span class="text-danger">*</span></label>
          <select v-model="form.parentcompany" class="form-select" :disabled="isView">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.parentCompanies"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
          <div v-if="form.errors.parentcompany" class="text-danger fs-sm mt-1">
            {{ form.errors.parentcompany }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.tablet_username }} <span class="text-danger">*</span></label>
          <input v-model="form.username" class="form-control" :readonly="isView" />
          <div v-if="form.errors.username" class="text-danger fs-sm mt-1">
            {{ form.errors.username }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.tablet_password }} <span class="text-danger">*</span></label>
          <input
            v-model="form.userpassword"
            type="password"
            class="form-control"
            :readonly="isView"
          />
          <div v-if="form.errors.userpassword" class="text-danger fs-sm mt-1">
            {{ form.errors.userpassword }}
          </div>
        </div>

        <div v-if="!isView" class="col-md-6">
          <label class="form-label">{{ t.confirm_password }} <span class="text-danger">*</span></label>
          <input
            v-model="form.userpassword_confirmation"
            type="password"
            class="form-control"
          />
        </div>

        <div v-if="!isCreate" class="col-md-4">
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

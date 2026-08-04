<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  channelData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() =>
  isCreate.value ? `${t.create} ${t.customer_channel}` : isView.value ? `${t.view} ${t.customer_channel}` : `${t.edit} ${t.customer_channel}`,
);

const form = useForm({
  ...props.channelData,
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/account/customer-channel");
    return;
  }

  form.put(`/account/customer-channel/${form.channelcode}`);
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
    :subtitle="t.customer_channel_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/customer-channel')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/account/customer-channel/${form.channelcode}/edit`)"
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
      <div class="row g-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.channelcode" class="form-control" readonly />
        </div>

        <div class="col-md-5">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
          <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">
            {{ form.errors.alternatecode }}
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
          <input v-model="form.channelname" class="form-control" :readonly="isView" />
          <div v-if="form.errors.channelname" class="text-danger fs-sm mt-1">
            {{ form.errors.channelname }}
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_name }}</label>
          <input
            v-model="form.arbchannelname"
            class="form-control"
            :readonly="isView"
            dir="rtl"
          />
          <div v-if="form.errors.arbchannelname" class="text-danger fs-sm mt-1">
            {{ form.errors.arbchannelname }}
          </div>
        </div>

        <div v-if="!isCreate" class="col-md-4">
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

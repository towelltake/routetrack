<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  mode: { type: String, required: true },
  messageData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() =>
  isCreate.value ? t.create_customer_message : isView.value ? t.view_customer_message : t.edit_customer_message,
);

const form = useForm({
  ...props.messageData,
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/account/customer-message");
    return;
  }

  form.put(`/account/customer-message/${form.messagekey}`);
}

</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading
    :title="pageTitle"
    :subtitle="t.customer_message_note"
  >
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get('/account/customer-message')">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView"
          class="btn btn-primary"
          @click="router.get(`/account/customer-message/${form.messagekey}/edit`)"
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
          <label class="form-label">{{ t.message_key }}</label>
          <input v-model="form.messagekey" class="form-control" readonly />
        </div>
        <div class="col-md-5">
          <label class="form-label">{{ t.message_description }} <span class="text-danger">*</span></label>
          <input
            v-model="form.messagedescription"
            class="form-control"
            :readonly="isView"
            :required="!isView"
          />
          <div v-if="form.errors.messagedescription" class="text-danger fs-sm mt-1">
            {{ form.errors.messagedescription }}
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

        <div class="col-md-4">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
          <div v-if="form.errors.alternatecode" class="text-danger fs-sm mt-1">
            {{ form.errors.alternatecode }}
          </div>
        </div>

        <div class="col-12">
          <h5 class="border-bottom pb-2 mb-3">{{ t.message_lines }}</h5>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.message_line_1 }}</label>
          <input v-model="form.messageline1" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_message_line_1 }}</label>
          <input v-model="form.arbmessageline1" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.message_line_2 }}</label>
          <input v-model="form.messageline2" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_message_line_2 }}</label>
          <input v-model="form.arbmessageline2" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.message_line_3 }}</label>
          <input v-model="form.messageline3" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_message_line_3 }}</label>
          <input v-model="form.arbmessageline3" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.message_line_4 }}</label>
          <input v-model="form.messageline4" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_message_line_4 }}</label>
          <input v-model="form.arbmessageline4" class="form-control" :readonly="isView" />
        </div>

      </div>
    </BaseBlock>
  </div>
</template>

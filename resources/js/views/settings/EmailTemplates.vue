<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  templates: { type: Array, required: true },
  purposeOptions: { type: Array, required: true },
  placeholders: { type: Object, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});
const { can } = usePermissions();

const blankTemplate = () => ({
  id: null,
  purpose: "password_reset",
  name: "",
  subject_en: "",
  subject_ar: "",
  body_en: "",
  body_ar: "",
  is_active: true,
});

const selectedId = ref(props.templates[0]?.id ?? null);

function currentTemplate() {
  return (
    props.templates.find((template) => template.id === selectedId.value) ??
    blankTemplate()
  );
}

function payloadFromTemplate(template) {
  return {
    id: template.id ?? null,
    purpose: template.purpose ?? "password_reset",
    name: template.name ?? "",
    subject_en: template.subject_en ?? "",
    subject_ar: template.subject_ar ?? "",
    body_en: template.body_en ?? "",
    body_ar: template.body_ar ?? "",
    is_active: Boolean(template.is_active),
  };
}

const form = useForm(payloadFromTemplate(currentTemplate()));
const permissionKey = "email templates";

function loadTemplate(template) {
  selectedId.value = template?.id ?? null;
  form.defaults(payloadFromTemplate(template ?? blankTemplate()));
  form.reset();
  form.clearErrors();
}

function startCreate() {
  selectedId.value = null;
  form.defaults(blankTemplate());
  form.reset();
  form.clearErrors();
}

const availablePlaceholders = computed(
  () => props.placeholders?.[form.purpose] ?? [],
);
const isExistingTemplate = computed(() => form.id !== null);
const canCreate = computed(() => can(permissionKey, "create"));
const canEdit = computed(() => can(permissionKey, "edit"));
const canDelete = computed(() => can(permissionKey, "delete"));
const canSaveCurrent = computed(() =>
  isExistingTemplate.value ? canEdit.value : canCreate.value,
);
const fieldsReadonly = computed(() => !canSaveCurrent.value);

const selectedPurposeLabel = computed(() => {
  const purpose = props.purposeOptions.find(
    (option) => option.value === form.purpose,
  );

  return purpose?.label ?? form.purpose;
});

const htmlSupportedNote = computed(
  () =>
    t.value.html_supported_note ??
    "HTML is supported. Example: <a href='[reset_url]'>Reset Password</a>",
);

function formatPlaceholder(placeholder) {
  return `{{ ${placeholder} }}`;
}

function submit() {
  const request = form.id
    ? form.put(`/settings/email-templates/${form.id}`, {
        preserveScroll: true,
      })
    : form.post("/settings/email-templates", {
        preserveScroll: true,
      });

  return request;
}

function removeTemplate() {
  if (!form.id) {
    return;
  }

  if (
    !window.confirm(
      t.value.delete_email_template_confirmation ??
        "Delete this email template?",
    )
  ) {
    return;
  }

  form.delete(`/settings/email-templates/${form.id}`, {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="t.email_templates ?? 'Email Templates'" />

  <BasePageHeading
    :title="t.email_templates ?? 'Email Templates'"
    :subtitle="
      t.email_templates_subtitle ??
      'Create and manage email templates for password reset and other email purposes.'
    "
  >
    <template #extra>
      <button v-if="canCreate" class="btn btn-alt-primary me-2" @click="startCreate">
        <i class="fa fa-plus me-1"></i>
        {{ t.new_template ?? "New Template" }}
      </button>
      <button
        v-if="canSaveCurrent"
        class="btn btn-primary"
        :disabled="form.processing"
        @click="submit"
      >
        <i class="fa fa-save me-1"></i>
        {{ t.save ?? "Save" }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div class="row g-4">
      <div class="col-xl-4">
        <BaseBlock :title="t.template_directory ?? 'Template Directory'">
          <div class="d-grid gap-2 mb-3">
            <button
              v-if="canCreate"
              type="button"
              class="btn btn-alt-primary text-start"
              :class="{ active: form.id === null }"
              @click="startCreate"
            >
              <i class="fa fa-plus me-2"></i>
              {{ t.create_email_template ?? "Create Email Template" }}
            </button>

            <button
              v-for="template in templates"
              :key="template.id"
              type="button"
              class="btn text-start email-template-list-item"
              :class="selectedId === template.id ? 'btn-primary' : 'btn-alt-secondary'"
              @click="loadTemplate(template)"
            >
              <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                  <div class="fw-semibold">{{ template.name }}</div>
                  <div class="fs-sm opacity-75">{{ template.purpose }}</div>
                </div>
                <span
                  :class="[
                    'badge rounded-pill',
                    template.is_active ? 'text-bg-success' : 'text-bg-secondary',
                  ]"
                >
                  {{ template.is_active ? (t.active ?? "Active") : (t.inactive ?? "Inactive") }}
                </span>
              </div>
            </button>
          </div>
        </BaseBlock>
      </div>

      <div class="col-xl-8">
        <BaseBlock :title="t.template_editor ?? 'Template Editor'">
          <div class="row g-4">
            <div class="col-lg-6">
              <label class="form-label">{{ t.template_name ?? "Template Name" }}</label>
              <input
                v-model="form.name"
                type="text"
                class="form-control"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.name }"
              >
              <div class="invalid-feedback">{{ form.errors.name }}</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">{{ t.email_purpose ?? "Email Purpose" }}</label>
              <input
                v-model="form.purpose"
                type="text"
                list="email-template-purposes"
                class="form-control"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.purpose }"
              >
              <datalist id="email-template-purposes">
                <option
                  v-for="purpose in purposeOptions"
                  :key="purpose.value"
                  :value="purpose.value"
                />
              </datalist>
              <div class="invalid-feedback">{{ form.errors.purpose }}</div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input
                  id="is_active"
                  v-model="form.is_active"
                  class="form-check-input"
                  type="checkbox"
                  :disabled="fieldsReadonly"
                >
                <label class="form-check-label" for="is_active">
                  {{ t.use_this_template_for_sending ?? "Use this template for sending emails" }}
                </label>
              </div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">{{ t.subject_english ?? "Subject (English)" }}</label>
              <input
                v-model="form.subject_en"
                type="text"
                class="form-control"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.subject_en }"
              >
              <div class="invalid-feedback">{{ form.errors.subject_en }}</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">{{ t.subject_arabic ?? "Subject (Arabic)" }}</label>
              <input
                v-model="form.subject_ar"
                type="text"
                class="form-control"
                dir="rtl"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.subject_ar }"
              >
              <div class="invalid-feedback">{{ form.errors.subject_ar }}</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">{{ t.body_english ?? "Body (English)" }}</label>
              <textarea
                v-model="form.body_en"
                rows="12"
                class="form-control"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.body_en }"
              ></textarea>
              <div class="form-text">
                {{ htmlSupportedNote }}
              </div>
              <div class="invalid-feedback">{{ form.errors.body_en }}</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">{{ t.body_arabic ?? "Body (Arabic)" }}</label>
              <textarea
                v-model="form.body_ar"
                rows="12"
                class="form-control"
                dir="rtl"
                :readonly="fieldsReadonly"
                :class="{ 'is-invalid': form.errors.body_ar }"
              ></textarea>
              <div class="form-text">
                {{ htmlSupportedNote }}
              </div>
              <div class="invalid-feedback">{{ form.errors.body_ar }}</div>
            </div>
          </div>

          <hr>

          <div class="row g-4 align-items-start">
            <div class="col-lg-8">
              <h5 class="mb-2">{{ t.available_placeholders ?? "Available Placeholders" }}</h5>
              <div class="d-flex flex-wrap gap-2">
                <span
                  v-for="placeholder in availablePlaceholders"
                  :key="placeholder"
                  class="badge text-bg-light border"
                >
                  {{ formatPlaceholder(placeholder) }}
                </span>
              </div>
              <div class="form-text mt-2">
                {{
                  t.template_purpose_note ??
                  "The selected purpose decides which placeholders are available and which outgoing email flow will use this template."
                }}
              </div>
            </div>

            <div class="col-lg-4 text-lg-end">
              <div class="small text-muted mb-2">
                {{ t.current_email_purpose ?? "Current purpose" }}:
                <strong>{{ t[form.purpose] ?? selectedPurposeLabel }}</strong>
              </div>
              <button
                v-if="form.id && canDelete"
                type="button"
                class="btn btn-alt-danger"
                :disabled="form.processing"
                @click="removeTemplate"
              >
                <i class="fa fa-trash me-1"></i>
                {{ t.delete_template ?? "Delete Template" }}
              </button>
            </div>
          </div>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

<style scoped>
.email-template-list-item {
  border: 1px solid rgba(203, 213, 225, 0.7);
}
</style>

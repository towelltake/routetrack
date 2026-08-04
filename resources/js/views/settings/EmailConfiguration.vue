<script setup>
import { computed } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  form: { type: Object, required: true },
  mailerOptions: { type: Array, required: true },
  encryptionOptions: { type: Array, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});
const { can } = usePermissions();
const canEdit = computed(() => can("email configuration", "edit"));

const form = useForm({
  id: props.form.id ?? null,
  mailer: props.form.mailer ?? "smtp",
  host: props.form.host ?? "",
  port: props.form.port ?? 587,
  username: props.form.username ?? "",
  password: props.form.password ?? "",
  encryption: props.form.encryption ?? "tls",
  from_address: props.form.from_address ?? "",
  from_name: props.form.from_name ?? "",
  is_active: Boolean(props.form.is_active),
});

const fieldsReadonly = computed(() => !canEdit.value);

function submit() {
  if (!canEdit.value) {
    return;
  }

  form.put("/settings/email-configuration", {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head :title="t.email_configuration ?? 'Email Configuration'" />

  <BasePageHeading
    :title="t.email_configuration ?? 'Email Configuration'"
    :subtitle="t.email_configuration_subtitle ?? 'Manage SMTP mailer, sender address, credentials, and encryption settings for outgoing emails.'"
  >
    <template #extra>
      <button
        v-if="canEdit"
        class="btn btn-primary"
        :disabled="form.processing"
        @click="submit"
      >
        <i class="fa fa-save me-1"></i> {{ t.save ?? "Save" }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.email_configuration ?? 'Email Configuration'">
      <div class="row g-4">
        <div class="col-lg-4">
          <label class="form-label">{{ t.mailer ?? "Mailer" }}</label>
          <select
            v-model="form.mailer"
            class="form-select"
            :disabled="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.mailer }"
          >
            <option v-for="option in mailerOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.mailer }}</div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.mail_host ?? "Mail Host" }}</label>
          <input
            v-model="form.host"
            type="text"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.host }"
          >
          <div class="invalid-feedback">{{ form.errors.host }}</div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.mail_port ?? "Mail Port" }}</label>
          <input
            v-model="form.port"
            type="number"
            min="1"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.port }"
          >
          <div class="invalid-feedback">{{ form.errors.port }}</div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.mail_username ?? "Mail Username" }}</label>
          <input
            v-model="form.username"
            type="text"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.username }"
          >
          <div class="invalid-feedback">{{ form.errors.username }}</div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.mail_password ?? "Mail Password" }}</label>
          <input
            v-model="form.password"
            type="password"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.password }"
          >
          <div class="invalid-feedback">{{ form.errors.password }}</div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">{{ t.mail_encryption ?? "Mail Encryption" }}</label>
          <select
            v-model="form.encryption"
            class="form-select"
            :disabled="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.encryption }"
          >
            <option v-for="option in encryptionOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <div class="invalid-feedback">{{ form.errors.encryption }}</div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.from_address ?? "From Address" }}</label>
          <input
            v-model="form.from_address"
            type="email"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.from_address }"
          >
          <div class="invalid-feedback">{{ form.errors.from_address }}</div>
        </div>

        <div class="col-lg-6">
          <label class="form-label">{{ t.from_name ?? "From Name" }}</label>
          <input
            v-model="form.from_name"
            type="text"
            class="form-control"
            :readonly="fieldsReadonly"
            :class="{ 'is-invalid': form.errors.from_name }"
          >
          <div class="invalid-feedback">{{ form.errors.from_name }}</div>
        </div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input
              id="mail_is_active"
              v-model="form.is_active"
              class="form-check-input"
              type="checkbox"
              :disabled="fieldsReadonly"
            >
            <label class="form-check-label" for="mail_is_active">
              {{ t.use_email_configuration_for_sending ?? "Use this email configuration for sending emails" }}
            </label>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed, onBeforeUpdate, onMounted, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);
const modalContainer = ref(null);
const modal = ref(null);
const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const form = useForm({
  password: "",
});

const confirmUserDeletion = () => {
  modal.value.show();
  confirmingUserDeletion.value = true;
};

const deleteUser = () => {
  form.delete("/profile", {
    onSuccess: () => closeModal(),
    onError: () => passwordInput.value.focus(),
    onFinish: () => form.reset(),
  });
};

const closeModal = (hideModal = true) => {
  if (hideModal) {
    modal.value.hide();
  }

  confirmingUserDeletion.value = false;
  form.clearErrors();
  form.reset();
};

onMounted(() => {
  modal.value = new bootstrap.Modal(modalContainer.value);

  modalContainer.value.addEventListener("shown.bs.modal", () => {
    passwordInput.value.focus();
  });
  modalContainer.value.addEventListener("hide.bs.modal", () => {
    closeModal(false);
  });
});

onBeforeUpdate(() => {
  if (modal.value) {
    modal.value.hide();
    modal.value.dispose();
  }
});
</script>

<template>
  <button type="button" class="btn btn-danger" @click="confirmUserDeletion">
    <i class="fa fa-fw fa-times opacity-50"></i>
    {{ t.delete_account ?? "Delete Account" }}
  </button>

  <div
    id="modal"
    ref="modalContainer"
    class="modal fade"
    tabindex="-1"
    role="dialog"
    aria-labelledby="modal"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-dialog-popout" role="document">
      <div class="modal-content">
        <BaseBlock :title="t.are_you_sure ?? 'Are you sure?'" transparent class="mb-0">
          <template #options>
            <button
              type="button"
              class="btn-block-option"
              data-bs-dismiss="modal"
              aria-label="Close"
            >
              <i class="fa fa-fw fa-times"></i>
            </button>
          </template>

          <template #content>
            <div class="block-content fs-sm">
              <p>
                {{ t.delete_account_confirmation_message ?? "Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account." }}
              </p>
              <div class="mb-4">
                <label class="form-label visually-hidden" for="password2">{{ t.password ?? "Password" }}</label>
                <input
                  id="password2"
                  ref="passwordInput"
                  v-model="form.password"
                  type="password"
                  class="form-control form-control-lg form-control-alt"
                  :class="{
                    'is-invalid': form.errors.password,
                  }"
                  :placeholder="t.password ?? 'Password'"
                  @keyup.enter="deleteUser"
                />
                <div v-show="form.errors.password" class="invalid-feedback">
                  {{ form.errors.password }}
                </div>
              </div>
            </div>
            <div
              class="block-content block-content-full bg-body d-flex justify-content-end gap-1"
            >
              <button
                type="button"
                class="btn btn-sm btn-alt-secondary me-1"
                data-bs-dismiss="modal"
              >
                {{ t.cancel ?? "Cancel" }}
              </button>
              <button
                type="button"
                class="btn btn-sm btn-danger"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="deleteUser"
              >
                {{ t.delete_account ?? "Delete Account" }}
              </button>
            </div>
          </template>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

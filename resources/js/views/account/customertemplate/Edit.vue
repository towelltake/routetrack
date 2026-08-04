<script setup>
import FormPage from "../customer/FormPage.vue";
import { usePage } from "@inertiajs/vue3";

defineProps({
  customerData: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <FormPage
    mode="edit"
    :customer-data="customerData"
    :option-sets="optionSets"
    :form-config="{
      titleBase: t.customer_template,
      backUrl: '/account/customer-template',
      submitBaseUrl: '/account/customer-template',
      subtitle: t.customer_template_form_note,
      showContactTab: false,
      showTemplateName: true,
      templateMode: true,
    }"
  />
</template>

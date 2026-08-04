<script setup>
import { Head, router, usePage, useForm } from "@inertiajs/vue3";

const props = defineProps({
  filters: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const t = usePage().props.translations.ui;
const form = useForm({
  routecode: props.filters.routecode,
  from_week: null,
  to_week: null,
  from_day: null,
  to_day: null,
});

function submit() {
  form.post("/account/customer-sequence/copy-sequence");
}
</script>

<template>
  <Head :title="t.copy_sequence" />

  <BasePageHeading
    :title="t.copy_sequence"
    :subtitle="t.copy_sequence_note"
  >
    <template #extra>
      <button
        class="btn btn-alt-secondary"
        @click="router.get('/account/customer-sequence')"
      >
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <div class="row g-4">
        <div class="col-md-6">
          <label class="form-label">{{ t.from_week }}</label>
          <select v-model="form.from_week" class="form-select">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.weekOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.to_week }}</label>
          <select v-model="form.to_week" class="form-select">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.weekOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.from_day }}</label>
          <select v-model="form.from_day" class="form-select">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.dayOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.to_day }}</label>
          <select v-model="form.to_day" class="form-select">
            <option :value="null">{{ t.select }}</option>
            <option
              v-for="option in optionSets.dayOptions"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="pt-4 mb-3">
        <button
          class="btn btn-primary"
          :disabled="form.processing"
          @click="submit"
        >
          <i class="fa fa-copy me-1"></i> {{ t.copy_sequence }}
        </button>
      </div>
    </BaseBlock>
  </div>
</template>

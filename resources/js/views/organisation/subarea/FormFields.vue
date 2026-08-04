<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: { type: Object, required: true },
  areas: { type: Array, required: true },
  supervisors: { type: Array, required: true },
  isViewing: { type: Boolean, default: false },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternatesubareacode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternatesubareacode }"
        maxlength="30"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternatesubareacode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.sub_area_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.subareaname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.subareaname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.subareaname }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arb_sub_area_name }}</label>
      <input
        v-model="form.arbsubareaname"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.area }} <span class="text-danger">*</span></label>
      <select
        v-model="form.areacode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.areacode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="area in areas" :key="area.areacode" :value="area.areacode">
          {{ area.areaname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.areacode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.supervisor }} <span class="text-danger">*</span></label>
      <select
        v-model="form.supervisorcode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.supervisorcode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="supervisor in supervisors"
          :key="supervisor.supervisorcode"
          :value="supervisor.supervisorcode"
        >
          {{ supervisor.supervisorname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.supervisorcode }}</div>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input
          id="subarea-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="subarea-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>

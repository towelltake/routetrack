<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: { type: Object, required: true },
  depots: { type: Array, required: true },
  areaManagers: { type: Array, required: true },
  isViewing: { type: Boolean, default: false },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternateareacode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternateareacode }"
        maxlength="30"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternateareacode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.area_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.areaname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.areaname }"
        maxlength="30"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.areaname }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arb_area_name }}</label>
      <input
        v-model="form.arbareaname"
        class="form-control"
        dir="rtl"
        maxlength="30"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.branch_depot }} <span class="text-danger">*</span></label>
      <select
        v-model="form.depotcode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.depotcode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="depot in depots" :key="depot.depotcode" :value="depot.depotcode">
          {{ depot.depotname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.depotcode }}</div>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.area_manager }} <span class="text-danger">*</span></label>
      <select
        v-model="form.areamanagercode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.areamanagercode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="manager in areaManagers"
          :key="manager.areamanagercode"
          :value="manager.areamanagercode"
        >
          {{ manager.areamanagername }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.areamanagercode }}</div>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input
          id="area-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="area-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>

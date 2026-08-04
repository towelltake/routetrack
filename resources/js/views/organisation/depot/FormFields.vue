<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: { type: Object, required: true },
  branchManagers: { type: Array, required: true },
  companies: { type: Array, required: true },
  regions: { type: Array, required: true },
  pricingKeys: { type: Array, required: true },
  isViewing: { type: Boolean, default: false },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.branch_depot_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.depotname"
        class="form-control"
        :class="{ 'is-invalid': form.errors.depotname }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.depotname }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arab_depot_name }}</label>
      <input
        v-model="form.arbdepotname"
        class="form-control"
        dir="rtl"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.alternate_code }}</label>
      <input
        v-model="form.alternatedepotcode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternatedepotcode }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternatedepotcode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.branch_depot_manager }} <span class="text-danger">*</span></label>
      <select
        v-model="form.branchmanagercode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.branchmanagercode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="manager in branchManagers"
          :key="manager.branchmanagercode"
          :value="manager.branchmanagercode"
        >
          {{ manager.branchmanagername }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.branchmanagercode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.company }} <span class="text-danger">*</span></label>
      <select
        v-model="form.cmpycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.cmpycode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="company in companies" :key="company.cmpycode" :value="company.cmpycode">
          {{ company.name }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.cmpycode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.region }} <span class="text-danger">*</span></label>
      <select
        v-model="form.regionmstcode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.regionmstcode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="region in regions" :key="region.regionmstcode" :value="region.regionmstcode">
          {{ region.regionmstname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.regionmstcode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.branch_depot_prefix }}</label>
      <input
        v-model="form.depotprefix"
        type="number"
        min="0"
        step="1"
        class="form-control"
        :class="{ 'is-invalid': form.errors.depotprefix }"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.depotprefix }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.price_key }}</label>
      <select
        v-model="form.pricingkey"
        class="form-select"
        :class="{ 'is-invalid': form.errors.pricingkey }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option v-for="price in pricingKeys" :key="price.pricingkey" :value="price.pricingkey">
          {{ price.description }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.pricingkey }}</div>
    </div>

    <div class="col-12 d-flex align-items-center gap-4">
      <div class="form-check">
        <input
          id="depot-centralwh"
          v-model="form.centralwh"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="depot-centralwh">{{ t.central_warehouse }}</label>
      </div>

      <div class="form-check">
        <input
          id="depot-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="depot-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>

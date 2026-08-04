<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: {
    type: Object,
    required: true,
  },
  countries: {
    type: Array,
    required: true,
  },
  companyOptions: {
    type: Array,
    required: true,
  },
  isEditing: {
    type: Boolean,
    default: false,
  },
  isViewing: {
    type: Boolean,
    default: false,
  },
  editingId: {
    type: [Number, String, null],
    default: null,
  },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.company_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.name"
        class="form-control"
        :class="{ 'is-invalid': form.errors.name }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.name }}</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ t.arab_company_name }}</label>
      <input
        v-model="form.arbcompanyname"
        class="form-control"
        dir="rtl"
        maxlength="100"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.country }} <span class="text-danger">*</span></label>
      <select
        v-model="form.countrycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.countrycode }"
        :disabled="isViewing"
      >
        <option :value="null">- {{ t.select }} -</option>
        <option
          v-for="country in countries"
          :key="country.countrycode"
          :value="country.countrycode"
        >
          {{ country.countryname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.countrycode }}</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ t.parent_company }}</label>
      <select v-model="form.parentcompany" class="form-select" :disabled="isViewing">
        <option :value="null">- {{ t.none }} -</option>
        <option
          v-for="company in companyOptions"
          :key="company.cmpycode"
          :value="company.cmpycode"
          :disabled="isEditing && company.cmpycode === editingId"
        >
          {{ company.name }}
        </option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ t.contact_name }}</label>
      <input
        v-model="form.contactname"
        class="form-control"
        maxlength="40"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.telephone }} <span class="text-danger">*</span></label>
      <input
        v-model="form.telephone"
        class="form-control"
        :class="{ 'is-invalid': form.errors.telephone }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.telephone }}</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ t.fax }}</label>
      <input
        v-model="form.fax"
        class="form-control"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ t.zip_code }}</label>
      <input
        v-model="form.zipcode"
        class="form-control"
        maxlength="20"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ t.address }} <span class="text-danger">*</span></label>
      <input
        v-model="form.address"
        class="form-control"
        :class="{ 'is-invalid': form.errors.address }"
        maxlength="255"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.address }}</div>
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ t.tax_registration_no }}</label>
      <input
        v-model="form.taxregistrationnumber"
        class="form-control"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ t.distributor_code }}</label>
      <input
        v-model="form.distributorcode"
        class="form-control"
        maxlength="10"
        :readonly="isViewing"
      />
    </div>

    <div class="col-12 d-flex align-items-center">
      <div class="form-check">
        <input
          id="company-active"
          v-model="form.activestatus"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="company-active">{{ t.status_active }}</label>
      </div>
    </div>
  </div>
</template>

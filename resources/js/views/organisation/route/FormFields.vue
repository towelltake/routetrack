<script setup>
import { usePage } from "@inertiajs/vue3";

defineProps({
  form: { type: Object, required: true },
  companies: { type: Array, required: true },
  regions: { type: Array, required: true },
  subAreas: { type: Array, required: true },
  salesmen: { type: Array, required: true },
  vans: { type: Array, required: true },
  routeCategories: { type: Array, required: true },
  routeItemGroups: { type: Array, required: true },
  routeTypes: { type: Array, required: true },
  isViewing: { type: Boolean, default: false },
});

const t = usePage().props.translations.ui;
</script>

<template>
  <div class="modal-body row g-3">
    <div class="col-md-4">
      <label class="form-label">{{ t.alternate_code }} <span class="text-danger">*</span></label>
      <input
        v-model="form.alternateroutecode"
        class="form-control"
        :class="{ 'is-invalid': form.errors.alternateroutecode }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.alternateroutecode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.route_name }} <span class="text-danger">*</span></label>
      <input
        v-model="form.routename"
        class="form-control"
        :class="{ 'is-invalid': form.errors.routename }"
        maxlength="50"
        :readonly="isViewing"
      />
      <div class="invalid-feedback">{{ form.errors.routename }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.arab_route_name }}</label>
      <input
        v-model="form.arbroutename"
        class="form-control"
        maxlength="50"
        dir="rtl"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.company }} <span class="text-danger">*</span></label>
      <select
        v-model="form.cmpycode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.cmpycode }"
        :disabled="isViewing"
      >
        <option :value="null">{{ t.select }}</option>
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
        <option :value="null">{{ t.select }}</option>
        <option v-for="region in regions" :key="region.regionmstcode" :value="region.regionmstcode">
          {{ region.regionmstname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.regionmstcode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.sub_area }} <span class="text-danger">*</span></label>
      <select
        v-model="form.subareacode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.subareacode }"
        :disabled="isViewing"
      >
        <option :value="null">{{ t.select }}</option>
        <option v-for="subArea in subAreas" :key="subArea.subareacode" :value="subArea.subareacode">
          {{ subArea.subareaname }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.subareacode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.salesman }} <span class="text-danger">*</span></label>
      <select
        v-model="form.salesmancode"
        class="form-select"
        :class="{ 'is-invalid': form.errors.salesmancode }"
        :disabled="isViewing"
      >
        <option :value="null">{{ t.select }}</option>
        <option v-for="salesman in salesmen" :key="salesman.salesmancode" :value="salesman.salesmancode">
          {{ salesman.salesmanname1 }}
        </option>
      </select>
      <div class="invalid-feedback">{{ form.errors.salesmancode }}</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.van }}</label>
      <select v-model="form.vehiclenumber" class="form-select" :disabled="isViewing">
        <option :value="null">{{ t.select }}</option>
        <option v-for="van in vans" :key="van.vancode" :value="van.vancode">
          {{ van.vandescription }}
        </option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.route_category }}</label>
      <select v-model="form.routecatcode" class="form-select" :disabled="isViewing">
        <option :value="null">{{ t.select }}</option>
        <option
          v-for="routeCategory in routeCategories"
          :key="routeCategory.routecatcode"
          :value="routeCategory.routecatcode"
        >
          {{ routeCategory.routecatname }}
        </option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.route_type }}</label>
      <select v-model="form.routetype" class="form-select" :disabled="isViewing">
        <option v-for="routeType in routeTypes" :key="routeType.id" :value="routeType.id">
          {{ routeType.val }}
        </option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.route_item_group }}</label>
      <select v-model="form.routeitemgrpcode" class="form-select" :disabled="isViewing">
        <option :value="null">{{ t.select }}</option>
        <option
          v-for="routeItemGroup in routeItemGroups"
          :key="routeItemGroup.routeitemgrpcode"
          :value="routeItemGroup.routeitemgrpcode"
        >
          {{ routeItemGroup.description }}
        </option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.device_assigned }}</label>
      <input
        v-model="form.device_assigned_id"
        class="form-control"
        maxlength="50"
        :readonly="isViewing"
      />
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ t.status }}</label>
      <select v-model="form.activestatus" class="form-select" :disabled="isViewing">
        <option :value="1">{{ t.status_active }}</option>
        <option :value="0">{{ t.status_inactive }}</option>
      </select>
    </div>

    <div class="col-md-6">
      <div class="form-check mt-4">
        <input
          id="route-presales"
          v-model="form.presalesorder"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="route-presales">{{ t.allow_change_salesman }}</label>
      </div>
    </div>

    <div class="col-md-6">
      <div class="form-check mt-4">
        <input
          id="route-depotroute"
          v-model="form.depotrouteflag"
          :true-value="1"
          :false-value="0"
          type="checkbox"
          class="form-check-input"
          :disabled="isViewing"
        />
        <label class="form-check-label" for="route-depotroute">{{ t.depot_route }}</label>
      </div>
    </div>
  </div>
</template>

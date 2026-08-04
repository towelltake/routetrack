<script setup>
import { computed, ref, watch } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  tabs: { type: Array, required: true },
  form: { type: Object, required: true },
  meta: { type: Object, required: true },
});

const page = usePage();
const t = computed(() => page.props.translations?.ui ?? {});

const activeTab = ref("general");

function payloadFromProps(serverForm) {
  return {
    flags: {
      general: [...(serverForm.flags?.general ?? [])],
      route: [...(serverForm.flags?.route ?? [])],
      customer: [...(serverForm.flags?.customer ?? [])],
      item: [...(serverForm.flags?.item ?? [])],
    },
    startingLoadMethod: Number(serverForm.startingLoadMethod ?? 0),
    customerCodeGeneration:
      serverForm.customerCodeGeneration ?? "Customer Code With Route",
    pdcClearance:
      serverForm.pdcClearance ?? "PDC Clearance With CashierReceipt",
    depotInventory: serverForm.depotInventory ?? "Standard Depot Inventory",
    costPricePercent: String(serverForm.costPricePercent ?? "0"),
    monthClosingTime:
      typeof serverForm.monthClosingTime === "string"
        ? serverForm.monthClosingTime.slice(0, 5)
        : "18:00",
  };
}

const form = useForm(payloadFromProps(props.form));

watch(
  () => props.form,
  (nextForm) => {
    form.defaults(payloadFromProps(nextForm));

    if (!form.hasErrors) {
      form.reset();
    }
  },
  { deep: true },
);

const activeTabItems = computed(
  () => props.tabs.find((tab) => tab.key === activeTab.value)?.items ?? [],
);

const sectionOrder = {
  general: ["General", "Accounts", "Depot Inventory"],
  route: ["Route", "Starting Load"],
  customer: ["Customer", "Code Generation"],
  item: ["General", "Cost Price"],
};

const tabLabelKeys = {
  General: "general",
  Route: "route",
  Customer: "customer",
  Items: "items",
};

const sectionLabelKeys = {
  General: "general",
  Accounts: "account",
  "Depot Inventory": "depot_inventory",
  Route: "route",
  "Starting Load": "starting_load",
  Customer: "customer",
  "Code Generation": "code_generation",
  "Cost Price": "cost_price",
};

const itemSectionMap = {
  "Use Password Generator": "General",
  "Enable Customer Free Contracts": "General",
  "Use Suggested Sales": "General",
  "Enable Discount Key (Item Level Ranged Discounts)": "General",
  "Enable Authorized Item Group": "General",
  "Enable Distribution Key (Item Level Case Price Discounts)": "General",
  "Enable Outlet Product Code": "General",
  "Depot Damage Expiry": "General",
  "Use Alternate Code In Backoffice (Customer & Item)": "General",
  "Damage/Expiry Management": "General",
  "Use Alternate Code For Pending Invoices": "General",
  "Use Fixed Qualification And Fixed Assignment": "General",
  "Download Other Route Pending Invoices": "General",
  "Use Ranged Qualification On Fixed Assignment": "General",
  "Post PDC Collected As Cash": "General",
  "Get PDC Amount From CI": "General",
  "Post Collection With Respect To Invoiced Salesman": "General",
  "Get PDC Amount From ARD": "General",
  "Enable Transaction Posting In Cloud": "General",
  "Enable Tax": "General",
  "Enable Month Closing": "General",
  "Closing Time(HH:MM)": "General",
  "PDC Clearance With CashierReceipt": "Accounts",
  "PDC Clearance WithOut CashierReceipt": "Accounts",
  Standard: "Depot Inventory",
  Advance: "Depot Inventory",
  "Enable Company And Region": "Route",
  "Allowed Radius (For GPS Limits)": "Route",
  "Enable Item Must List": "Route",
  "Import Sync With Salesman Load": "Route",
  "Daily Salesman Load Generation Method": "Starting Load",
  "Use Grace Period (For Credit Limit Days)": "Customer",
  "Create Route Sequence For New Customers": "Customer",
  "Use Journey Plan Credit Limit (Route Wise Limit For Customer)": "Customer",
  "Show Additional Customer Details During Creation": "Customer",
  "Use Credit Check Exception": "Customer",
  "Enabled Channel Master": "Customer",
  "With Route Code": "Code Generation",
  "With Depot Code": "Code Generation",
  "With Route And Depot Code": "Code Generation",
  "With Depot And Route Code": "Code Generation",
  "Enabled Batch And Expiry": "General",
  "Add New Items To Route Item Grouping": "General",
  "Enable Cost Price": "Cost Price",
  "Cost Price Calculation %": "Cost Price",
};

const itemLabelKeys = {
  "Use Password Generator": "use_password_generator",
  "Enable Customer Free Contracts": "enable_customer_free_contracts",
  "Use Suggested Sales": "use_suggested_sales",
  "Enable Discount Key (Item Level Ranged Discounts)": "enable_discount_key_item_level_ranged_discounts",
  "Enable Authorized Item Group": "enable_authorized_item_group",
  "Enable Distribution Key (Item Level Case Price Discounts)": "enable_distribution_key_item_level_case_price_discounts",
  "Enable Outlet Product Code": "enable_outlet_product_code",
  "Depot Damage Expiry": "depot_damage_expiry",
  "Use Alternate Code In Backoffice (Customer & Item)": "use_alternate_code_in_backoffice_customer_item",
  "Damage/Expiry Management": "damage_expiry_management",
  "Use Alternate Code For Pending Invoices": "use_alternate_code_for_pending_invoices",
  "Use Fixed Qualification And Fixed Assignment": "use_fixed_qualification_and_fixed_assignment",
  "Download Other Route Pending Invoices": "download_other_route_pending_invoices",
  "Use Ranged Qualification On Fixed Assignment": "use_ranged_qualification_on_fixed_assignment",
  "Post PDC Collected As Cash": "post_pdc_collected_as_cash",
  "Get PDC Amount From CI": "get_pdc_amount_from_ci",
  "Post Collection With Respect To Invoiced Salesman": "post_collection_with_respect_to_invoiced_salesman",
  "Get PDC Amount From ARD": "get_pdc_amount_from_ard",
  "Enable Transaction Posting In Cloud": "enable_transaction_posting_in_cloud",
  "Enable Tax": "enable_tax",
  "Enable Month Closing": "enable_month_closing",
  "Closing Time(HH:MM)": "closing_time_hh_mm",
  "PDC Clearance With CashierReceipt": "pdc_clearance_with_cashier_receipt",
  "PDC Clearance WithOut CashierReceipt": "pdc_clearance_without_cashier_receipt",
  Standard: "standard",
  Advance: "advance",
  "Enable Company And Region": "enable_company_and_region",
  "Allowed Radius (For GPS Limits)": "allowed_radius_for_gps_limits",
  "Enable Item Must List": "enable_item_must_list",
  "Import Sync With Salesman Load": "import_sync_with_salesman_load",
  "Daily Salesman Load Generation Method": "daily_salesman_load_generation_method",
  "Use Grace Period (For Credit Limit Days)": "use_grace_period_for_credit_limit_days",
  "Create Route Sequence For New Customers": "create_route_sequence_for_new_customers",
  "Use Journey Plan Credit Limit (Route Wise Limit For Customer)": "use_journey_plan_credit_limit_route_wise_limit_for_customer",
  "Show Additional Customer Details During Creation": "show_additional_customer_details_during_creation",
  "Use Credit Check Exception": "use_credit_check_exception",
  "Enabled Channel Master": "enabled_channel_master",
  "With Route Code": "with_route_code",
  "With Depot Code": "with_depot_code",
  "With Route And Depot Code": "with_route_and_depot_code",
  "With Depot And Route Code": "with_depot_and_route_code",
  "Enabled Batch And Expiry": "enabled_batch_and_expiry",
  "Add New Items To Route Item Grouping": "add_new_items_to_route_item_grouping",
  "Enable Cost Price": "enable_cost_price",
  "Cost Price Calculation %": "cost_price_calculation_percent",
};

const optionLabelKeys = {
  "Create New Load": "create_new_load",
  "Load Imported From ERP": "load_imported_from_erp",
  "Convert Load Request to Load": "convert_load_request_to_load",
  "Sales order to load": "sales_order_to_load",
  "Populate Previous day load": "populate_previous_day_load",
  "Use Suggested Load": "use_suggested_load",
};

function translateTabLabel(label) {
  const key = tabLabelKeys[label];

  return key ? (t.value[key] ?? label) : label;
}

function translateSectionLabel(label) {
  const key = sectionLabelKeys[label];

  return key ? (t.value[key] ?? label) : label;
}

function translateItemLabel(label) {
  const key = itemLabelKeys[label];

  return key ? (t.value[key] ?? label) : label;
}

function translateOptionLabel(label) {
  const key = optionLabelKeys[label];

  return key ? (t.value[key] ?? label) : label;
}

const activeSections = computed(() => {
  const grouped = new Map();

  for (const item of activeTabItems.value) {
    const sectionLabel = itemSectionMap[item.label] ?? "General";

    if (!grouped.has(sectionLabel)) {
      grouped.set(sectionLabel, []);
    }

    grouped.get(sectionLabel).push(item);
  }

  return (sectionOrder[activeTab.value] ?? [])
    .filter((label) => grouped.has(label))
    .map((label) => ({
      label,
      items: grouped.get(label),
    }));
});

const discountKeyName = "Discount Key";
const distributionKeyName = "Distribution Key";
const enableCostPriceName = "Enable Cost Price";
const enableMonthCloseName = "Month Close";

function toggleFlag(groupKey, flagName, checked) {
  const next = new Set(form.flags[groupKey]);

  if (checked) {
    next.add(flagName);
  } else {
    next.delete(flagName);
  }

  if (groupKey === "general" && flagName === discountKeyName && checked) {
    next.delete(distributionKeyName);
  }

  if (groupKey === "general" && flagName === distributionKeyName && checked) {
    next.delete(discountKeyName);
  }

  form.flags[groupKey] = [...next];
}

function isChecked(groupKey, flagName) {
  return form.flags[groupKey].includes(flagName);
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      costPricePercent:
        data.costPricePercent === "" ? "0" : data.costPricePercent,
      monthClosingTime:
        data.monthClosingTime === "" ? "18:00" : data.monthClosingTime,
    }))
    .put("/settings/control-panel", {
      preserveScroll: true,
    });
}

function costPriceEnabled() {
  return isChecked("item", enableCostPriceName);
}

function monthCloseEnabled() {
  return isChecked("general", enableMonthCloseName);
}

function isDepotInventoryChecked(name) {
  return form.depotInventory === name;
}

function toggleDepotInventory(name, checked) {
  if (checked) {
    form.depotInventory = name;
    return;
  }

  if (form.depotInventory === name) {
    form.depotInventory =
      name === "Standard Depot Inventory"
        ? "Advanced Depot Inventory"
        : "Standard Depot Inventory";
  }
}
</script>

<template>
  <Head :title="t.control_panel ?? 'Control Panel'" />

  <BasePageHeading
    :title="t.control_panel ?? 'Control Panel'"
    :subtitle="t.control_panel_subtitle ?? 'Configure transaction rules, account behavior, depot inventory options, and related control settings.'"
  >
    <template #extra>
      <button
        class="btn btn-primary"
        :disabled="form.processing"
        @click="submit"
      >
        <i class="fa fa-save me-1"></i> {{ t.save ?? "Save" }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock>
      <ul class="nav nav-tabs nav-tabs-block">
        <li v-for="tab in tabs" :key="tab.key" class="nav-item">
          <button
            type="button"
            :class="['nav-link', activeTab === tab.key ? 'active' : '']"
            @click="activeTab = tab.key"
          >
            {{ translateTabLabel(tab.label) }}
          </button>
        </li>
      </ul>

      <div class="block-content pt-4">
        <div v-if="Object.keys(form.errors).length" class="alert alert-danger">
          <div v-if="form.errors.flags" class="mb-1">{{ form.errors.flags }}</div>
          <div v-if="form.errors['flags.general']" class="mb-1">{{ form.errors["flags.general"] }}</div>
          <div v-if="form.errors['flags.route']" class="mb-1">{{ form.errors["flags.route"] }}</div>
          <div v-if="form.errors['flags.customer']" class="mb-1">{{ form.errors["flags.customer"] }}</div>
          <div v-if="form.errors['flags.item']" class="mb-1">{{ form.errors["flags.item"] }}</div>
          <div v-if="form.errors.startingLoadMethod" class="mb-1">{{ form.errors.startingLoadMethod }}</div>
          <div v-if="form.errors.customerCodeGeneration" class="mb-1">{{ form.errors.customerCodeGeneration }}</div>
          <div v-if="form.errors.pdcClearance" class="mb-1">{{ form.errors.pdcClearance }}</div>
          <div v-if="form.errors.depotInventory" class="mb-1">{{ form.errors.depotInventory }}</div>
          <div v-if="form.errors.costPricePercent" class="mb-1">{{ form.errors.costPricePercent }}</div>
          <div v-if="form.errors.monthClosingTime">{{ form.errors.monthClosingTime }}</div>
        </div>

        <div v-for="section in activeSections" :key="section.label" class="mb-4">
          <fieldset class="border rounded p-3">
            <legend class="float-none w-auto px-2 fs-sm fw-semibold mb-0">
              {{ translateSectionLabel(section.label) }}
            </legend>

            <div class="row g-4 mt-1">
              <template v-for="item in section.items" :key="item.name">
                <div v-if="item.type === 'checkbox'" class="col-lg-6">
                  <div class="form-check form-switch">
                    <input
                      :id="item.name"
                      class="form-check-input"
                      type="checkbox"
                      :checked="isChecked(activeTab, item.name)"
                      @change="toggleFlag(activeTab, item.name, $event.target.checked)"
                    />
                    <label class="form-check-label" :for="item.name">{{ translateItemLabel(item.label) }}</label>
                  </div>
                </div>

                <div
                  v-else-if="item.type === 'radio' && item.group === 'depot_inventory'"
                  class="col-lg-6"
                >
                  <div class="form-check form-switch">
                    <input
                      :id="item.name"
                      class="form-check-input"
                      type="checkbox"
                      :checked="isDepotInventoryChecked(item.name)"
                      @change="toggleDepotInventory(item.name, $event.target.checked)"
                    />
                    <label class="form-check-label" :for="item.name">{{ translateItemLabel(item.label) }}</label>
                  </div>
                </div>

                <div v-else-if="item.type === 'radio'" class="col-lg-6">
                  <div class="form-check">
                    <input
                      :id="item.name"
                      class="form-check-input"
                      type="radio"
                      :name="item.group"
                      :value="item.name"
                      :checked="
                        item.group === 'customer_code_generation'
                          ? form.customerCodeGeneration === item.name
                          : item.group === 'pdc_clearance'
                            ? form.pdcClearance === item.name
                            : form.depotInventory === item.name
                      "
                      @change="
                        item.group === 'customer_code_generation'
                          ? (form.customerCodeGeneration = item.name)
                          : item.group === 'pdc_clearance'
                            ? (form.pdcClearance = item.name)
                            : (form.depotInventory = item.name)
                      "
                    />
                    <label class="form-check-label" :for="item.name">{{ translateItemLabel(item.label) }}</label>
                  </div>
                </div>

                <div v-else-if="item.type === 'select'" class="col-lg-6">
                  <label class="form-label">{{ translateItemLabel(item.label) }}</label>
                  <select v-model="form.startingLoadMethod" class="form-select">
                    <option
                      v-for="option in item.options"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ translateOptionLabel(option.label) }}
                    </option>
                  </select>
                </div>

                <div v-else-if="item.name === 'Cost Price Percent'" class="col-lg-6">
                  <label class="form-label">{{ translateItemLabel(item.label) }}</label>
                  <input
                    v-model="form.costPricePercent"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    :readonly="!costPriceEnabled()"
                  />
                </div>

                <div v-else-if="item.name === 'Month Closing Time'" class="col-lg-6">
                  <label class="form-label">{{ translateItemLabel(item.label) }}</label>
                  <input
                    v-model="form.monthClosingTime"
                    type="time"
                    class="form-control"
                    :readonly="!monthCloseEnabled()"
                  />
                </div>
              </template>
            </div>
          </fieldset>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  itemData: { type: Object, required: true },
  lookupOptions: { type: Object, required: true },
  optionSets: { type: Object, required: true },
});

const page = usePage();
const t = page.props.translations.ui;
const { can } = usePermissions();

const isView = computed(() => props.mode === "view");
const isCreate = computed(() => props.mode === "create");
const pageTitle = computed(() =>
  isCreate.value ? t.create_item : isView.value ? t.items : t.edit_item,
);

const form = useForm({
  actualitemcode: props.itemData.actualitemcode ?? "",
  itemgroupcode: props.itemData.itemgroupcode ?? "",
  itemtype: props.itemData.itemtype ?? 1,
  anitemcode: props.itemData.anitemcode ?? "",
  alternatecode: props.itemData.alternatecode ?? "",
  itemshortdescription: props.itemData.itemshortdescription ?? "",
  arbitemshortdescription: props.itemData.arbitemshortdescription ?? "",
  itemdescription: props.itemData.itemdescription ?? "",
  arbitemdescription: props.itemData.arbitemdescription ?? "",
  itemgrpcode: props.itemData.itemgrpcode ?? "",
  unitspercase: props.itemData.unitspercase ?? 1,
  caseuom: props.itemData.caseuom ?? 1,
  warehousestock: props.itemData.warehousestock ?? null,
  dataentry: props.itemData.dataentry ?? null,
  offtakeparameter: props.itemData.offtakeparameter ?? null,
  liter: props.itemData.liter ?? null,
  literperunit: props.itemData.literperunit ?? null,
  caseprice: props.itemData.caseprice ?? 0,
  defaultsalesprice: props.itemData.defaultsalesprice ?? 0,
  defaultgoodreturncaseprice: props.itemData.defaultgoodreturncaseprice ?? 0,
  defaultgoodreturnprice: props.itemData.defaultgoodreturnprice ?? 0,
  returncaseprice: props.itemData.returncaseprice ?? 0,
  defaultreturnprice: props.itemData.defaultreturnprice ?? 0,
  costcaseprice: props.itemData.costcaseprice ?? 0,
  defaultcostprice: props.itemData.defaultcostprice ?? 0,
  activeitem: props.itemData.activeitem ?? 1,
  captureshelfstock: props.itemData.captureshelfstock ?? 0,
  tcallowed: props.itemData.tcallowed ?? 0,
  allowinvoicepricechange: props.itemData.allowinvoicepricechange ?? 0,
  allowbatchentry: props.itemData.allowbatchentry ?? 0,
  fastmovingitemflag: props.itemData.fastmovingitemflag ?? 0,
  codedateformat: props.itemData.codedateformat ?? 0,
  printsequenceroute: props.itemData.printsequenceroute ?? null,
  printsequencecust: props.itemData.printsequencecust ?? null,
  itemtaxkey1: props.itemData.itemtaxkey1 ?? null,
  itemtaxkey2: props.itemData.itemtaxkey2 ?? null,
  itemtaxkey3: props.itemData.itemtaxkey3 ?? null,
  packagecode: props.itemData.packagecode ?? null,
  memo1: props.itemData.memo1 ?? "",
  memo2: props.itemData.memo2 ?? "",
  barcode1: props.itemData.barcode1 ?? "",
  barcode2: props.itemData.barcode2 ?? "",
  barcode3: props.itemData.barcode3 ?? "",
  barcode4: props.itemData.barcode4 ?? "",
  barcode5: props.itemData.barcode5 ?? "",
  barcode6: props.itemData.barcode6 ?? "",
  barcode7: props.itemData.barcode7 ?? "",
  barcode8: props.itemData.barcode8 ?? "",
  barcode9: props.itemData.barcode9 ?? "",
  barcode10: props.itemData.barcode10 ?? "",
});

function submit() {
  if (isView.value) {
    return;
  }

  if (isCreate.value) {
    form.post("/inventory/item");
    return;
  }

  form.put(`/inventory/item/${form.actualitemcode}`);
}

function backToIndex() {
  router.get("/inventory/item");
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="t.item_note">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="backToIndex">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('items', 'edit')"
          class="btn btn-primary"
          @click="router.get(`/inventory/item/${form.actualitemcode}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button
          v-else-if="!isView"
          class="btn btn-primary"
          :disabled="form.processing"
          @click="submit"
        >
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.item_list">
      <div class="row g-4 mb-3">
        <div class="col-12">
          <h5 class="mb-0">{{ t.items }}</h5>
          <p class="text-muted fs-sm mb-0">{{ t.item_master_details_note }}</p>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.code }}</label>
          <input v-model="form.actualitemcode" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.item_group }} <span class="text-danger">*</span></label>
          <select v-model="form.itemgroupcode" class="form-select" :disabled="isView">
            <option value="">{{ t.select_item_group }}</option>
            <option v-for="option in lookupOptions.itemGroups" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('itemgroupcode')" class="text-danger fs-sm mt-1">{{ errorFor("itemgroupcode") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.item_type }} <span class="text-danger">*</span></label>
          <select v-model="form.itemtype" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.itemTypeOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="errorFor('itemtype')" class="text-danger fs-sm mt-1">{{ errorFor("itemtype") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.activeitem" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.statusOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input v-model="form.alternatecode" class="form-control" :readonly="isView" />
          <div v-if="errorFor('alternatecode')" class="text-danger fs-sm mt-1">{{ errorFor("alternatecode") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.alpha_numeric_code }}</label>
          <input v-model="form.anitemcode" class="form-control" :readonly="isView" />
          <div v-if="errorFor('anitemcode')" class="text-danger fs-sm mt-1">{{ errorFor("anitemcode") }}</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.item_grouping_code }}</label>
          <input v-model="form.itemgrpcode" class="form-control" :readonly="isView" />
          <div v-if="errorFor('itemgrpcode')" class="text-danger fs-sm mt-1">{{ errorFor("itemgrpcode") }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.short_description }} <span class="text-danger">*</span></label>
          <input v-model="form.itemshortdescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('itemshortdescription')" class="text-danger fs-sm mt-1">{{ errorFor("itemshortdescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arb_short_description }}</label>
          <input v-model="form.arbitemshortdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbitemshortdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbitemshortdescription") }}</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.item_description }} <span class="text-danger">*</span></label>
          <input v-model="form.itemdescription" class="form-control" :readonly="isView" />
          <div v-if="errorFor('itemdescription')" class="text-danger fs-sm mt-1">{{ errorFor("itemdescription") }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.arb_item_description }}</label>
          <input v-model="form.arbitemdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbitemdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbitemdescription") }}</div>
        </div>

        <div class="col-12">
          <h5 class="mb-0">{{ t.quantities_pricing }}</h5>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.upc }}</label>
          <input v-model="form.unitspercase" type="number" min="1" class="form-control" :readonly="isView" />
          <div v-if="errorFor('unitspercase')" class="text-danger fs-sm mt-1">{{ errorFor("unitspercase") }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.case_qty }}</label>
          <input v-model="form.caseuom" type="number" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('caseuom')" class="text-danger fs-sm mt-1">{{ errorFor("caseuom") }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.warehouse_stock }}</label>
          <input v-model="form.warehousestock" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('warehousestock')" class="text-danger fs-sm mt-1">{{ errorFor("warehousestock") }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.liters_per_case }}</label>
          <input v-model="form.liter" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('liter')" class="text-danger fs-sm mt-1">{{ errorFor("liter") }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.liters_per_unit }}</label>
          <input v-model="form.literperunit" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('literperunit')" class="text-danger fs-sm mt-1">{{ errorFor("literperunit") }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.data_entry_seq }}</label>
          <input v-model="form.dataentry" type="number" min="0" class="form-control" :readonly="isView" />
          <div v-if="errorFor('dataentry')" class="text-danger fs-sm mt-1">{{ errorFor("dataentry") }}</div>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.sales_case_price }}</label>
          <input v-model="form.caseprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.sales_unit_price }}</label>
          <input v-model="form.defaultsalesprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.good_return_case }}</label>
          <input v-model="form.defaultgoodreturncaseprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.good_return_unit }}</label>
          <input v-model="form.defaultgoodreturnprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.damaged_case }}</label>
          <input v-model="form.returncaseprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.damaged_unit }}</label>
          <input v-model="form.defaultreturnprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.cost_case_price }}</label>
          <input v-model="form.costcaseprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.cost_unit_price }}</label>
          <input v-model="form.defaultcostprice" type="number" step="0.0001" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.offtake_parameter }}</label>
          <input v-model="form.offtakeparameter" type="number" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.print_seq_route }}</label>
          <input v-model="form.printsequenceroute" type="number" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.print_seq_customer }}</label>
          <input v-model="form.printsequencecust" type="number" min="0" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.code_date_format }}</label>
          <select v-model="form.codedateformat" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.codeDateFormatOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="col-12">
          <h5 class="mb-0">{{ t.tax_package_options }}</h5>
        </div>

        <div class="col-md-3">
          <label class="form-label">{{ t.tax_key_1 }}</label>
          <select v-model="form.itemtaxkey1" class="form-select" :disabled="isView">
            <option :value="null">{{ t.none }}</option>
            <option v-for="option in lookupOptions.itemTaxes" :key="`tax1-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.tax_key_2 }}</label>
          <select v-model="form.itemtaxkey2" class="form-select" :disabled="isView">
            <option :value="null">{{ t.none }}</option>
            <option v-for="option in lookupOptions.itemTaxes" :key="`tax2-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.tax_key_3 }}</label>
          <select v-model="form.itemtaxkey3" class="form-select" :disabled="isView">
            <option :value="null">{{ t.none }}</option>
            <option v-for="option in lookupOptions.itemTaxes" :key="`tax3-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.package }}</label>
          <select v-model="form.packagecode" class="form-select" :disabled="isView">
            <option :value="null">{{ t.none }}</option>
            <option v-for="option in lookupOptions.itemPackages" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">{{ t.shelf_stock }}</label>
          <select v-model="form.captureshelfstock" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.yesNoOptions" :key="`shelf-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.enable_tc }}</label>
          <select v-model="form.tcallowed" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.yesNoOptions" :key="`tc-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.invoice_price_change }}</label>
          <select v-model="form.allowinvoicepricechange" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.yesNoOptions" :key="`inv-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.enable_batch }}</label>
          <select v-model="form.allowbatchentry" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.yesNoOptions" :key="`batch-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.fast_moving_item }}</label>
          <select v-model="form.fastmovingitemflag" class="form-select" :disabled="isView">
            <option v-for="option in optionSets.yesNoOptions" :key="`fast-${option.id}`" :value="option.id">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div class="col-md-2">
        </div>

        <div class="col-md-6">
          <label class="form-label">{{ t.memo_1 }}</label>
          <input v-model="form.memo1" class="form-control" :readonly="isView" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ t.memo_2 }}</label>
          <input v-model="form.memo2" class="form-control" :readonly="isView" />
        </div>

        <div class="col-12">
          <h5 class="mb-0">{{ t.barcodes }}</h5>
        </div>

        <div v-for="index in 10" :key="index" class="col-md-2">
          <label class="form-label">{{ t.barcode }} {{ index }}</label>
          <input v-model="form[`barcode${index}`]" class="form-control" :readonly="isView" />
          <div v-if="errorFor(`barcode${index}`)" class="text-danger fs-sm mt-1">{{ errorFor(`barcode${index}`) }}</div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

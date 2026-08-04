<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePermissions } from "@/composables/usePermissions";

const props = defineProps({
  mode: { type: String, required: true },
  formMeta: { type: Object, required: true },
  planData: { type: Object, required: true },
});

const isView = computed(() => props.mode === "view");
const { can } = usePermissions();
const isCreate = computed(() => props.mode === "create");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => (isCreate.value ? t.create_pricing_plan : isView.value ? t.view_pricing_plan : t.edit_pricing_plan));
const pageSubtitle = computed(() => t.pricing_plan_note);
const types = computed(() => props.formMeta.optionSets?.types ?? {});
const itemMeta = computed(() => {
  const map = {};
  for (const option of props.formMeta.itemOptions ?? []) {
    map[option.id] = option;
  }
  return map;
});

const form = useForm({
  customerpricingkey: props.planData.customerpricingkey ?? "",
  description: props.planData.description ?? "",
  arbdescription: props.planData.arbdescription ?? "",
  type: props.planData.type ?? 1,
  active: props.planData.active ?? 1,
  country: props.planData.country ?? "",
  items: props.planData.items ?? [],
});

const draftItemCode = ref("");
const draft = useForm({
  itemcode: "",
  itemlabel: "",
  unitspercase: "",
  stdsalescaseprice: "",
  stdsalesunitprice: "",
  stdreturncaseprice: "",
  stdreturnunitprice: "",
  salescaseprice: "",
  salesprice: "",
  returncaseprice: "",
  returnprice: "",
});

function resetDraft() {
  draft.defaults({
    itemcode: "",
    itemlabel: "",
    unitspercase: "",
    stdsalescaseprice: "",
    stdsalesunitprice: "",
    stdreturncaseprice: "",
    stdreturnunitprice: "",
    salescaseprice: "",
    salesprice: "",
    returncaseprice: "",
    returnprice: "",
  });
  draft.reset();
  draft.clearErrors();
  draftItemCode.value = "";
}

function applySelectedItem() {
  const option = itemMeta.value[draftItemCode.value];
  if (!option) {
    resetDraft();
    return;
  }

  draft.itemcode = Number(option.id);
  draft.itemlabel = option.label;
  draft.unitspercase = option.meta.unitspercase ?? "";
  draft.stdsalescaseprice = option.meta.stdsalescaseprice ?? "";
  draft.stdsalesunitprice = option.meta.stdsalesunitprice ?? "";
  draft.stdreturncaseprice = option.meta.stdreturncaseprice ?? "";
  draft.stdreturnunitprice = option.meta.stdreturnunitprice ?? "";
  draft.salescaseprice = "";
  draft.salesprice = "";
  draft.returncaseprice = "";
  draft.returnprice = "";
}

function addItem() {
  if (!draft.itemcode) {
    draft.setError("itemcode", t.item_code_required);
    return;
  }

  if (form.items.some((item) => Number(item.itemcode) === Number(draft.itemcode))) {
    draft.setError("itemcode", t.this_item_is_already_added);
    return;
  }

  form.items.push({
    itemcode: Number(draft.itemcode),
    itemlabel: draft.itemlabel,
    unitspercase: draft.unitspercase,
    stdsalescaseprice: draft.stdsalescaseprice,
    stdsalesunitprice: draft.stdsalesunitprice,
    stdreturncaseprice: draft.stdreturncaseprice,
    stdreturnunitprice: draft.stdreturnunitprice,
    salescaseprice: draft.salescaseprice,
    salesprice: draft.salesprice,
    returncaseprice: draft.returncaseprice,
    returnprice: draft.returnprice,
  });

  resetDraft();
}

function removeItem(index) {
  form.items.splice(index, 1);
}

function submit() {
  if (isView.value) return;
  if (isCreate.value) {
    form.post(props.formMeta.baseUrl);
    return;
  }

  form.put(`${props.formMeta.baseUrl}/${form.customerpricingkey}`);
}

function errorFor(field) {
  return form.errors[field];
}
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="pageSubtitle">
    <template #extra>
      <div class="d-flex gap-2">
        <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
          <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
        </button>
        <button
          v-if="isView && can('pricing plan', 'edit')"
          class="btn btn-primary"
          @click="router.get(`${formMeta.baseUrl}/${form.customerpricingkey}/edit`)"
        >
          <i class="fa fa-pen me-1"></i> {{ t.edit }}
        </button>
        <button v-else class="btn btn-primary" :disabled="form.processing" @click="submit">
          <i class="fa fa-floppy-disk me-1"></i> {{ form.processing ? t.saving : t.save }}
        </button>
      </div>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.pricing_plan">
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <label class="form-label">{{ t.plan_key }}</label>
          <input v-model="form.customerpricingkey" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.country }} <span class="text-danger">*</span></label>
          <select v-model="form.country" class="form-select" :disabled="isView">
            <option value="">{{ t.select }}</option>
            <option v-for="country in formMeta.countries" :key="country.id" :value="country.id">
              {{ country.label }}
            </option>
          </select>
          <div v-if="errorFor('country')" class="text-danger fs-sm mt-1">{{ errorFor("country") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.description }} <span class="text-danger">*</span></label>
          <input v-model="form.description" class="form-control" :readonly="isView" />
          <div v-if="errorFor('description')" class="text-danger fs-sm mt-1">{{ errorFor("description") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.type }} <span class="text-danger">*</span></label>
          <select v-model="form.type" class="form-select" :disabled="isView">
            <option v-for="(label, key) in types" :key="key" :value="Number(key)">{{ label }}</option>
          </select>
          <div v-if="errorFor('type')" class="text-danger fs-sm mt-1">{{ errorFor("type") }}</div>
        </div>
      </div>

      <div class="row g-4 mb-3">
        <div class="col-md-6">
          <label class="form-label">{{ t.arabic_description }}</label>
          <input v-model="form.arbdescription" class="form-control" dir="rtl" :readonly="isView" />
          <div v-if="errorFor('arbdescription')" class="text-danger fs-sm mt-1">{{ errorFor("arbdescription") }}</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.status }}</label>
          <select v-model="form.active" class="form-select" :disabled="isView">
            <option :value="1">{{ t.status_active }}</option>
            <option :value="0">{{ t.status_inactive }}</option>
          </select>
        </div>
      </div>
    </BaseBlock>

    <BaseBlock v-if="!isCreate || isView" :title="t.details">
      <div v-if="!isView" class="row g-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.item_code }} <span class="text-danger">*</span></label>
          <select v-model="draftItemCode" class="form-select" @change="applySelectedItem">
            <option value="">{{ t.select }}</option>
            <option v-for="option in formMeta.itemOptions" :key="option.id" :value="option.id">
              {{ option.label }}
            </option>
          </select>
          <div v-if="draft.errors.itemcode" class="text-danger fs-sm mt-1">{{ draft.errors.itemcode }}</div>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ t.units_per_case }}</label>
          <input v-model="draft.unitspercase" class="form-control" readonly />
        </div>
      </div>

      <div v-if="!isView" class="row g-4 mt-1">
        <div class="col-md-3">
          <label class="form-label">{{ t.price_case }}</label>
          <input v-model="draft.stdsalescaseprice" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.price_pcs }}</label>
          <input v-model="draft.stdsalesunitprice" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.return_price_case }}</label>
          <input v-model="draft.stdreturncaseprice" class="form-control" readonly />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.return_price_pcs }}</label>
          <input v-model="draft.stdreturnunitprice" class="form-control" readonly />
        </div>
      </div>

      <div v-if="!isView" class="row g-4 mt-1">
        <div class="col-md-3">
          <label class="form-label">{{ t.new_price_case }}</label>
          <input v-model="draft.salescaseprice" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.new_price_pcs }}</label>
          <input v-model="draft.salesprice" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.new_return_price_case }}</label>
          <input v-model="draft.returncaseprice" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ t.new_return_price_pcs }}</label>
          <input v-model="draft.returnprice" class="form-control" />
        </div>
      </div>

      <div v-if="!isView" class="d-flex justify-content-center pt-4">
        <button class="btn btn-alt-primary px-4" @click="addItem">
          <i class="fa fa-plus me-1"></i> {{ t.add }}
        </button>
      </div>

      <div class="table-responsive pt-4">
        <table class="table table-hover table-vcenter fs-sm">
          <thead>
            <tr>
              <th style="width: 70px">#</th>
              <th>{{ t.item_code }}</th>
              <th>{{ t.units_per_case }}</th>
              <th>{{ t.price_case }}</th>
              <th>{{ t.price_pcs }}</th>
              <th>{{ t.return_price_case }}</th>
              <th>{{ t.return_price_pcs }}</th>
              <th>{{ t.new_price_case }}</th>
              <th>{{ t.new_price_pcs }}</th>
              <th>{{ t.new_return_price_case }}</th>
              <th>{{ t.new_return_price_pcs }}</th>
              <th v-if="!isView" class="text-center" style="width: 90px">{{ t.action }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!form.items.length">
              <td :colspan="isView ? 11 : 12" class="text-center text-muted py-4">{{ t.no_items_added }}</td>
            </tr>
            <tr v-for="(item, index) in form.items" :key="`${item.itemcode}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>
                <div class="fw-semibold">{{ item.itemlabel || item.itemcode }}</div>
                <div class="text-muted">{{ item.itemcode }}</div>
              </td>
              <td>{{ item.unitspercase || "-" }}</td>
              <td>{{ item.stdsalescaseprice || "-" }}</td>
              <td>{{ item.stdsalesunitprice || "-" }}</td>
              <td>{{ item.stdreturncaseprice || "-" }}</td>
              <td>{{ item.stdreturnunitprice || "-" }}</td>
              <td>
                <input v-model="item.salescaseprice" class="form-control form-control-sm" :readonly="isView" />
              </td>
              <td>
                <input v-model="item.salesprice" class="form-control form-control-sm" :readonly="isView" />
              </td>
              <td>
                <input v-model="item.returncaseprice" class="form-control form-control-sm" :readonly="isView" />
              </td>
              <td>
                <input v-model="item.returnprice" class="form-control form-control-sm" :readonly="isView" />
              </td>
              <td v-if="!isView" class="text-center">
                <button class="btn btn-sm btn-alt-danger" @click="removeItem(index)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="errorFor('items')" class="text-danger fs-sm mt-2">{{ errorFor("items") }}</div>
    </BaseBlock>

    <BaseBlock v-else :title="t.details">
      <div class="text-muted fs-sm">
        {{ t.save_pricing_plan_first_then_reopen_add_rows }}
      </div>
    </BaseBlock>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
  typeOptions: Array,
  result: Object,
  notes: Array,
});
const page = usePage();
const t = page.props.translations.ui;

const form = useForm({
  type: props.result?.requested_type ?? props.typeOptions?.[0]?.id ?? "1",
  customer_code: props.result?.requested_customer_code ?? "",
  route_code: props.result?.requested_route_code ?? "",
  access_key: props.result?.requested_access_key ?? "",
});
const invoicePage = ref(1);
const invoicePerPage = ref(10);
const invoiceRows = computed(() => props.result?.invoices ?? []);
const invoiceTotal = computed(() => invoiceRows.value.length);
const invoiceLastPage = computed(() =>
  Math.max(1, Math.ceil(invoiceTotal.value / invoicePerPage.value)),
);
const pagedInvoices = computed(() => {
  const start = (invoicePage.value - 1) * invoicePerPage.value;

  return invoiceRows.value.slice(start, start + invoicePerPage.value);
});

watch(
  () => props.result?.otp,
  () => {
    invoicePage.value = 1;
  },
);

watch(invoicePerPage, () => {
  invoicePage.value = 1;
});

function submit() {
  form.post("/account/salesman-otp/generate", {
    preserveScroll: true,
  });
}

function money(value) {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  return Number(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<template>
  <Head :title="t.salesman_otp" />

  <BasePageHeading
    :title="t.salesman_otp"
    :subtitle="t.salesman_otp_note"
  />

  <div class="content">
    <div class="row g-4">
      <div class="col-12 col-xl-4">
        <BaseBlock :title="t.generate_otp">
          <form class="row g-3" @submit.prevent="submit">
            <div class="col-12">
              <label class="form-label">{{ t.override_for }}</label>
              <select
                v-model="form.type"
                class="form-select"
                :class="{ 'is-invalid': form.errors.type }"
              >
                <option
                  v-for="option in typeOptions"
                  :key="option.id"
                  :value="option.id"
                >
                  {{ option.label }}
                </option>
              </select>
              <div class="invalid-feedback">{{ form.errors.type }}</div>
            </div>

            <div class="col-12">
              <label class="form-label"
                >{{ t.customer_code }}
                <span class="text-muted">({{ t.customer_code_otp_help }})</span></label
              >
              <input
                v-model="form.customer_code"
                class="form-control"
                :class="{ 'is-invalid': form.errors.customer_code }"
                maxlength="50"
                inputmode="numeric"
              />
              <div class="invalid-feedback">
                {{ form.errors.customer_code }}
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">{{ t.route_code }}</label>
              <input
                v-model="form.route_code"
                class="form-control"
                :class="{ 'is-invalid': form.errors.route_code }"
                maxlength="50"
                inputmode="numeric"
              />
              <div class="invalid-feedback">{{ form.errors.route_code }}</div>
            </div>

            <div class="col-12">
              <label class="form-label">{{ t.popup_passkey }}</label>
              <input
                v-model="form.access_key"
                class="form-control"
                :class="{ 'is-invalid': form.errors.access_key }"
                maxlength="50"
                inputmode="numeric"
              />
              <div class="invalid-feedback">{{ form.errors.access_key }}</div>
            </div>

            <div class="col-12 d-grid mb-3">
              <button
                class="btn btn-primary"
                type="submit"
                :disabled="form.processing"
              >
                {{ t.generate_otp }}
              </button>
            </div>
          </form>
        </BaseBlock>
      </div>

      <div class="col-12 col-xl-8">
        <BaseBlock :title="t.otp_result">
          <div v-if="result?.error" class="alert alert-danger mb-0">
            {{ result.error }}
          </div>

          <div v-else-if="result?.otp" class="row g-3">
            <div v-if="result.customer_lookup_warning" class="col-12">
              <div class="alert alert-warning mb-0">
                {{ result.customer_lookup_warning }}
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <div class="border rounded p-3 h-100">
                <div class="text-muted fs-sm text-uppercase mb-1">
                  {{ t.override_type }}
                </div>
                <div class="fw-semibold">{{ result.type_label }}</div>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3 h-100">
                <div class="text-muted fs-sm text-uppercase mb-1">
                  {{ t.popup_passkey }}
                </div>
                <div class="fw-semibold">{{ result.requested_access_key }}</div>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3 h-100 bg-primary-lighter">
                <div class="text-muted fs-sm text-uppercase mb-1">
                  {{ t.generated_otp }}
                </div>
                <div class="fs-3 fw-bold text-primary">{{ result.otp }}</div>
              </div>
            </div>

            <div v-if="result.customer" class="col-12">
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                  <tbody>
                    <tr>
                      <th style="width: 180px">{{ t.customer_code }}</th>
                      <td>{{ result.customer.customercode }}</td>
                      <th style="width: 180px">{{ t.alternate_code }}</th>
                      <td>{{ result.customer.alternatecode || "-" }}</td>
                    </tr>
                    <tr>
                      <th>{{ t.customer_name }}</th>
                      <td>{{ result.customer.customername }}</td>
                      <th>{{ t.route_code }}</th>
                      <td>{{ result.customer.routecode ?? "-" }}</td>
                    </tr>
                    <tr>
                      <th>{{ t.credit_limit }}</th>
                      <td>{{ money(result.customer.creditlimit) }}</td>
                      <th>{{ t.balance }}</th>
                      <td>{{ money(result.customer.balance) }}</td>
                    </tr>
                    <tr>
                      <th>{{ t.credit_limit_days }}</th>
                      <td>{{ result.customer.creditlimitdays ?? "-" }}</td>
                      <th>{{ t.grace_period }}</th>
                      <td>{{ result.customer.graceperiod ?? "-" }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="result.customer" class="col-12">
              <div class="table-responsive">
                <table class="table table-hover table-vcenter fs-sm">
                  <thead>
                    <tr>
                      <th>{{ t.transaction_date }}</th>
                      <th>{{ t.invoice_number }}</th>
                      <th>{{ t.erp_reference }}</th>
                      <th>{{ t.salesman_code }}</th>
                      <th class="text-end">{{ t.invoice_amount }}</th>
                      <th class="text-end">{{ t.balance }}</th>
                      <th>{{ t.due_date }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!invoiceRows.length">
                      <td colspan="7" class="text-center text-muted py-4">
                        {{ t.no_invoice_records_found }}
                      </td>
                    </tr>
                    <tr
                      v-for="invoice in pagedInvoices"
                      :key="`${invoice.invoicenumber}-${invoice.erpreferencenumber}`"
                    >
                      <td>{{ invoice.transactiondate || "-" }}</td>
                      <td>{{ invoice.invoicenumber || "-" }}</td>
                      <td>{{ invoice.erpreferencenumber || "-" }}</td>
                      <td>{{ invoice.salesmancode || "-" }}</td>
                      <td class="text-end">
                        {{ money(invoice.totalinvoiceamount) }}
                      </td>
                      <td class="text-end">
                        {{ money(invoice.invoicebalance) }}
                      </td>
                      <td>{{ invoice.duedate || "-" }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div
                v-if="invoiceRows.length"
                class="d-flex justify-content-between align-items-center pt-3"
              >
                <div
                  class="d-flex align-items-center gap-2 text-muted fs-sm mb-3"
                >
                  <span>
                    {{ t.showing }} {{ (invoicePage - 1) * invoicePerPage + 1 }} {{ t.to }}
                    {{ Math.min(invoicePage * invoicePerPage, invoiceTotal) }}
                    {{ t.of }} {{ invoiceTotal }}
                  </span>
                  <select
                    v-model="invoicePerPage"
                    class="form-select form-select-sm"
                    style="width: 90px"
                  >
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                  </select>
                </div>

                <div class="btn-group mb-3">
                  <button
                    class="btn btn-sm btn-alt-secondary"
                    :disabled="invoicePage <= 1"
                    @click="invoicePage -= 1"
                  >
                    {{ t.previous }}
                  </button>
                  <button class="btn btn-sm btn-alt-secondary" disabled>
                    {{ invoicePage }} / {{ invoiceLastPage }}
                  </button>
                  <button
                    class="btn btn-sm btn-alt-secondary"
                    :disabled="invoicePage >= invoiceLastPage"
                    @click="invoicePage += 1"
                  >
                    {{ t.next }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-muted">
            {{ t.otp_device_help }}
          </div>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

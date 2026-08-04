<script setup>
import { Head, router, usePage } from "@inertiajs/vue3";

const page = usePage();
const t = page.props.translations.ui;

const sections = [
  {
    key: "month-close",
    title: t.month_close,
    description: t.month_close_note,
    icon: "fa fa-calendar-check",
    href: "/account/transaction/month-close",
  },
  {
    key: "gc-collection",
    title: t.gc_collection,
    description: t.collection_analytics,
    icon: "fa fa-money-bill-transfer",
    href: "/account/transaction/gc-collection",
  },
  {
    key: "ho-collection",
    title: t.ho_collection,
    description: t.cashier_receipt,
    icon: "fa fa-building-circle-check",
    href: "/account/transaction/ho-collection",
  },
  {
    key: "opening-balance",
    title: t.opening_balance,
    description: t.opening_balance_note,
    icon: "fa fa-scale-balanced",
    href: "/account/transaction/opening-balance",
  },
];
</script>

<template>
  <Head :title="t.transaction" />

  <BasePageHeading
    :title="t.transaction"
    :subtitle="t.transaction_note"
  />

  <div class="content">
    <div class="row g-4">
      <div v-for="section in sections" :key="section.key" class="col-md-6">
        <BaseBlock tag="button" class="w-100 text-start h-100" transparent @click="router.get(section.href)">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-primary">
              <i :class="section.icon"></i>
            </div>
            <div>
              <div class="fw-semibold fs-4 mb-1">{{ section.title }}</div>
              <div class="text-muted">{{ section.description }}</div>
            </div>
          </div>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

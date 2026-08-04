<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
  formMeta: { type: Object, required: true },
  imageData: { type: Object, required: true },
});

const activeTab = ref("customer");
const t = usePage().props.translations.ui;
const pageTitle = computed(() => `${t.images_captured} - ${props.imageData.customername}`);

const tabs = [
  { key: "customer", label: t.customer_images },
  { key: "planogram", label: t.planogram },
  { key: "pos", label: t.pos },
  { key: "survey", label: t.survey },
];
</script>

<template>
  <Head :title="pageTitle" />

  <BasePageHeading :title="pageTitle" :subtitle="formMeta.subtitle">
    <template #extra>
      <button class="btn btn-alt-secondary" @click="router.get(formMeta.indexUrl)">
        <i class="fa fa-arrow-left me-1"></i> {{ t.back }}
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <BaseBlock :title="t.customer">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_code }}</label>
          <input :value="imageData.customercode" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.customer_name }}</label>
          <input :value="imageData.customername" class="form-control" readonly />
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ t.alternate_code }}</label>
          <input :value="imageData.alternatecode || '-'" class="form-control" readonly />
        </div>
      </div>
    </BaseBlock>

    <div v-if="imageData.legacyMissing.visitControlTable || imageData.legacyMissing.planogramCapturedFolder || imageData.legacyMissing.posFolder || imageData.legacyMissing.surveyFolder" class="alert alert-warning d-flex align-items-start" role="alert">
      <i class="fa fa-triangle-exclamation me-2 mt-1"></i>
      <div>
        {{ t.some_legacy_image_sources_not_available }}
      </div>
    </div>

    <BaseBlock :title="t.captured_images">
      <div class="border-bottom mb-4">
        <ul class="nav nav-tabs nav-tabs-block">
          <li v-for="tab in tabs" :key="tab.key" class="nav-item">
            <button class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
              {{ tab.label }}
            </button>
          </li>
        </ul>
      </div>

      <div v-if="activeTab === 'customer'">
        <div v-if="!imageData.customerImages.length" class="text-center text-muted py-4">{{ t.no_customer_images_found }}</div>
        <div v-else class="row g-4">
          <div v-for="row in imageData.customerImages" :key="row.table_id" class="col-md-3 col-sm-6">
            <div class="border rounded p-3 h-100 text-center">
              <a v-if="row.imageurl" :href="row.imageurl" target="_blank" rel="noreferrer">
                <img :src="row.imageurl" :alt="row.remarks || row.imagename" class="img-fluid rounded mb-3" style="height: 200px; object-fit: cover; width: 100%" />
              </a>
              <div v-else class="bg-body-light rounded d-flex align-items-center justify-content-center text-muted mb-3" style="height: 200px">
                {{ t.no_image }}
              </div>
              <div class="fw-semibold">{{ row.remarks || "-" }}</div>
              <div class="text-muted fs-sm">{{ row.imagename }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'planogram'">
        <div v-if="!imageData.planogramImages.length" class="text-center text-muted py-4">{{ t.no_planogram_captured_images_found }}</div>
        <div v-else class="row g-4">
          <div v-for="(row, index) in imageData.planogramImages" :key="index" class="col-12">
            <div class="row g-4 align-items-start border rounded p-3">
              <div class="col-md-12 fw-semibold">{{ t.planogram_description_label }}: {{ row.plan_desc }}</div>
              <div class="col-md-6 text-center">
                <div class="fw-semibold mb-2">{{ t.original_image }}</div>
                <img v-if="row.oldimg" :src="row.oldimg" :alt="t.original_image" class="img-fluid rounded border" style="max-height: 200px" />
                <div v-else class="text-muted py-5">{{ t.no_image }}</div>
              </div>
              <div class="col-md-6 text-center">
                <div class="fw-semibold mb-2">{{ t.captured_image }}</div>
                <img v-if="row.newimg" :src="row.newimg" :alt="t.captured_image" class="img-fluid rounded border" style="max-height: 200px" />
                <div v-else class="text-muted py-5">{{ t.no_image }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="activeTab === 'pos'">
        <div v-if="!imageData.posImages.length" class="text-center text-muted py-4">{{ t.no_pos_captured_images_found }}</div>
        <div v-else class="row g-4">
          <div v-for="(row, index) in imageData.posImages" :key="index" class="col-md-3 col-sm-6">
            <div class="border rounded p-3 h-100 text-center">
              <a v-if="row.imageurl" :href="row.imageurl" target="_blank" rel="noreferrer">
                <img :src="row.imageurl" :alt="row.caption || row.imagename" class="img-fluid rounded mb-3" style="height: 200px; object-fit: cover; width: 100%" />
              </a>
              <div v-else class="bg-body-light rounded d-flex align-items-center justify-content-center text-muted mb-3" style="height: 200px">{{ t.no_image }}</div>
              <div class="fw-semibold">{{ row.caption || row.imagename || "-" }}</div>
            </div>
          </div>
        </div>
      </div>

      <div v-else>
        <div v-if="!imageData.surveyImages.length" class="text-center text-muted py-4">{{ t.no_survey_captured_images_found }}</div>
        <div v-else class="row g-4">
          <div v-for="(row, index) in imageData.surveyImages" :key="index" class="col-md-3 col-sm-6">
            <div class="border rounded p-3 h-100 text-center">
              <a v-if="row.imageurl" :href="row.imageurl" target="_blank" rel="noreferrer">
                <img :src="row.imageurl" :alt="row.surveyprompt || row.imagename" class="img-fluid rounded mb-3" style="height: 200px; object-fit: cover; width: 100%" />
              </a>
              <div v-else class="bg-body-light rounded d-flex align-items-center justify-content-center text-muted mb-3" style="height: 200px">{{ t.no_image }}</div>
              <div class="fw-semibold">{{ row.surveyprompt || row.imagename || "-" }}</div>
            </div>
          </div>
        </div>
      </div>
    </BaseBlock>
  </div>
</template>

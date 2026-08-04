<script setup></script>

<template>
  <Head title="Errors" />

  <!-- Hero -->
  <BasePageHeading title="Error Pages" subtitle="All pages in one spot!">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Error Pages</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">All</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <div class="row">
      <div class="col-md-4">
        <!-- 400 -->
        <BaseBlock href="/preview/errors/400" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-default fw-semibold mb-0">400</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 400 -->
      </div>
      <div class="col-md-4">
        <!-- 401 -->
        <BaseBlock href="/preview/errors/401" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-amethyst fw-semibold mb-0">401</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 401 -->
      </div>
      <div class="col-md-4">
        <!-- 403 -->
        <BaseBlock href="/preview/errors/403" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-flat fw-semibold mb-0">403</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 403 -->
      </div>
      <div class="col-md-4">
        <!-- 404 -->
        <BaseBlock href="/preview/errors/404" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-city fw-semibold mb-0">404</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 404 -->
      </div>
      <div class="col-md-4">
        <!-- 500 -->
        <BaseBlock href="/preview/errors/500" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-modern fw-semibold mb-0">500</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 500 -->
      </div>
      <div class="col-md-4">
        <!-- 503 -->
        <BaseBlock href="/preview/errors/503" tag="Link" link-shadow>
          <div class="py-5 text-center">
            <p class="fs-1 text-smooth fw-semibold mb-0">503</p>
            <p class="fw-medium text-muted">Error Page</p>
          </div>
        </BaseBlock>
        <!-- END 503 -->
      </div>
    </div>
  </div>
  <!-- END Page Content -->
</template>

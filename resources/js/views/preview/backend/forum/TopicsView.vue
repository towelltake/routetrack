<script setup></script>

<template>
  <Head title="Topics - Forum" />

  <!-- Hero -->
  <BasePageHeading title="Explore a category">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Forum</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">Topics</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <!-- Topics -->
    <BaseBlock title="Topics">
      <template #options>
        <button type="button" class="btn-block-option me-2">
          <i class="fa fa-plus me-1"></i> New Topic
        </button>
      </template>

      <!-- Topics -->
      <table class="table table-striped table-borderless table-vcenter">
        <thead class="border-bottom">
          <tr>
            <th colspan="2">Welcome</th>
            <th class="d-none d-md-table-cell text-center" style="width: 100px">
              Replies
            </th>
            <th class="d-none d-md-table-cell text-center" style="width: 100px">
              Views
            </th>
            <th class="d-none d-md-table-cell" style="width: 200px">
              Last Post
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center" style="width: 40px">
              <i class="si si-pin"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Welcome to our forums!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Justin Hunt</Link
                >
                on May 20, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">108</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">853</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Judy Ford</Link
                ><br />on May 21, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center" style="width: 40px">
              <i class="si si-pin"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Big upgrades are coming soon!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Jeffrey Shaw</Link
                >
                on May 25, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">289</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">794</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Scott Young</Link
                ><br />on May 18, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center" style="width: 40px">
              <i class="si si-pin"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Tips &amp; tricks for staying motivated</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Lisa Jenkins</Link
                >
                on May 15, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">276</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">392</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Amanda Powell</Link
                ><br />on May 13, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >2024, all the new features!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Lori Grant</Link
                >
                on May 21, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">110</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">1637</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Lori Grant</Link
                ><br />on May 13, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Issue when saving a file, can you help me?</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Albert Ray</Link
                >
                on May 26, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">136</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">2914</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Adam McCoy</Link
                ><br />on May 2, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >New Features!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Scott Young</Link
                >
                on May 29, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">169</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">1715</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Sara Fields</Link
                ><br />on May 2, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Question about the new features!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Thomas Riley</Link
                >
                on May 28, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">108</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">2581</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Helen Jacobs</Link
                >
                ><br />on May 1, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Which plan to choose?</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Scott Young</Link
                >
                on March 12, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">299</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">642</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >David Fuller</Link
                ><br />on May 1, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Your web app saved me tons of time</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Jose Parker</Link
                >
                on May 23, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">275</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">874</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Carol Ray</Link
                ><br />on May 12, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Is it easy to upgrade my plan?</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Carol White</Link
                >
                on May 10, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">186</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">1811</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >David Fuller</Link
                ><br />on May 6, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Check out all those tips &amp; tricks!</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Brian Stevens</Link
                >
                on May 5, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">240</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">2137</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >David Fuller</Link
                ><br />on May 5, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Review needed</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Henry Harrison</Link
                >
                on May 3, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">208</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">970</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Amber Harvey</Link
                ><br />on May 4, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <Link
                href="/preview/backend/page-packs/forum/discussion"
                class="fw-semibold"
                >Testing out the API</Link
              >
              <div class="fs-sm text-muted mt-1">
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Jesse Fisher</Link
                >
                on April 25, 2024
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">271</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">1961</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Helen Jacobs</Link
                ><br />on May 4, 2024</span
              >
            </td>
          </tr>
        </tbody>
      </table>
      <!-- END Topics -->

      <!-- Pagination -->
      <nav aria-label="Topics navigation">
        <ul class="pagination justify-content-end">
          <li class="page-item active">
            <a class="page-link" href="javascript:void(0)">1</a>
          </li>
          <li class="page-item">
            <a class="page-link" href="javascript:void(0)">2</a>
          </li>
          <li class="page-item">
            <a class="page-link" href="javascript:void(0)">3</a>
          </li>
          <li class="page-item">
            <a class="page-link" href="javascript:void(0)" aria-label="Next">
              <span aria-hidden="true">
                <i class="fa fa-angle-right"></i>
              </span>
              <span class="visually-hidden">Next</span>
            </a>
          </li>
        </ul>
      </nav>
      <!-- END Pagination -->
    </BaseBlock>
    <!-- END Topics -->
  </div>
  <!-- END Page Content -->
</template>

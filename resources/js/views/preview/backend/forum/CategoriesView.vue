<script setup></script>

<template>
  <Head title="Categories - Forum" />

  <!-- Hero -->
  <BasePageHeading title="Welcome to the forums">
    <template #extra>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb breadcrumb-alt">
          <li class="breadcrumb-item">
            <a class="link-fx" href="javascript:void(0)">Forum</a>
          </li>
          <li class="breadcrumb-item" aria-current="page">Home</li>
        </ol>
      </nav>
    </template>
  </BasePageHeading>
  <!-- END Hero -->

  <!-- Page Content -->
  <div class="content">
    <!-- Categories -->
    <BaseBlock title="Categories">
      <!-- Intro Category -->
      <table class="table table-striped table-borderless table-vcenter push">
        <thead class="border-bottom">
          <tr>
            <th colspan="2">Introduction</th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Topics
            </th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Posts
            </th>
            <th class="d-none d-md-table-cell" style="width: 180px">
              Last Post
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center" style="width: 70px">
              <i class="si si-check fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Welcome</Link
              >
              <div class="fs-sm text-muted mt-1">
                Introduce yourself to our community
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">437</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">9945</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Judy Ford</Link
                ><br />on May 23, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-bell fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Announcements</Link
              >
              <div class="fs-sm text-muted mt-1">For all recent news</div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">449</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">8581</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Jeffrey Shaw</Link
                ><br />on May 15, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-badge fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Terms &amp; Conditions</Link
              >
              <div class="fs-sm text-muted mt-1">
                Please read and comply with our forum’s rules
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">986</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">9099</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Brian Cruz</Link
                ><br />on May 21, 2024</span
              >
            </td>
          </tr>
        </tbody>
      </table>
      <!-- END Intro Category -->

      <!-- Web App Category -->
      <table class="table table-striped table-borderless table-vcenter push">
        <thead class="border-bottom">
          <tr>
            <th colspan="2">Web App</th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Topics
            </th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Posts
            </th>
            <th class="d-none d-md-table-cell" style="width: 180px">
              Last Post
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center" style="width: 70px">
              <i class="si si-cursor fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Getting Started</Link
              >
              <div class="fs-sm text-muted mt-1">
                If you are a new user, here you will find everything you need
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">218</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">3905</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Melissa Rice</Link
                ><br />on May 17, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-docs fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Tutorials</Link
              >
              <div class="fs-sm text-muted mt-1">
                The best place to learn new stuff
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">247</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">7280</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Megan Fuller</Link
                ><br />on May 23, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-settings fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Plugins</Link
              >
              <div class="fs-sm text-muted mt-1">
                Creating or looking for an existing plugin?
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">236</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">7688</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Laura Carr</Link
                ><br />on May 22, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-wrench fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >API</Link
              >
              <div class="fs-sm text-muted mt-1">
                Take advantage of our powerful API
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">834</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">9735</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Marie Duncan</Link
                ><br />on May 17, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-chemistry fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Extentions</Link
              >
              <div class="fs-sm text-muted mt-1">
                Extend our web application’s features
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">482</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">3409</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Amber Harvey</Link
                ><br />on May 13, 2024</span
              >
            </td>
          </tr>
        </tbody>
      </table>
      <!-- END Web App Category -->

      <!-- Support Category -->
      <table class="table table-striped table-borderless table-vcenter push">
        <thead class="border-bottom">
          <tr>
            <th colspan="2">Support</th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Topics
            </th>
            <th class="d-none d-md-table-cell text-center" style="width: 90px">
              Posts
            </th>
            <th class="d-none d-md-table-cell" style="width: 180px">
              Last Post
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-center" style="width: 70px">
              <i class="si si-info fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Questions</Link
              >
              <div class="fs-sm text-muted mt-1">
                Need assistance? Don’t worry, we are here to help
              </div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">546</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">4990</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Adam McCoy</Link
                ><br />on May 20, 2024</span
              >
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <i class="si si-question fa-2x"></i>
            </td>
            <td>
              <Link
                href="/preview/backend/page-packs/forum/topics"
                class="fw-semibold"
                >Playground</Link
              >
              <div class="fs-sm text-muted mt-1">Discuss with other users</div>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">230</a>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <a class="fw-semibold" href="javascript:void(0)">5848</a>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fs-sm"
                >by
                <Link
                  href="/preview/backend/pages/generic/profile"
                  class="fw-medium"
                  >Albert Ray</Link
                ><br />on May 24, 2024</span
              >
            </td>
          </tr>
        </tbody>
      </table>
      <!-- END Support Category -->
    </BaseBlock>
    <!-- END Categories -->
  </div>
  <!-- END Page Content -->
</template>

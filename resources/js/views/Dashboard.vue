<script setup></script>

<template>
  <Head title="Dashboard" />

  <BasePageHeading
    title="Dashboard"
    :subtitle="`Welcome ${$page.props.auth.user.name.split(' ')[0]}, everything looks good!`"
  >
    <template #extra>
      <button type="button" class="btn btn-alt-primary" v-click-ripple>
        <i class="fa fa-plus opacity-50 me-1"></i>
        Main Action
      </button>
    </template>
  </BasePageHeading>

  <div class="content">
    <div class="row items-push">
      <div class="col-sm-6 col-xl-4">
        <BaseBlock title="Title" class="h-100 mb-0" content-class="fs-sm">
          <p>Content..</p>
        </BaseBlock>
      </div>
    </div>
  </div>
</template>

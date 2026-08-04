<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
  links: {
    type: Array,
    required: true,
  },
  align: {
    type: String,
    default: "end", // start | center | end
  },
});

const alignClass = {
  start: "",
  center: "justify-content-center",
  end: "justify-content-end",
};
</script>

<template>
  <nav v-if="links.length > 1">
    <ul class="pagination" :class="alignClass[align]">
      <li
        v-for="(link, i) in links"
        :key="i"
        class="page-item"
        :class="{ active: link.active, disabled: !link.url }"
      >
        <Link class="page-link" :href="link.url || '#'" v-html="link.label" />
      </li>
    </ul>
  </nav>
</template>

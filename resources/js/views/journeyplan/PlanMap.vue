<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
const props = defineProps({ day: Object, customers: Object });
const element = ref(null); let map, layer, resize;
async function draw() {
  await nextTick(); if (!map) return;
  layer.clearLayers();
  const points = (props.day?.visits ?? []).map(v => {
    const c = props.customers[v.customer], point = [Number(c.lat), Number(c.lng)];
    const label = document.createElement('span'); label.textContent = `${v.sequence}. ${c.name}`;
    L.circleMarker(point, { radius: 7, color: '#4265b8', fillOpacity: 0.8 }).bindTooltip(label).addTo(layer);
    return point;
  });
  if (points.length > 1) L.polyline(points, { dashArray: '5 8' }).addTo(layer);
  if (points.length) map.fitBounds(L.latLngBounds(points), { padding: [30, 30], maxZoom: 14 });
}
watch(() => props.day, draw, { deep: true });
onMounted(() => { map = L.map(element.value).setView([23.6, 58.4], 7); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map); layer = L.layerGroup().addTo(map); resize = new ResizeObserver(() => { map?.invalidateSize(); draw(); }); resize.observe(element.value); draw(); });
onBeforeUnmount(() => { resize?.disconnect(); map?.remove(); map = null; });
</script>
<template><div ref="element" style="height: 360px; z-index: 0; border-radius: 12px"></div><p class="text-muted fs-sm mt-2">Customer-to-customer straight-line connections. Depot and return legs are included in time totals only when enabled.</p></template>

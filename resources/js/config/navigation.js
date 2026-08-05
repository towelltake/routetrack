export const navigation = [
  {
    label: "gps_routing",
    icon: "fa fa-route",
    sub: [
      { label: "customer_location", to: "/customer-location", icon: "fa fa-map-location-dot", permission: "customer location" },
      { label: "route_location", to: "/route-location", icon: "fa fa-location-crosshairs", permission: "route location" },
      { label: "route_tracking", to: "/route-tracking", icon: "fa fa-code-compare", permission: "route tracking" },
    ],
  },
];

export default navigation;

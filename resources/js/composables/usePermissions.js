import { usePage } from "@inertiajs/vue3";

export function usePermissions() {
  const page = usePage();

  const can = (permission, action = "view") => {
    const details = page.props.auth?.formPermissions?.[permission];

    if (!details) {
      return false;
    }

    if (details.all) {
      return true;
    }

    if (action === "view") {
      return !!details.view;
    }

    if (action === "create") {
      return !!details.create;
    }

    if (action === "edit") {
      return !!details.write;
    }

    return !!details[action];
  };

  return { can };
}

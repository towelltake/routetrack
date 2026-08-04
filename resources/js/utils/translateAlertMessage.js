const ENTITY_KEY_MAP = {
  "area": "area",
  "area manager": "area_manager",
  "bank": "bank_master",
  "basic setup": "basic_setup",
  "branch/depot": "depot",
  "branch/depot manager": "branch_depot_manager",
  "cash description": "cash_description",
  "company": "company",
  "control panel": "control_panel",
  "country": "country",
  "currency": "currency",
  "customer": "customer",
  "customer authorize group": "customer_authorize_group",
  "customer category": "customer_category",
  "customer channel": "customer_channel",
  "customer message": "customer_message",
  "customer pos limit": "customer_pos_limit",
  "customer sequence": "customer_sequence",
  "customer template": "customer_template",
  "delivery": "delivery",
  "device": "device_registration",
  "email template": "email_templates",
  "inventory location": "inventory_location",
  "item": "item",
  "item group": "item_group",
  "loyalty group": "loyalty_group",
  "loyalty key": "loyalty_key",
  "loyalty plan": "loyalty_plan",
  "national sales manager": "national_sales_manager",
  "permissions": "user_permissions",
  "planogram": "planogram",
  "pos instruction": "pos_instruction",
  "pos item": "pos_item",
  "pos master": "pos_master",
  "pricing key": "pricing_key",
  "pricing plan": "pricing_plan",
  "promo key": "promo_key",
  "promo plan": "promo_plan",
  "reason": "reason_master",
  "region": "region",
  "regional manager": "regional_manager",
  "route": "route",
  "route category": "route_category",
  "route template": "route_template",
  "salesman": "salesman",
  "salesman message": "salesman_message",
  "sub area": "sub_area",
  "sub major category": "sub_major_category",
  "supervisor": "supervisor",
  "survey": "survey",
  "survey definition": "survey",
  "survey key": "survey_key",
  "survey plan": "survey_plan",
  "target group": "target_group",
  "tax": "tax",
  "user master record": "user_master",
  "user type": "user_type",
  "van": "van",
};

const EXACT_MESSAGE_KEYS = {
  "Cannot delete: record is in use.": "flash_cannot_delete_record_in_use",
  "Cannot delete: user type is assigned to one or more users.":
    "flash_cannot_delete_user_type_assigned",
  "Failed to save permissions.": "flash_failed_to_save_permissions",
  "Permissions saved successfully.": "flash_permissions_saved_successfully",
  "Update Record": "flash_update_record",
  "Duplicate record.": "flash_duplicate_record",
  "New record.": "flash_new_record",
  "Invalid week/day selection.": "flash_invalid_week_day_selection",
};

function translateEntity(entity, t) {
  const normalized = entity.trim().toLowerCase();
  const key = ENTITY_KEY_MAP[normalized];

  return key ? t[key] ?? entity : entity;
}

function template(t, key, fallback, entity) {
  return (t[key] ?? fallback).replace(":entity", entity);
}

export function translateAlertMessage(message, t = {}, locale = "en") {
  if (!message || locale !== "ar") {
    return message;
  }

  const exactKey = EXACT_MESSAGE_KEYS[message];
  if (exactKey) {
    return t[exactKey] ?? message;
  }

  const patternHandlers = [
    [/^(.+?) created successfully\.$/i, (entity) =>
      template(t, "flash_entity_created_successfully", "تم إنشاء :entity بنجاح.", translateEntity(entity, t))],
    [/^(.+?) updated successfully\.$/i, (entity) =>
      template(t, "flash_entity_updated_successfully", "تم تحديث :entity بنجاح.", translateEntity(entity, t))],
    [/^(.+?) deleted successfully\.$/i, (entity) =>
      template(t, "flash_entity_deleted_successfully", "تم حذف :entity بنجاح.", translateEntity(entity, t))],
    [/^(.+?) removed successfully\.$/i, (entity) =>
      template(t, "flash_entity_removed_successfully", "تمت إزالة :entity بنجاح.", translateEntity(entity, t))],
    [/^(.+?) created\.$/i, (entity) =>
      template(t, "flash_entity_created", "تم إنشاء :entity.", translateEntity(entity, t))],
    [/^(.+?) updated\.$/i, (entity) =>
      template(t, "flash_entity_updated", "تم تحديث :entity.", translateEntity(entity, t))],
    [/^(.+?) deleted\.$/i, (entity) =>
      template(t, "flash_entity_deleted", "تم حذف :entity.", translateEntity(entity, t))],
    [/^(.+?) saved\.$/i, (entity) =>
      template(t, "flash_entity_saved", "تم حفظ :entity.", translateEntity(entity, t))],
    [/^(.+?) registered\.$/i, (entity) =>
      template(t, "flash_entity_registered", "تم تسجيل :entity.", translateEntity(entity, t))],
    [/^(.+?) copied\.$/i, (entity) =>
      template(t, "flash_entity_copied", "تم نسخ :entity.", translateEntity(entity, t))],
  ];

  for (const [pattern, handler] of patternHandlers) {
    const match = message.match(pattern);
    if (match) {
      return handler(match[1]);
    }
  }

  return message;
}

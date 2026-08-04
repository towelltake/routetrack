<?php

return [
	'currency_create_failed' => 'Unable to create currency. Please try again.',
	'currency_update_failed' => 'Unable to update currency. Please try again.',
	'currency_delete_failed' => 'Currency is already in use and cannot be deleted.',
	'country_create_failed' => 'Unable to create country. Please try again.',
	'country_update_failed' => 'Unable to update country. Please try again.',
	'country_delete_failed' => 'Country is already in use and cannot be deleted.',
	'region_create_failed' => 'Unable to create region. Please try again.',
	'region_update_failed' => 'Unable to update region. Please try again.',
	'region_delete_failed' => 'Region is already in use and cannot be deleted.',
	'area_create_failed' => 'Unable to create area. Please try again.',
	'area_update_failed' => 'Unable to update area. Please try again.',
	'area_delete_failed' => 'Area is already in use and cannot be deleted.',
	'subarea_create_failed' => 'Unable to create sub area. Please try again.',
	'subarea_update_failed' => 'Unable to update sub area. Please try again.',
	'subarea_delete_failed' => 'Sub area is already in use and cannot be deleted.',
	// Vehicle Master
	'vehicle_create_failed' => 'Unable to create vehicle. Please try again.',
	'vehicle_update_failed' => 'Unable to update vehicle. Please try again.',
	'vehicle_delete_failed' => 'Vehicle is already in use and cannot be deleted.',
	'vehicle_delete_linked_route' => 'Vehicle is linked to a route and cannot be deleted.',
	'vehicle_inactivate_linked_route' => 'Vehicle is linked to a route and cannot be inactivated.',
	// Depot Master
	'depot_create_failed' => 'Unable to create depot. Please try again.',
	'depot_update_failed' => 'Unable to update depot. Please try again.',
	'depot_delete_failed' => 'Depot is already in use and cannot be deleted.',
	'depot_inactivate_linked_route' =>
		'Cannot inactivate this depot because it is linked to routes.',
	'depot_delete_linked_route' =>
		'Cannot delete this depot because it is linked to routes.',
	// Company Master
	'company_create_failed' => 'Unable to create company. Please try again.',
	'company_update_failed' => 'Unable to update company. Please try again.',
	'company_delete_failed' => 'Company is already in use and cannot be deleted.',
	'company_inactivate_linked_route' =>
		'Cannot inactivate this company because it is linked to routes.',
	'company_delete_linked_route' =>
		'Cannot delete this company because it is linked to routes.',
	// Device Master
	'device_create_failed' => 'Unable to create device. Please try again.',
	'device_update_failed' => 'Unable to update device. Please try again.',
	'device_delete_failed' => 'Device is already in use and cannot be deleted.',
	'device_delete_linked_route' => 'Device is linked to a route and cannot be deleted.',
	'device_inactivate_linked_route' => 'Device is linked to a route and cannot be inactivated.',
	// Company group Master
	'companygroup_create_failed' => 'Unable to create company group. Please try again.',
	'companygroup_update_failed' => 'Unable to update company group. Please try again.',
	'companygroup_delete_failed' => 'Company group is already in use and cannot be deleted.',
	'companygroup_inactivate_linked_majorcategory' => 'Company group is linked to a major category and cannot be inactivated.',
	// Major Category Master
	'majorcategory_create_failed' => 'Unable to create major category. Please try again.',
	'majorcategory_update_failed' => 'Unable to update major category. Please try again.',
	'majorcategory_delete_failed' => 'Major category is already in use and cannot be deleted.',
	'majorcategory_inactivate_linked_submajorcategory' => 'Major category is linked to sub major categories and cannot be inactivated.',
	// Sub Major Category Master
	'submajorcategory_create_failed' => 'Unable to create sub major category. Please try again.',
	'submajorcategory_update_failed' => 'Unable to update sub major category. Please try again.',
	'submajorcategory_delete_failed' => 'Sub major category is already in use and cannot be deleted.',
	'submajorcategory_inactivate_linked_itemgroup' => 'Sub major category is linked to item groups and cannot be inactivated.',
	// Item Group
	'itemgroup_create_failed' => 'Unable to create Item Group. Please try again.',
	'itemgroup_update_failed' => 'Unable to update Item Group. Please try again.',
	'itemgroup_delete_failed' => 'Item Group is already in use and cannot be deleted.',
	'itemgroup_inactivate_linked_itemmaster' => 'Item group is linked to item master and cannot be inactivated.',
	// Itemmaster
	'item_create_failed' => 'Unable to create Item. Please try again.',
	'uom_base_required' => 'Exactly one Base UOM must be selected.',
	'uom_duplicate_not_allowed' => 'Duplicate UOM is not allowed for the same item.',
	'uom_required' => 'Please select at least one UOM.',
	'item_update_failed' => 'Unable to update Item. Please try again.',
	// UOM Master
	'uom_create_failed' => 'Unable to create UOM. Please try again.',
	'uom_inactivate_linked_itemuom' => 'UOM is linked to Item and cannot be inactivated.',
	'uom_update_failed' => 'Unable to update UOM. Please try again',
	'uom_delete_failed' => 'UOM is already in use and cannot be deleted.',
	// Salesman Master
	'salesman_create_failed' => 'Unable to create salesman. Please try again.',
	'salesman_update_failed' => 'Unable to update salesman. Please try again.',
	'salesman_delete_failed_linked' => 'Salesman is linked to routes and cannot be deleted.',
	'salesman_linked_to_route' => 'Cannot inactivate this salesman because they are linked to routes.',
	// category Master
	'category_create_failed' => 'Unable to create category. Please try again.',
	'category_update_failed' => 'Unable to update category. Please try again.',
	'category_delete_failed' => 'Category is already in use and cannot be deleted.',
	// cahnnel Master
	'channel_create_failed' => 'Unable to create channel. Please try again.',
	'channel_update_failed' => 'Unable to update channel. Please try again.',
	'channel_delete_failed' => 'Channel is already in use and cannot be deleted.',
	//Route Item Group
	'route_item_group_create_failed' => 'Unable to create Route Item Group. Please try again.',
	'route_item_group_update_failed' => 'Unable to update Route Item Group. Please try again.',
	'route_item_group_delete_failed' => 'Route Item Group is already in use and cannot be deleted.',
	'route_item_group_inactivate_linked' => 'Route Item Group is linked to Route and cannot be inactivated.',
	'daily_salesman_load_exists' => 'A Daily Salesman Load already exists for this route, date, salesman, and load period.',
	'daily_salesman_load_no_items' => 'Enter at least one item line with a quantity greater than zero.',
	'daily_salesman_load_header_locked' => 'Route, salesman, load date, and load period cannot be changed after creation.',
	//Route Setting Template
	'routesettingtemplate_create_failed' => 'Unable to create Route Setting Template. Please try again.',
	'routesettingtemplate_update_failed' => 'Unable to update Route Setting Template. Please try again.',
	'template_delete_failed' => 'Unable to delete Route Setting Template.',
];

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura las PRIMARY KEY, AUTO_INCREMENT, índices y foreign keys que se perdieron
 * al importar un dump sin las sentencias de claves.
 *
 * Sin ellas `sales` y `tickets` (~185k filas cada una) se recorren completas en cada
 * consulta —el reporte de detalle de consumo hacía un BNL join de 165k × 181k filas—
 * y `id` no autoincrementa, por lo que dos inserciones concurrentes colisionan.
 *
 * Es idempotente: cada sentencia verifica primero contra information_schema, así que
 * en una base sana no hace nada. Las definiciones se extrajeron comparando contra un
 * esquema levantado desde cero con estas mismas migraciones.
 */
return new class extends Migration
{
    /** tabla => [columnas] */
    private const PRIMARY_KEYS = [
        'addendums' => array (
  0 => 'id',
),
        'areas' => array (
  0 => 'id',
),
        'area_headquarter' => array (
  0 => 'id',
),
        'area_role' => array (
  0 => 'id',
),
        'atwater_factors' => array (
  0 => 'id',
),
        'businessables' => array (
  0 => 'id',
),
        'businesses' => array (
  0 => 'id',
),
        'business_service' => array (
  0 => 'id',
),
        'cache' => array (
  0 => 'key',
),
        'cache_locks' => array (
  0 => 'key',
),
        'cafes' => array (
  0 => 'id',
),
        'cafe_roles' => array (
  0 => 'id',
),
        'cafe_satisfactions' => array (
  0 => 'id',
),
        'cafe_service' => array (
  0 => 'id',
),
        'cafe_user' => array (
  0 => 'id',
),
        'calories' => array (
  0 => 'id',
),
        'category_epps' => array (
  0 => 'id',
),
        'cities' => array (
  0 => 'id',
),
        'city_provider' => array (
  0 => 'id',
),
        'cloths' => array (
  0 => 'id',
),
        'cloth_cloth_provider' => array (
  0 => 'id',
),
        'cloth_inventories' => array (
  0 => 'id',
),
        'cloth_invoices' => array (
  0 => 'id',
),
        'cloth_invoice_items' => array (
  0 => 'id',
),
        'cloth_providers' => array (
  0 => 'id',
),
        'cloth_provider_epp' => array (
  0 => 'id',
),
        'cloth_role' => array (
  0 => 'id',
),
        'colors' => array (
  0 => 'id',
),
        'computer_equipments' => array (
  0 => 'id',
),
        'contracts' => array (
  0 => 'id',
),
        'daily_menus' => array (
  0 => 'id',
),
        'daily_portions' => array (
  0 => 'id',
),
        'dealerships' => array (
  0 => 'id',
),
        'dinners' => array (
  0 => 'id',
),
        'dishes' => array (
  0 => 'id',
),
        'dish_categories' => array (
  0 => 'id',
),
        'dish_category_dish' => array (
  0 => 'id',
),
        'dish_category_serviceables' => array (
  0 => 'id',
),
        'dish_ingredient' => array (
  0 => 'id',
),
        'dish_ingredient_levels' => array (
  0 => 'id',
),
        'dish_recipes' => array (
  0 => 'id',
),
        'dish_recipe_ingredients' => array (
  0 => 'id',
),
        'dish_recipe_levels' => array (
  0 => 'id',
),
        'dosifications' => array (
  0 => 'id',
),
        'epps' => array (
  0 => 'id',
),
        'epp_city_providers' => array (
  0 => 'id',
),
        'epp_role' => array (
  0 => 'id',
),
        'epp_sizes' => array (
  0 => 'id',
),
        'epp_size_pivot' => array (
  0 => 'id',
),
        'equipment_dispatches' => array (
  0 => 'id',
),
        'equipment_histories' => array (
  0 => 'id',
),
        'equipment_invoices' => array (
  0 => 'id',
),
        'equipment_stocks' => array (
  0 => 'id',
),
        'failed_jobs' => array (
  0 => 'id',
),
        'gross_weights' => array (
  0 => 'id',
),
        'guards' => array (
  0 => 'id',
),
        'guard_roles' => array (
  0 => 'id',
),
        'headquarters' => array (
  0 => 'id',
),
        'ingredients' => array (
  0 => 'id',
),
        'ingredient_categories' => array (
  0 => 'id',
),
        'ingredient_city_providers' => array (
  0 => 'id',
),
        'ingredient_costs' => array (
  0 => 'id',
),
        'inputs' => array (
  0 => 'id',
),
        'inventory_stocks' => array (
  0 => 'id',
),
        'inventory_transfers' => array (
  0 => 'id',
),
        'inventory_transfer_items' => array (
  0 => 'id',
),
        'jobs' => array (
  0 => 'id',
),
        'job_batches' => array (
  0 => 'id',
),
        'kitchen_equipments' => array (
  0 => 'id',
),
        'levels' => array (
  0 => 'id',
),
        'liquid_wastes' => array (
  0 => 'id',
),
        'measurement_units' => array (
  0 => 'id',
),
        'menu_cycles' => array (
  0 => 'id',
),
        'menu_structures' => array (
  0 => 'id',
),
        'mercantiles' => array (
  0 => 'id',
),
        'mercantil_sales' => array (
  0 => 'id',
),
        'mercantil_sale_details' => array (
  0 => 'id',
),
        'migrations' => array (
  0 => 'id',
),
        'mines' => array (
  0 => 'id',
),
        'mine_subdealerships' => array (
  0 => 'id',
),
        'model_has_permissions' => array (
  0 => 'permission_id',
  1 => 'model_id',
  2 => 'model_type',
),
        'model_has_roles' => array (
  0 => 'role_id',
  1 => 'model_id',
  2 => 'model_type',
),
        'net_weights' => array (
  0 => 'id',
),
        'nutritional_factors' => array (
  0 => 'id',
),
        'observations' => array (
  0 => 'id',
),
        'password_reset_tokens' => array (
  0 => 'email',
),
        'payment_methods' => array (
  0 => 'id',
),
        'periods' => array (
  0 => 'id',
),
        'period_staffs' => array (
  0 => 'id',
),
        'permissions' => array (
  0 => 'id',
),
        'products' => array (
  0 => 'id',
),
        'product_batches' => array (
  0 => 'id',
),
        'providers' => array (
  0 => 'id',
),
        'purchase_orders' => array (
  0 => 'id',
),
        'purchase_order_items' => array (
  0 => 'id',
),
        'receipt_types' => array (
  0 => 'id',
),
        'recipes' => array (
  0 => 'id',
),
        'roles' => array (
  0 => 'id',
),
        'role_has_permissions' => array (
  0 => 'permission_id',
  1 => 'role_id',
),
        'sales' => array (
  0 => 'id',
),
        'sale_details' => array (
  0 => 'id',
),
        'sale_types' => array (
  0 => 'id',
),
        'serviceables' => array (
  0 => 'id',
),
        'services' => array (
  0 => 'id',
),
        'service_types' => array (
  0 => 'id',
),
        'sessions' => array (
  0 => 'id',
),
        'sizes' => array (
  0 => 'id',
),
        'solid_wastes' => array (
  0 => 'id',
),
        'staff' => array (
  0 => 'id',
),
        'staff_clothes' => array (
  0 => 'id',
),
        'staff_clothes_histories' => array (
  0 => 'id',
),
        'staff_files' => array (
  0 => 'id',
),
        'staff_financials' => array (
  0 => 'id',
),
        'staff_photos' => array (
  0 => 'id',
),
        'structures' => array (
  0 => 'id',
),
        'structure_costs' => array (
  0 => 'id',
),
        'subdealerships' => array (
  0 => 'id',
),
        'subdealership_unit' => array (
  0 => 'id',
),
        'tickets' => array (
  0 => 'id',
),
        'ticket_details' => array (
  0 => 'id',
),
        'units' => array (
  0 => 'id',
),
        'users' => array (
  0 => 'id',
),
        'user_role_area' => array (
  0 => 'id',
),
        'user_units' => array (
  0 => 'id',
),
        'weekly_programs' => array (
  0 => 'id',
),
        'weekly_program_items' => array (
  0 => 'id',
),
    ];

    /** tabla => [columna, tipo] */
    private const AUTO_INCREMENT = [
        'addendums' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'areas' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'area_headquarter' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'area_role' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'atwater_factors' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'businessables' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'businesses' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'business_service' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cafes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cafe_roles' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cafe_satisfactions' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cafe_service' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cafe_user' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'calories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'category_epps' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cities' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'city_provider' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloths' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_cloth_provider' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_inventories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_invoices' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_invoice_items' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_providers' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_provider_epp' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'cloth_role' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'colors' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'computer_equipments' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'contracts' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'daily_menus' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'daily_portions' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dealerships' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dinners' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dishes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_categories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_category_dish' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_category_serviceables' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_ingredient' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_ingredient_levels' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_recipes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_recipe_ingredients' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dish_recipe_levels' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'dosifications' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'epps' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'epp_city_providers' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'epp_role' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'epp_sizes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'epp_size_pivot' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'equipment_dispatches' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'equipment_histories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'equipment_invoices' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'equipment_stocks' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'failed_jobs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'gross_weights' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'guards' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'guard_roles' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'headquarters' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'ingredients' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'ingredient_categories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'ingredient_city_providers' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'ingredient_costs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'inputs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'inventory_stocks' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'inventory_transfers' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'inventory_transfer_items' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'jobs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'kitchen_equipments' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'levels' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'liquid_wastes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'measurement_units' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'menu_cycles' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'menu_structures' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'mercantiles' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'mercantil_sales' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'mercantil_sale_details' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'migrations' => array (
  0 => 'id',
  1 => 'int(10) unsigned',
),
        'mines' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'mine_subdealerships' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'net_weights' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'nutritional_factors' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'observations' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'payment_methods' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'periods' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'period_staffs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'permissions' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'products' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'product_batches' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'providers' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'purchase_orders' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'purchase_order_items' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'receipt_types' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'recipes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'roles' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'sales' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'sale_details' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'sale_types' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'serviceables' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'services' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'service_types' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'sizes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'solid_wastes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff_clothes' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff_clothes_histories' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff_files' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff_financials' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'staff_photos' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'structures' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'structure_costs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'subdealerships' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'subdealership_unit' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'tickets' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'ticket_details' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'units' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'users' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'user_role_area' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'user_units' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'weekly_programs' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
        'weekly_program_items' => array (
  0 => 'id',
  1 => 'bigint(20) unsigned',
),
    ];

    /** [tabla, nombre, [columnas], esUnico] */
    private const INDEXES = [
        ['addendums', 'addendums_contract_id_foreign', array (
  0 => 'contract_id',
), false],
        ['area_headquarter', 'area_headquarter_area_id_foreign', array (
  0 => 'area_id',
), false],
        ['area_headquarter', 'area_headquarter_headquarter_id_foreign', array (
  0 => 'headquarter_id',
), false],
        ['area_role', 'area_role_area_id_foreign', array (
  0 => 'area_id',
), false],
        ['area_role', 'area_role_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['businessables', 'businessables_businessable_type_businessable_id_index', array (
  0 => 'businessable_type',
  1 => 'businessable_id',
), false],
        ['businessables', 'businessables_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['business_service', 'business_service_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['business_service', 'business_service_service_id_foreign', array (
  0 => 'service_id',
), false],
        ['cafes', 'cafes_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['cafe_roles', 'cafe_roles_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['cafe_roles', 'cafe_roles_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['cafe_satisfactions', 'cafe_satisfactions_cafe_id_date_index', array (
  0 => 'cafe_id',
  1 => 'date',
), false],
        ['cafe_service', 'cafe_service_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['cafe_service', 'cafe_service_service_id_foreign', array (
  0 => 'service_id',
), false],
        ['cafe_user', 'cafe_user_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['cafe_user', 'cafe_user_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['calories', 'calories_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['calories', 'calories_unit_measurement_id_foreign', array (
  0 => 'unit_measurement_id',
), false],
        ['city_provider', 'city_provider_city_id_foreign', array (
  0 => 'city_id',
), false],
        ['city_provider', 'city_provider_provider_id_foreign', array (
  0 => 'provider_id',
), false],
        ['cloth_cloth_provider', 'cloth_cloth_provider_cloth_id_foreign', array (
  0 => 'cloth_id',
), false],
        ['cloth_cloth_provider', 'cloth_cloth_provider_cloth_provider_id_foreign', array (
  0 => 'cloth_provider_id',
), false],
        ['cloth_inventories', 'cloth_inventories_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['cloth_inventories', 'cloth_inventories_cloth_id_foreign', array (
  0 => 'cloth_id',
), false],
        ['cloth_inventories', 'cloth_inventories_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['cloth_invoices', 'cloth_invoices_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['cloth_invoices', 'cloth_invoices_cloth_provider_id_foreign', array (
  0 => 'cloth_provider_id',
), false],
        ['cloth_invoices', 'cloth_invoices_headquarter_id_foreign', array (
  0 => 'headquarter_id',
), false],
        ['cloth_invoices', 'cloth_invoices_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['cloth_invoice_items', 'cloth_invoice_items_cloth_id_foreign', array (
  0 => 'cloth_id',
), false],
        ['cloth_invoice_items', 'cloth_invoice_items_cloth_invoice_id_foreign', array (
  0 => 'cloth_invoice_id',
), false],
        ['cloth_invoice_items', 'cloth_invoice_items_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['cloth_invoice_items', 'cloth_invoice_items_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['cloth_provider_epp', 'cloth_provider_epp_cloth_provider_id_foreign', array (
  0 => 'cloth_provider_id',
), false],
        ['cloth_provider_epp', 'cloth_provider_epp_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['cloth_role', 'cloth_role_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['cloth_role', 'cloth_role_cloth_id_foreign', array (
  0 => 'cloth_id',
), false],
        ['cloth_role', 'cloth_role_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['colors', 'colors_name_unique', array (
  0 => 'name',
), true],
        ['computer_equipments', 'computer_equipments_equipment_invoice_id_foreign', array (
  0 => 'equipment_invoice_id',
), false],
        ['computer_equipments', 'computer_equipments_responsible_id_foreign', array (
  0 => 'responsible_id',
), false],
        ['computer_equipments', 'computer_equipments_storage_headquarter_id_foreign', array (
  0 => 'storage_headquarter_id',
), false],
        ['contracts', 'contracts_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['contracts', 'contracts_dealership_id_foreign', array (
  0 => 'dealership_id',
), false],
        ['daily_portions', 'daily_portions_weekly_program_id_foreign', array (
  0 => 'weekly_program_id',
), false],
        ['dinners', 'dinners_mine_id_foreign', array (
  0 => 'mine_id',
), false],
        ['dinners', 'dinners_subdealership_id_foreign', array (
  0 => 'subdealership_id',
), false],
        ['dishes', 'dishes_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['dish_category_dish', 'dish_category_dish_dish_category_id_foreign', array (
  0 => 'dish_category_id',
), false],
        ['dish_category_dish', 'dish_category_dish_dish_id_foreign', array (
  0 => 'dish_id',
), false],
        ['dish_category_serviceables', 'dish_category_serviceables_dish_category_id_foreign', array (
  0 => 'dish_category_id',
), false],
        ['dish_category_serviceables', 'dish_category_serviceables_measurement_unit_id_foreign', array (
  0 => 'measurement_unit_id',
), false],
        ['dish_category_serviceables', 'dish_category_serviceables_serviceable_id_foreign', array (
  0 => 'serviceable_id',
), false],
        ['dish_ingredient', 'dish_ingredient_dish_id_foreign', array (
  0 => 'dish_id',
), false],
        ['dish_ingredient', 'dish_ingredient_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['dish_ingredient_levels', 'dish_ingredient_levels_dish_id_foreign', array (
  0 => 'dish_id',
), false],
        ['dish_ingredient_levels', 'dish_ingredient_levels_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['dish_ingredient_levels', 'dish_ingredient_levels_level_id_foreign', array (
  0 => 'level_id',
), false],
        ['dish_recipes', 'dish_recipes_dish_id_foreign', array (
  0 => 'dish_id',
), false],
        ['dish_recipes', 'dish_recipes_dish_level_unique', array (
  0 => 'dish_id',
  1 => 'level_id',
), true],
        ['dish_recipes', 'dish_recipes_level_id_foreign', array (
  0 => 'level_id',
), false],
        ['dish_recipe_ingredients', 'dish_recipe_ingredients_dish_recipe_id_foreign', array (
  0 => 'dish_recipe_id',
), false],
        ['dish_recipe_ingredients', 'dish_recipe_ingredients_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['dish_recipe_levels', 'dish_recipe_levels_dish_recipe_id_foreign', array (
  0 => 'dish_recipe_id',
), false],
        ['dish_recipe_levels', 'dish_recipe_levels_level_id_foreign', array (
  0 => 'level_id',
), false],
        ['dosifications', 'dosifications_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['epps', 'epps_category_epp_id_foreign', array (
  0 => 'category_epp_id',
), false],
        ['epp_city_providers', 'epp_city_providers_city_id_foreign', array (
  0 => 'city_id',
), false],
        ['epp_city_providers', 'epp_city_providers_cloth_provider_id_foreign', array (
  0 => 'cloth_provider_id',
), false],
        ['epp_city_providers', 'epp_city_providers_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['epp_role', 'epp_role_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['epp_role', 'epp_role_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['epp_role', 'epp_role_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['epp_role', 'epp_role_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['epp_sizes', 'epp_sizes_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['epp_size_pivot', 'epp_size_pivot_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['epp_size_pivot', 'epp_size_pivot_size_id_foreign', array (
  0 => 'size_id',
), false],
        ['equipment_dispatches', 'equipment_dispatches_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['equipment_dispatches', 'equipment_dispatches_dispatched_by_foreign', array (
  0 => 'dispatched_by',
), false],
        ['equipment_dispatches', 'equipment_dispatches_dispatch_number_unique', array (
  0 => 'dispatch_number',
), true],
        ['equipment_dispatches', 'equipment_dispatches_guide_number_index', array (
  0 => 'guide_number',
), false],
        ['equipment_dispatches', 'equipment_dispatches_origin_cafe_id_foreign', array (
  0 => 'origin_cafe_id',
), false],
        ['equipment_dispatches', 'equipment_dispatches_origin_headquarter_id_foreign', array (
  0 => 'origin_headquarter_id',
), false],
        ['equipment_dispatches', 'equipment_dispatches_received_by_foreign', array (
  0 => 'received_by',
), false],
        ['equipment_dispatches', 'equipment_dispatches_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['equipment_dispatches', 'equip_dispatch_morphable_index', array (
  0 => 'equipable_type',
  1 => 'equipable_id',
), false],
        ['equipment_histories', 'equipment_histories_equipable_type_equipable_id_index', array (
  0 => 'equipable_type',
  1 => 'equipable_id',
), false],
        ['equipment_histories', 'equipment_histories_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['equipment_histories', 'equipment_histories_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['equipment_invoices', 'equipment_invoices_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['equipment_invoices', 'equipment_invoices_provider_id_foreign', array (
  0 => 'provider_id',
), false],
        ['equipment_invoices', 'equipment_invoices_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['equipment_stocks', 'equipment_stocks_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['equipment_stocks', 'equipment_stocks_location_unique', array (
  0 => 'stockable_type',
  1 => 'stockable_id',
  2 => 'cafe_id',
  3 => 'unit_id',
), true],
        ['equipment_stocks', 'equipment_stocks_stockable_type_stockable_id_index', array (
  0 => 'stockable_type',
  1 => 'stockable_id',
), false],
        ['equipment_stocks', 'equipment_stocks_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['failed_jobs', 'failed_jobs_uuid_unique', array (
  0 => 'uuid',
), true],
        ['gross_weights', 'gross_weights_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['gross_weights', 'gross_weights_unit_measurement_id_foreign', array (
  0 => 'unit_measurement_id',
), false],
        ['guards', 'guards_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['guard_roles', 'guard_roles_guard_id_foreign', array (
  0 => 'guard_id',
), false],
        ['guard_roles', 'guard_roles_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['guard_roles', 'guard_roles_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['headquarters', 'headquarters_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['ingredients', 'ingredients_atwater_factor_id_foreign', array (
  0 => 'atwater_factor_id',
), false],
        ['ingredients', 'ingredients_ingredient_category_id_foreign', array (
  0 => 'ingredient_category_id',
), false],
        ['ingredients', 'ingredients_measurement_unit_id_foreign', array (
  0 => 'measurement_unit_id',
), false],
        ['ingredient_city_providers', 'ingredient_city_providers_measurement_unit_id_foreign', array (
  0 => 'measurement_unit_id',
), false],
        ['ingredient_city_providers', 'ingredient_city_provider_city_id_foreign', array (
  0 => 'city_id',
), false],
        ['ingredient_city_providers', 'ingredient_city_provider_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['ingredient_city_providers', 'ingredient_city_provider_provider_id_foreign', array (
  0 => 'provider_id',
), false],
        ['ingredient_costs', 'ingredient_costs_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['inputs', 'inputs_code_unique', array (
  0 => 'code',
), true],
        ['inputs', 'inputs_name_index', array (
  0 => 'name',
), false],
        ['inventory_stocks', 'inventory_stocks_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['inventory_stocks', 'inventory_stocks_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['inventory_stocks', 'inventory_stocks_headquarter_id_foreign', array (
  0 => 'headquarter_id',
), false],
        ['inventory_stocks', 'inventory_stocks_stockable_type_stockable_id_index', array (
  0 => 'stockable_type',
  1 => 'stockable_id',
), false],
        ['inventory_stocks', 'inventory_stocks_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['inventory_transfers', 'inventory_transfers_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['inventory_transfers', 'inventory_transfers_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['inventory_transfer_items', 'inventory_transfer_items_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['inventory_transfer_items', 'inventory_transfer_items_inventory_transfer_id_foreign', array (
  0 => 'inventory_transfer_id',
), false],
        ['inventory_transfer_items', 'inventory_transfer_items_stockable_type_stockable_id_index', array (
  0 => 'stockable_type',
  1 => 'stockable_id',
), false],
        ['jobs', 'jobs_queue_index', array (
  0 => 'queue',
), false],
        ['kitchen_equipments', 'kitchen_equipments_equipment_invoice_id_foreign', array (
  0 => 'equipment_invoice_id',
), false],
        ['kitchen_equipments', 'kitchen_equipments_responsible_id_foreign', array (
  0 => 'responsible_id',
), false],
        ['kitchen_equipments', 'kitchen_equipments_storage_headquarter_id_foreign', array (
  0 => 'storage_headquarter_id',
), false],
        ['liquid_wastes', 'liquid_wastes_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['liquid_wastes', 'liquid_wastes_unit_measurement_id_foreign', array (
  0 => 'unit_measurement_id',
), false],
        ['menu_structures', 'menu_structures_dish_category_id_foreign', array (
  0 => 'dish_category_id',
), false],
        ['mercantiles', 'mercantiles_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['mercantil_sales', 'mercantil_sales_dinner_id_foreign', array (
  0 => 'dinner_id',
), false],
        ['mercantil_sales', 'mercantil_sales_mercantil_id_foreign', array (
  0 => 'mercantil_id',
), false],
        ['mercantil_sales', 'mercantil_sales_sale_type_id_foreign', array (
  0 => 'sale_type_id',
), false],
        ['mercantil_sales', 'mercantil_sales_subdealership_id_foreign', array (
  0 => 'subdealership_id',
), false],
        ['mercantil_sales', 'mercantil_sales_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['mercantil_sales', 'mercantil_sales_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['mercantil_sale_details', 'mercantil_sale_details_mercantil_sale_id_foreign', array (
  0 => 'mercantil_sale_id',
), false],
        ['mercantil_sale_details', 'mercantil_sale_details_product_id_foreign', array (
  0 => 'product_id',
), false],
        ['mines', 'mines_dealership_id_foreign', array (
  0 => 'dealership_id',
), false],
        ['mine_subdealerships', 'mine_subdealerships_mine_id_foreign', array (
  0 => 'mine_id',
), false],
        ['mine_subdealerships', 'mine_subdealerships_subdealership_id_foreign', array (
  0 => 'subdealership_id',
), false],
        ['model_has_permissions', 'model_has_permissions_model_id_model_type_index', array (
  0 => 'model_id',
  1 => 'model_type',
), false],
        ['model_has_roles', 'model_has_roles_model_id_model_type_index', array (
  0 => 'model_id',
  1 => 'model_type',
), false],
        ['net_weights', 'net_weights_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['net_weights', 'net_weights_unit_measurement_id_foreign', array (
  0 => 'unit_measurement_id',
), false],
        ['nutritional_factors', 'nutritional_factors_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['observations', 'observations_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['observations', 'observations_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['periods', 'periods_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['period_staffs', 'period_staffs_period_id_foreign', array (
  0 => 'period_id',
), false],
        ['period_staffs', 'period_staffs_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['permissions', 'permissions_name_guard_name_unique', array (
  0 => 'name',
  1 => 'guard_name',
), true],
        ['products', 'products_mercantil_id_foreign', array (
  0 => 'mercantil_id',
), false],
        ['product_batches', 'product_batches_product_id_expiration_date_index', array (
  0 => 'product_id',
  1 => 'expiration_date',
), false],
        ['purchase_orders', 'purchase_orders_weekly_program_id_foreign', array (
  0 => 'weekly_program_id',
), false],
        ['purchase_order_items', 'purchase_order_items_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['purchase_order_items', 'purchase_order_items_purchase_order_id_foreign', array (
  0 => 'purchase_order_id',
), false],
        ['recipes', 'recipes_dish_id_ingredient_id_unique', array (
  0 => 'dish_id',
  1 => 'ingredient_id',
), true],
        ['recipes', 'recipes_ingredient_id_foreign', array (
  0 => 'ingredient_id',
), false],
        ['recipes', 'recipes_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['roles', 'roles_area_id_foreign', array (
  0 => 'area_id',
), false],
        ['roles', 'roles_name_guard_name_unique', array (
  0 => 'name',
  1 => 'guard_name',
), true],
        ['role_has_permissions', 'role_has_permissions_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['sales', 'sales_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['sales', 'sales_cafe_id_date_index', array (
  0 => 'cafe_id',
  1 => 'date',
), false],
        ['sales', 'sales_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['sales', 'sales_date_id_index', array (
  0 => 'date',
  1 => 'id',
), false],
        ['sales', 'sales_date_index', array (
  0 => 'date',
), false],
        ['sales', 'sales_dinner_id_foreign', array (
  0 => 'dinner_id',
), false],
        ['sales', 'sales_mine_id_foreign', array (
  0 => 'mine_id',
), false],
        ['sales', 'sales_payment_method_id_foreign', array (
  0 => 'payment_method_id',
), false],
        ['sales', 'sales_sale_type_id_foreign', array (
  0 => 'sale_type_id',
), false],
        ['sales', 'sales_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['sale_details', 'sale_details_sale_id_foreign', array (
  0 => 'sale_id',
), false],
        ['sale_details', 'sale_details_service_id_foreign', array (
  0 => 'service_id',
), false],
        ['serviceables', 'serviceables_serviceable_type_serviceable_id_index', array (
  0 => 'serviceable_type',
  1 => 'serviceable_id',
), false],
        ['serviceables', 'serviceables_service_id_foreign', array (
  0 => 'service_id',
), false],
        ['sessions', 'sessions_last_activity_index', array (
  0 => 'last_activity',
), false],
        ['sessions', 'sessions_user_id_index', array (
  0 => 'user_id',
), false],
        ['sizes', 'sizes_name_unique', array (
  0 => 'name',
), true],
        ['solid_wastes', 'solid_wastes_dish_ingredient_level_id_foreign', array (
  0 => 'dish_ingredient_level_id',
), false],
        ['solid_wastes', 'solid_wastes_unit_measurement_id_foreign', array (
  0 => 'unit_measurement_id',
), false],
        ['staff', 'staff_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['staff', 'staff_staffable_type_staffable_id_index', array (
  0 => 'staffable_type',
  1 => 'staffable_id',
), false],
        ['staff', 'staff_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['staff_clothes', 'staff_clothes_cloth_id_foreign', array (
  0 => 'cloth_id',
), false],
        ['staff_clothes', 'staff_clothes_color_id_foreign', array (
  0 => 'color_id',
), false],
        ['staff_clothes', 'staff_clothes_epp_id_foreign', array (
  0 => 'epp_id',
), false],
        ['staff_clothes', 'staff_clothes_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['staff_clothes_histories', 'staff_clothes_histories_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['staff_clothes_histories', 'staff_clothes_histories_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['staff_files', 'staff_files_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['staff_financials', 'staff_financials_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['staff_financials', 'staff_financials_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['staff_photos', 'staff_photos_staff_id_foreign', array (
  0 => 'staff_id',
), false],
        ['structures', 'structures_serviceable_id_foreign', array (
  0 => 'serviceable_id',
), false],
        ['structure_costs', 'structure_costs_structure_id_foreign', array (
  0 => 'structure_id',
), false],
        ['subdealership_unit', 'subdealership_unit_subdealership_id_foreign', array (
  0 => 'subdealership_id',
), false],
        ['subdealership_unit', 'subdealership_unit_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['tickets', 'tickets_dinner_id_foreign', array (
  0 => 'dinner_id',
), false],
        ['tickets', 'tickets_sale_id_foreign', array (
  0 => 'sale_id',
), false],
        ['tickets', 'tickets_subdealership_name_index', array (
  0 => 'subdealership_name',
), false],
        ['ticket_details', 'ticket_details_service_id_foreign', array (
  0 => 'service_id',
), false],
        ['ticket_details', 'ticket_details_ticket_id_foreign', array (
  0 => 'ticket_id',
), false],
        ['units', 'units_mine_id_foreign', array (
  0 => 'mine_id',
), false],
        ['users', 'users_business_id_foreign', array (
  0 => 'business_id',
), false],
        ['users', 'users_email_unique', array (
  0 => 'email',
), true],
        ['users', 'users_mine_id_foreign', array (
  0 => 'mine_id',
), false],
        ['user_role_area', 'user_role_area_area_id_foreign', array (
  0 => 'area_id',
), false],
        ['user_role_area', 'user_role_area_role_id_foreign', array (
  0 => 'role_id',
), false],
        ['user_role_area', 'user_role_area_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['user_units', 'user_units_unit_id_foreign', array (
  0 => 'unit_id',
), false],
        ['user_units', 'user_units_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['weekly_programs', 'weekly_programs_cafe_id_foreign', array (
  0 => 'cafe_id',
), false],
        ['weekly_programs', 'weekly_programs_structure_id_foreign', array (
  0 => 'structure_id',
), false],
        ['weekly_programs', 'weekly_programs_user_id_foreign', array (
  0 => 'user_id',
), false],
        ['weekly_program_items', 'weekly_program_items_dish_category_id_foreign', array (
  0 => 'dish_category_id',
), false],
        ['weekly_program_items', 'weekly_program_items_dish_id_foreign', array (
  0 => 'dish_id',
), false],
        ['weekly_program_items', 'weekly_program_items_weekly_program_id_foreign', array (
  0 => 'weekly_program_id',
), false],
    ];

    /** [tabla, nombre, columna, tablaRef, columnaRef, onDelete, onUpdate] */
    private const FOREIGN_KEYS = [
        ['addendums', 'addendums_contract_id_foreign', 'contract_id', 'contracts', 'id', 'SET NULL', 'RESTRICT'],
        ['area_headquarter', 'area_headquarter_area_id_foreign', 'area_id', 'areas', 'id', 'CASCADE', 'RESTRICT'],
        ['area_headquarter', 'area_headquarter_headquarter_id_foreign', 'headquarter_id', 'headquarters', 'id', 'CASCADE', 'RESTRICT'],
        ['area_role', 'area_role_area_id_foreign', 'area_id', 'areas', 'id', 'SET NULL', 'RESTRICT'],
        ['area_role', 'area_role_role_id_foreign', 'role_id', 'roles', 'id', 'SET NULL', 'RESTRICT'],
        ['businessables', 'businessables_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['business_service', 'business_service_business_id_foreign', 'business_id', 'businesses', 'id', 'CASCADE', 'RESTRICT'],
        ['business_service', 'business_service_service_id_foreign', 'service_id', 'services', 'id', 'CASCADE', 'RESTRICT'],
        ['cafes', 'cafes_unit_id_foreign', 'unit_id', 'units', 'id', 'RESTRICT', 'RESTRICT'],
        ['cafe_roles', 'cafe_roles_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_roles', 'cafe_roles_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_satisfactions', 'cafe_satisfactions_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_service', 'cafe_service_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_service', 'cafe_service_service_id_foreign', 'service_id', 'services', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_user', 'cafe_user_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cafe_user', 'cafe_user_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT'],
        ['calories', 'calories_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['calories', 'calories_unit_measurement_id_foreign', 'unit_measurement_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['city_provider', 'city_provider_city_id_foreign', 'city_id', 'cities', 'id', 'CASCADE', 'RESTRICT'],
        ['city_provider', 'city_provider_provider_id_foreign', 'provider_id', 'providers', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_cloth_provider', 'cloth_cloth_provider_cloth_id_foreign', 'cloth_id', 'cloths', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_cloth_provider', 'cloth_cloth_provider_cloth_provider_id_foreign', 'cloth_provider_id', 'cloth_providers', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_inventories', 'cloth_inventories_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_inventories', 'cloth_inventories_cloth_id_foreign', 'cloth_id', 'cloths', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_inventories', 'cloth_inventories_color_id_foreign', 'color_id', 'colors', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_invoices', 'cloth_invoices_business_id_foreign', 'business_id', 'businesses', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_invoices', 'cloth_invoices_cloth_provider_id_foreign', 'cloth_provider_id', 'cloth_providers', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_invoices', 'cloth_invoices_headquarter_id_foreign', 'headquarter_id', 'headquarters', 'id', 'SET NULL', 'RESTRICT'],
        ['cloth_invoices', 'cloth_invoices_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['cloth_invoice_items', 'cloth_invoice_items_cloth_id_foreign', 'cloth_id', 'cloths', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_invoice_items', 'cloth_invoice_items_cloth_invoice_id_foreign', 'cloth_invoice_id', 'cloth_invoices', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_invoice_items', 'cloth_invoice_items_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['cloth_invoice_items', 'cloth_invoice_items_epp_id_foreign', 'epp_id', 'epps', 'id', 'SET NULL', 'RESTRICT'],
        ['cloth_provider_epp', 'cloth_provider_epp_cloth_provider_id_foreign', 'cloth_provider_id', 'cloth_providers', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_provider_epp', 'cloth_provider_epp_epp_id_foreign', 'epp_id', 'epps', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_role', 'cloth_role_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_role', 'cloth_role_cloth_id_foreign', 'cloth_id', 'cloths', 'id', 'CASCADE', 'RESTRICT'],
        ['cloth_role', 'cloth_role_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['computer_equipments', 'computer_equipments_equipment_invoice_id_foreign', 'equipment_invoice_id', 'equipment_invoices', 'id', 'SET NULL', 'RESTRICT'],
        ['computer_equipments', 'computer_equipments_responsible_id_foreign', 'responsible_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['computer_equipments', 'computer_equipments_storage_headquarter_id_foreign', 'storage_headquarter_id', 'headquarters', 'id', 'SET NULL', 'RESTRICT'],
        ['contracts', 'contracts_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['contracts', 'contracts_dealership_id_foreign', 'dealership_id', 'dealerships', 'id', 'SET NULL', 'RESTRICT'],
        ['daily_portions', 'daily_portions_weekly_program_id_foreign', 'weekly_program_id', 'weekly_programs', 'id', 'CASCADE', 'RESTRICT'],
        ['dinners', 'dinners_mine_id_foreign', 'mine_id', 'mines', 'id', 'SET NULL', 'RESTRICT'],
        ['dinners', 'dinners_subdealership_id_foreign', 'subdealership_id', 'subdealerships', 'id', 'SET NULL', 'RESTRICT'],
        ['dishes', 'dishes_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['dish_category_dish', 'dish_category_dish_dish_category_id_foreign', 'dish_category_id', 'dish_categories', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_category_dish', 'dish_category_dish_dish_id_foreign', 'dish_id', 'dishes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_category_serviceables', 'dish_category_serviceables_dish_category_id_foreign', 'dish_category_id', 'dish_categories', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_category_serviceables', 'dish_category_serviceables_measurement_unit_id_foreign', 'measurement_unit_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['dish_category_serviceables', 'dish_category_serviceables_serviceable_id_foreign', 'serviceable_id', 'serviceables', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_ingredient', 'dish_ingredient_dish_id_foreign', 'dish_id', 'dishes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_ingredient', 'dish_ingredient_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_ingredient_levels', 'dish_ingredient_levels_dish_id_foreign', 'dish_id', 'dishes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_ingredient_levels', 'dish_ingredient_levels_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_ingredient_levels', 'dish_ingredient_levels_level_id_foreign', 'level_id', 'levels', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipes', 'dish_recipes_dish_id_foreign', 'dish_id', 'dishes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipes', 'dish_recipes_level_id_foreign', 'level_id', 'levels', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipe_ingredients', 'dish_recipe_ingredients_dish_recipe_id_foreign', 'dish_recipe_id', 'dish_recipes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipe_ingredients', 'dish_recipe_ingredients_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipe_levels', 'dish_recipe_levels_dish_recipe_id_foreign', 'dish_recipe_id', 'dish_recipes', 'id', 'CASCADE', 'RESTRICT'],
        ['dish_recipe_levels', 'dish_recipe_levels_level_id_foreign', 'level_id', 'levels', 'id', 'CASCADE', 'RESTRICT'],
        ['dosifications', 'dosifications_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['epps', 'epps_category_epp_id_foreign', 'category_epp_id', 'category_epps', 'id', 'SET NULL', 'RESTRICT'],
        ['epp_city_providers', 'epp_city_providers_city_id_foreign', 'city_id', 'cities', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_city_providers', 'epp_city_providers_cloth_provider_id_foreign', 'cloth_provider_id', 'cloth_providers', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_city_providers', 'epp_city_providers_epp_id_foreign', 'epp_id', 'epps', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_role', 'epp_role_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_role', 'epp_role_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['epp_role', 'epp_role_epp_id_foreign', 'epp_id', 'epps', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_role', 'epp_role_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_sizes', 'epp_sizes_epp_id_foreign', 'epp_id', 'epps', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_size_pivot', 'epp_size_pivot_epp_id_foreign', 'epp_id', 'epps', 'id', 'CASCADE', 'RESTRICT'],
        ['epp_size_pivot', 'epp_size_pivot_size_id_foreign', 'size_id', 'sizes', 'id', 'CASCADE', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_dispatched_by_foreign', 'dispatched_by', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_origin_cafe_id_foreign', 'origin_cafe_id', 'cafes', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_origin_headquarter_id_foreign', 'origin_headquarter_id', 'headquarters', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_received_by_foreign', 'received_by', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_dispatches', 'equipment_dispatches_staff_id_foreign', 'staff_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_histories', 'equipment_histories_staff_id_foreign', 'staff_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_histories', 'equipment_histories_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT'],
        ['equipment_invoices', 'equipment_invoices_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_invoices', 'equipment_invoices_provider_id_foreign', 'provider_id', 'providers', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_invoices', 'equipment_invoices_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['equipment_stocks', 'equipment_stocks_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['equipment_stocks', 'equipment_stocks_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['gross_weights', 'gross_weights_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['gross_weights', 'gross_weights_unit_measurement_id_foreign', 'unit_measurement_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['guards', 'guards_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['guard_roles', 'guard_roles_guard_id_foreign', 'guard_id', 'guards', 'id', 'CASCADE', 'RESTRICT'],
        ['guard_roles', 'guard_roles_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['guard_roles', 'guard_roles_staff_id_foreign', 'staff_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['headquarters', 'headquarters_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['ingredients', 'ingredients_atwater_factor_id_foreign', 'atwater_factor_id', 'atwater_factors', 'id', 'SET NULL', 'RESTRICT'],
        ['ingredients', 'ingredients_ingredient_category_id_foreign', 'ingredient_category_id', 'ingredient_categories', 'id', 'SET NULL', 'CASCADE'],
        ['ingredients', 'ingredients_measurement_unit_id_foreign', 'measurement_unit_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['ingredient_city_providers', 'ingredient_city_providers_measurement_unit_id_foreign', 'measurement_unit_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['ingredient_city_providers', 'ingredient_city_provider_city_id_foreign', 'city_id', 'cities', 'id', 'CASCADE', 'RESTRICT'],
        ['ingredient_city_providers', 'ingredient_city_provider_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['ingredient_city_providers', 'ingredient_city_provider_provider_id_foreign', 'provider_id', 'providers', 'id', 'CASCADE', 'RESTRICT'],
        ['ingredient_costs', 'ingredient_costs_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['inventory_stocks', 'inventory_stocks_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['inventory_stocks', 'inventory_stocks_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['inventory_stocks', 'inventory_stocks_headquarter_id_foreign', 'headquarter_id', 'headquarters', 'id', 'CASCADE', 'RESTRICT'],
        ['inventory_stocks', 'inventory_stocks_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['inventory_transfers', 'inventory_transfers_staff_id_foreign', 'staff_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['inventory_transfers', 'inventory_transfers_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['inventory_transfer_items', 'inventory_transfer_items_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['inventory_transfer_items', 'inventory_transfer_items_inventory_transfer_id_foreign', 'inventory_transfer_id', 'inventory_transfers', 'id', 'CASCADE', 'RESTRICT'],
        ['kitchen_equipments', 'kitchen_equipments_equipment_invoice_id_foreign', 'equipment_invoice_id', 'equipment_invoices', 'id', 'SET NULL', 'RESTRICT'],
        ['kitchen_equipments', 'kitchen_equipments_responsible_id_foreign', 'responsible_id', 'staff', 'id', 'SET NULL', 'RESTRICT'],
        ['kitchen_equipments', 'kitchen_equipments_storage_headquarter_id_foreign', 'storage_headquarter_id', 'headquarters', 'id', 'SET NULL', 'RESTRICT'],
        ['liquid_wastes', 'liquid_wastes_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['liquid_wastes', 'liquid_wastes_unit_measurement_id_foreign', 'unit_measurement_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['menu_structures', 'menu_structures_dish_category_id_foreign', 'dish_category_id', 'dish_categories', 'id', 'CASCADE', 'RESTRICT'],
        ['mercantiles', 'mercantiles_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_dinner_id_foreign', 'dinner_id', 'dinners', 'id', 'SET NULL', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_mercantil_id_foreign', 'mercantil_id', 'mercantiles', 'id', 'CASCADE', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_sale_type_id_foreign', 'sale_type_id', 'sale_types', 'id', 'SET NULL', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_subdealership_id_foreign', 'subdealership_id', 'subdealerships', 'id', 'SET NULL', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_unit_id_foreign', 'unit_id', 'units', 'id', 'SET NULL', 'RESTRICT'],
        ['mercantil_sales', 'mercantil_sales_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['mercantil_sale_details', 'mercantil_sale_details_mercantil_sale_id_foreign', 'mercantil_sale_id', 'mercantil_sales', 'id', 'CASCADE', 'RESTRICT'],
        ['mercantil_sale_details', 'mercantil_sale_details_product_id_foreign', 'product_id', 'products', 'id', 'SET NULL', 'RESTRICT'],
        ['mines', 'mines_dealership_id_foreign', 'dealership_id', 'dealerships', 'id', 'SET NULL', 'RESTRICT'],
        ['mine_subdealerships', 'mine_subdealerships_mine_id_foreign', 'mine_id', 'mines', 'id', 'CASCADE', 'RESTRICT'],
        ['mine_subdealerships', 'mine_subdealerships_subdealership_id_foreign', 'subdealership_id', 'subdealerships', 'id', 'CASCADE', 'RESTRICT'],
        ['model_has_permissions', 'model_has_permissions_permission_id_foreign', 'permission_id', 'permissions', 'id', 'CASCADE', 'RESTRICT'],
        ['model_has_roles', 'model_has_roles_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['net_weights', 'net_weights_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['net_weights', 'net_weights_unit_measurement_id_foreign', 'unit_measurement_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['nutritional_factors', 'nutritional_factors_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['observations', 'observations_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['observations', 'observations_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT'],
        ['periods', 'periods_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['period_staffs', 'period_staffs_period_id_foreign', 'period_id', 'periods', 'id', 'CASCADE', 'RESTRICT'],
        ['period_staffs', 'period_staffs_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['products', 'products_mercantil_id_foreign', 'mercantil_id', 'mercantiles', 'id', 'CASCADE', 'RESTRICT'],
        ['product_batches', 'product_batches_product_id_foreign', 'product_id', 'products', 'id', 'CASCADE', 'RESTRICT'],
        ['purchase_orders', 'purchase_orders_weekly_program_id_foreign', 'weekly_program_id', 'weekly_programs', 'id', 'CASCADE', 'RESTRICT'],
        ['purchase_order_items', 'purchase_order_items_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'RESTRICT', 'RESTRICT'],
        ['purchase_order_items', 'purchase_order_items_purchase_order_id_foreign', 'purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT'],
        ['recipes', 'recipes_dish_id_foreign', 'dish_id', 'dishes', 'id', 'CASCADE', 'RESTRICT'],
        ['recipes', 'recipes_ingredient_id_foreign', 'ingredient_id', 'ingredients', 'id', 'CASCADE', 'RESTRICT'],
        ['recipes', 'recipes_unit_id_foreign', 'unit_id', 'measurement_units', 'id', 'RESTRICT', 'RESTRICT'],
        ['roles', 'roles_area_id_foreign', 'area_id', 'areas', 'id', 'SET NULL', 'RESTRICT'],
        ['role_has_permissions', 'role_has_permissions_permission_id_foreign', 'permission_id', 'permissions', 'id', 'CASCADE', 'RESTRICT'],
        ['role_has_permissions', 'role_has_permissions_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['sales', 'sales_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_dinner_id_foreign', 'dinner_id', 'dinners', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_mine_id_foreign', 'mine_id', 'mines', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_payment_method_id_foreign', 'payment_method_id', 'payment_methods', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_sale_type_id_foreign', 'sale_type_id', 'sale_types', 'id', 'SET NULL', 'RESTRICT'],
        ['sales', 'sales_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['sale_details', 'sale_details_sale_id_foreign', 'sale_id', 'sales', 'id', 'SET NULL', 'RESTRICT'],
        ['sale_details', 'sale_details_service_id_foreign', 'service_id', 'services', 'id', 'SET NULL', 'RESTRICT'],
        ['serviceables', 'serviceables_service_id_foreign', 'service_id', 'services', 'id', 'SET NULL', 'RESTRICT'],
        ['solid_wastes', 'solid_wastes_dish_ingredient_level_id_foreign', 'dish_ingredient_level_id', 'dish_ingredient_levels', 'id', 'CASCADE', 'RESTRICT'],
        ['solid_wastes', 'solid_wastes_unit_measurement_id_foreign', 'unit_measurement_id', 'measurement_units', 'id', 'SET NULL', 'RESTRICT'],
        ['staff', 'staff_role_id_foreign', 'role_id', 'roles', 'id', 'CASCADE', 'RESTRICT'],
        ['staff', 'staff_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['staff_clothes', 'staff_clothes_cloth_id_foreign', 'cloth_id', 'cloths', 'id', 'SET NULL', 'RESTRICT'],
        ['staff_clothes', 'staff_clothes_color_id_foreign', 'color_id', 'colors', 'id', 'SET NULL', 'RESTRICT'],
        ['staff_clothes', 'staff_clothes_epp_id_foreign', 'epp_id', 'epps', 'id', 'SET NULL', 'RESTRICT'],
        ['staff_clothes', 'staff_clothes_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_clothes_histories', 'staff_clothes_histories_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_clothes_histories', 'staff_clothes_histories_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_files', 'staff_files_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_financials', 'staff_financials_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_financials', 'staff_financials_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['staff_photos', 'staff_photos_staff_id_foreign', 'staff_id', 'staff', 'id', 'CASCADE', 'RESTRICT'],
        ['structures', 'structures_serviceable_id_foreign', 'serviceable_id', 'serviceables', 'id', 'CASCADE', 'RESTRICT'],
        ['structure_costs', 'structure_costs_structure_id_foreign', 'structure_id', 'structures', 'id', 'CASCADE', 'RESTRICT'],
        ['subdealership_unit', 'subdealership_unit_subdealership_id_foreign', 'subdealership_id', 'subdealerships', 'id', 'CASCADE', 'RESTRICT'],
        ['subdealership_unit', 'subdealership_unit_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['tickets', 'tickets_dinner_id_foreign', 'dinner_id', 'dinners', 'id', 'SET NULL', 'RESTRICT'],
        ['tickets', 'tickets_sale_id_foreign', 'sale_id', 'sales', 'id', 'SET NULL', 'RESTRICT'],
        ['ticket_details', 'ticket_details_service_id_foreign', 'service_id', 'services', 'id', 'SET NULL', 'RESTRICT'],
        ['ticket_details', 'ticket_details_ticket_id_foreign', 'ticket_id', 'tickets', 'id', 'SET NULL', 'RESTRICT'],
        ['units', 'units_mine_id_foreign', 'mine_id', 'mines', 'id', 'RESTRICT', 'RESTRICT'],
        ['users', 'users_business_id_foreign', 'business_id', 'businesses', 'id', 'SET NULL', 'RESTRICT'],
        ['users', 'users_mine_id_foreign', 'mine_id', 'mines', 'id', 'SET NULL', 'RESTRICT'],
        ['user_role_area', 'user_role_area_area_id_foreign', 'area_id', 'areas', 'id', 'SET NULL', 'RESTRICT'],
        ['user_role_area', 'user_role_area_role_id_foreign', 'role_id', 'roles', 'id', 'SET NULL', 'RESTRICT'],
        ['user_role_area', 'user_role_area_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['user_units', 'user_units_unit_id_foreign', 'unit_id', 'units', 'id', 'CASCADE', 'RESTRICT'],
        ['user_units', 'user_units_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT'],
        ['weekly_programs', 'weekly_programs_cafe_id_foreign', 'cafe_id', 'cafes', 'id', 'CASCADE', 'RESTRICT'],
        ['weekly_programs', 'weekly_programs_structure_id_foreign', 'structure_id', 'structures', 'id', 'SET NULL', 'RESTRICT'],
        ['weekly_programs', 'weekly_programs_user_id_foreign', 'user_id', 'users', 'id', 'RESTRICT', 'RESTRICT'],
        ['weekly_program_items', 'weekly_program_items_dish_category_id_foreign', 'dish_category_id', 'dish_categories', 'id', 'RESTRICT', 'RESTRICT'],
        ['weekly_program_items', 'weekly_program_items_dish_id_foreign', 'dish_id', 'dishes', 'id', 'RESTRICT', 'RESTRICT'],
        ['weekly_program_items', 'weekly_program_items_weekly_program_id_foreign', 'weekly_program_id', 'weekly_programs', 'id', 'CASCADE', 'RESTRICT'],
    ];

    public function up(): void
    {
        $db = DB::getDatabaseName();

        /* ── 1. PRIMARY KEYS ── */
        foreach (self::PRIMARY_KEYS as $table => $cols) {
            if (!Schema::hasTable($table) || $this->hasIndex($db, $table, 'PRIMARY')) {
                continue;
            }
            if ($this->hasDuplicates($table, $cols)) {
                $this->warn("PK omitida en `{$table}`: hay valores duplicados en " . implode(', ', $cols));
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY ({$this->cols($cols)})");
        }

        /* ── 2. AUTO_INCREMENT (requiere PK) ── */
        foreach (self::AUTO_INCREMENT as $table => [$column, $type]) {
            if (!Schema::hasTable($table) || !$this->hasIndex($db, $table, 'PRIMARY')) {
                continue;
            }
            $isAuto = DB::selectOne(
                'SELECT EXTRA e FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$db, $table, $column]
            );
            if ($isAuto && str_contains($isAuto->e, 'auto_increment')) {
                continue;
            }
            $next = (int) DB::selectOne("SELECT COALESCE(MAX(`{$column}`), 0) + 1 n FROM `{$table}`")->n;
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = {$next}");
        }

        /* ── 3. ÍNDICES ── */
        foreach (self::INDEXES as [$table, $name, $cols, $unique]) {
            if (!Schema::hasTable($table) || $this->hasIndex($db, $table, $name)) {
                continue;
            }
            if ($unique && $this->hasDuplicates($table, $cols)) {
                $this->warn("Índice único `{$name}` omitido: hay duplicados en `{$table}`");
                continue;
            }
            $u = $unique ? 'UNIQUE ' : '';
            DB::statement("ALTER TABLE `{$table}` ADD {$u}INDEX `{$name}` ({$this->cols($cols)})");
        }

        /* ── 4. FOREIGN KEYS (se omiten las que tendrían filas huérfanas) ── */
        foreach (self::FOREIGN_KEYS as [$table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate]) {
            if (!Schema::hasTable($table) || !Schema::hasTable($refTable)) {
                continue;
            }
            $exists = DB::selectOne(
                "SELECT 1 x FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                [$db, $table, $name]
            );
            if ($exists) {
                continue;
            }
            $orphans = (int) DB::selectOne(
                "SELECT COUNT(*) c FROM `{$table}` t
                 LEFT JOIN `{$refTable}` r ON r.`{$refCol}` = t.`{$col}`
                 WHERE t.`{$col}` IS NOT NULL AND r.`{$refCol}` IS NULL"
            )->c;
            if ($orphans > 0) {
                $this->warn("FK `{$name}` omitida: {$orphans} filas de `{$table}`.`{$col}` no existen en `{$refTable}`");
                continue;
            }
            $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}` (`{$refCol}`)";
            if (!in_array($onDelete, ['RESTRICT', 'NO ACTION'], true)) {
                $sql .= " ON DELETE {$onDelete}";
            }
            if (!in_array($onUpdate, ['RESTRICT', 'NO ACTION'], true)) {
                $sql .= " ON UPDATE {$onUpdate}";
            }
            DB::statement($sql);
        }
    }

    /**
     * Reparación de una base dañada: revertirla volvería a dejarla sin claves, así que
     * no se deshace nada.
     */
    public function down(): void
    {
        //
    }

    private function hasIndex(string $db, string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 x FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, $table, $index]
        ) !== null;
    }

    private function hasDuplicates(string $table, array $cols): bool
    {
        $list = $this->cols($cols);

        return DB::selectOne(
            "SELECT COUNT(*) c FROM (SELECT 1 FROM `{$table}` GROUP BY {$list} HAVING COUNT(*) > 1) d"
        )->c > 0;
    }

    /** Convierte ['a', 'b(191)'] en '`a`,`b`(191)' */
    private function cols(array $cols): string
    {
        return implode(',', array_map(
            fn($c) => preg_match('/^(.+)\((\d+)\)$/', $c, $m) ? "`{$m[1]}`({$m[2]})" : "`{$c}`",
            $cols
        ));
    }

    private function warn(string $message): void
    {
        if (app()->runningInConsole()) {
            fwrite(STDERR, "  [repair-keys] {$message}\n");
        }
    }
};
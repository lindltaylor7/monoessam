import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    timePeriods: TimePeriod[];
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    sidebar_name?: string;
    route_name?: string;
    icon_class?: string;
    created_at: string;
    updated_at: string;
}

export interface Area {
    id: number;
    name: string;
    headquarter_id?: number;
}

export interface Mine {
    id: number;
    name: string;
}

export interface Unit {
    id: number;
    name: string;
    mine_id: number;
    cafes: Cafe[];
}

export interface Cafe {
    id: number;
    name: string;
    unit_id: number;
    unit: {
        id: number;
        name: string;
        cafes: Cafe[];
    };
    // Properties used in Headcount context
    assigned_roles?: any[];
    roles?: any[];
}

export interface Dish {
    id: number;
    name: string;
    description: string;
    dish_category_id: number;
    dish_categories?: DishCategory[];
    ingredients?: Ingredient[];
    mesearument_unit?: string;
    recipes?: DishRecipe[];
}

export interface DishRecipe {
    id: number;
    dish_id: number;
    name: string;
    total_gross_weight: number;
    total_waste_weight: number;
    total_calories: number;
    total_cost: number;
    total_net_weight: number;
}

export interface Business {
    id: number;
    name: string;
    headquarters: Headquarter[];
}

export interface Headquarter {
    id: number;
    name: string;
    business_id?: number;
    areas: Area[];
}

export interface Dealership {
    id: number;
    name: string;
    ruc?: string;
    fiscal_address?: string;
    legal_address?: string;
    phone?: string;
    email?: string;
    subdealerships?: Subdealership[];
}

export interface Subdealership {
    id: number;
    name: string;
    ruc?: string;
    fiscal_address?: string;
    legal_address?: string;
    phone?: string;
    email?: string;
    dealership_id: number;
    dealership?: Dealership;
    dinners?: Dinner[];
    units?: Unit[];
    mines?: Mine[];
    created_at: string;
    updated_at: string;
}

export interface Dosification {
    id: number;
    ingredient_id: number;
    energy?: number;
    water?: number;
    protein?: number;
    lipid?: number;
    carbohydrate?: number;
    fiber?: number;
    ash?: number;
    calcium?: number;
    phosphorus?: number;
    iron?: number;
    retinol?: number;
    thiamine?: number;
    riboflavin?: number;
    niacin?: number;
    a_asc?: number;
    sodium?: number;
    potassium?: number;
    magnesium?: number;
    zinc?: number;
    selenium?: number;
    a_folic?: number;
    v_b6?: number;
    v_e?: number;
    v_b12?: number;
    v_b9?: number;
    iodine?: number;
    cholesterol?: number;
}

export interface Ingredient {
    id: number;
    name: string;
    unit?: string;
    waste?: number;
    amount?: number;
    energy?: number;
    dosification?: Dosification;
    pivot?: {
        gross_weight?: { amount: number };
        solid_waste?: { amount: number };
        liquid_waste?: { amount: number };
        calorie?: { amount: number };
        ingredient_cost?: { base_cost: number };
        net_weight?: { amount: number };
    };
}

export interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone: string;
    subdealership_id: number;
    cafe_id: number;
}

export interface Service {
    id: number;
    code: string;
    name: string;
    description: string;
}

export interface IngredientCategory {
    id: number;
    name: string;
    description: string;
}

export interface DishCategory {
    id: number;
    name: string;
    description: string;
    mesearument_unit: string;
}

export interface Provider {
    id: number;
    name: string;
}

export interface TimePeriod {
    entryDate: string;
    exitDate: string;
    status: 'Trabajando' | 'Libre' | 'Vacaciones'; // Ejemplo de estados
    statusColor: string; // Ejemplo para el color de la insignia
}

export interface Staff {
    id: number;
    name: string;
    dni: string;
    cell: string;
    birthdate: Date;
    age: number;
    sex: number;
    email: string;
    country: string;
    civilstatus: number;
    contactname: string;
    contactcell: string;
    status: number;
    cafe_id: number;
    role_id: number;
    role?: Role;
    staffable_id: number;
    staffable_type: string;
    staffable?: any;
    staff_files?: StaffFile[];
    photo?: {
        id: number;
        url: string;
    };
    staff_clothes?: any[];
}

export interface StaffFile {
    id: number;
    file_path: string;
    file_type: string;
    expiration_data: Date;
}

export type MealType = 'Desayuno' | 'Almuerzo' | 'Cena' | 'Refrigerio';

export interface MenuStructure {
    id: number;
    meal_type: MealType;
    dish_category_id: number;
    dish_category?: DishCategory;
    sort_order: number;
    cost_percentage?: number;
}

export interface WeeklyProgram {
    id: number;
    cafe_id: number;
    cafe?: Cafe;
    start_date: string;
    end_date: string;
    status: 'borrador' | 'aprobado';
    user_id: number;
    user?: User;
    items?: WeeklyProgramItem[];
    portions?: DailyPortion[];
    purchase_order?: PurchaseOrder;
}

export interface WeeklyProgramItem {
    id: number;
    weekly_program_id: number;
    date: string;
    meal_type: MealType;
    dish_category_id: number;
    dish_category?: DishCategory;
    dish_id: number;
    dish?: Dish;
}

export interface DailyPortion {
    id: number;
    weekly_program_id: number;
    date: string;
    meal_type: MealType;
    portions_count: number;
}

export interface PurchaseOrder {
    id: number;
    weekly_program_id: number;
    program?: WeeklyProgram;
    status: 'pendiente' | 'enviada';
    notes?: string;
    items?: PurchaseOrderItem[];
    created_at: string;
}

export interface PurchaseOrderItem {
    id: number;
    purchase_order_id: number;
    ingredient_id: number;
    ingredient?: Ingredient;
    total_amount: number;
    unit: string;
    estimated_cost?: number;
}

export type BreadcrumbItemType = BreadcrumbItem;

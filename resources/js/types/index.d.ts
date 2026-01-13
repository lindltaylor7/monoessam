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
    created_at: string;
    updated_at: string;
}

export interface Area {
    id: number;
    name: string;
}

export interface Mine {
    id: number;
    name: string;
}

export interface Unit {
    id: number;
    name: string;
    mine_id: number;
}

export interface Cafe {
    id: number;
    name: string;
    unit_id: number;
    unit: {
        id: number;
        name: string;
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
}

export interface Business {
    id: number;
    name: string;
}

export interface Headquarter {
    id: number;
    name: string;
}

export interface Ingredient {
    id: number;
    name: string;
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
    staff_files?: StaffFile[];
    photo?: {
        id: number;
        url: string;
    };
}

export interface StaffFile {
    id: number;
    file_path: string;
    file_type: string;
    expiration_data: Date;
}

export type BreadcrumbItemType = BreadcrumbItem;

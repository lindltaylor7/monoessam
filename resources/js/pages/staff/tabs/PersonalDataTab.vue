<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Area, Business, Role, Staff, Unit } from '@/types';
import { Cake, Calendar, Globe, Heart, IdCard, Mail, PersonStanding, Phone, User, UserRound } from 'lucide-vue-next';
import EmergencyContactSection from '../sections/EmergencyContactSection.vue';
import PhotoUploadSection from '../sections/PhotoUploadSection.vue';

interface Props {
    form: any;
    cafes: any[];
    imagePreview: string | null;
    units: Unit[];
    roles: Role[];
    businneses: Business[];
    staff: Staff;
}

interface Emits {
    (e: 'trigger-upload'): void;
    (e: 'remove-image'): void;
    (e: 'select-cafe', cafe: any): void;
    (e: 'select-unit', unit: Unit): void;
    (e: 'select-role', role: Role): void;
    (e: 'select-area', area: Area): void;
}

defineProps<Props>();
const emit = defineEmits<Emits>();
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
            <PhotoUploadSection
                class="md:col-span-4"
                :image-preview="imagePreview"
                :cafe-id="form.cafeId"
                :unit-id="form.unitId"
                :role-id="form.roleId"
                :roles="roles"
                :cafes="cafes"
                :units="units"
                :businneses="businneses"
                @trigger-upload="emit('trigger-upload')"
                @remove-image="emit('remove-image')"
                @select-cafe="emit('select-cafe', $event)"
                @select-unit="emit('select-unit', $event)"
                @select-role="emit('select-role', $event)"
                @select-area="emit('select-area', $event)"
            />
            <div class="rounded-2xl border border-zinc-100 bg-white p-7 shadow-sm transition-all duration-300 hover:shadow-md md:col-span-4">
                <div class="space-y-5">
                    <!-- Header de sección con icono -->
                    <div class="mb-4 border-b border-zinc-100 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                <UserRound class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-zinc-900">Datos Generales</h3>
                                <p class="mt-1 text-sm text-zinc-500">Información personal del colaborador</p>
                            </div>
                        </div>
                    </div>

                    <!-- Campos del formulario -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-3">
                        <!-- Apellidos y Nombres -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <User class="h-3 w-3" />
                                </div>
                                Apellidos y Nombres *
                            </label>
                            <Input
                                id="nombres"
                                v-model="form.name"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="Ej: Pérez García, Juan"
                            />
                        </div>

                        <!-- DNI / C.E. -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <IdCard class="h-3 w-3" />
                                </div>
                                DNI *
                            </label>
                            <Input
                                id="doc"
                                v-model="form.dni"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="Ej: 87654321"
                            />
                        </div>

                        <!-- Celular -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Phone class="h-3 w-3" />
                                </div>
                                Celular *
                            </label>
                            <Input
                                id="cel"
                                v-model="form.cell"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="Ej: 987654321"
                            />
                        </div>

                        <!-- Fecha de Nacimiento -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Cake class="h-3 w-3" />
                                </div>
                                F. Nacimiento
                            </label>
                            <Input
                                type="date"
                                v-model="form.birthdate"
                                class="h-10 border-zinc-200 bg-white text-zinc-700 focus:border-indigo-300 focus:ring-indigo-300"
                            />
                        </div>

                        <!-- Edad -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Calendar class="h-3 w-3" />
                                </div>
                                Edad
                            </label>
                            <Input
                                id="age"
                                v-model="form.age"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="Ej: 30"
                            />
                        </div>
                        <!-- Sexo -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <PersonStanding class="h-3 w-3" />
                                </div>
                                Sexo
                            </label>
                            <Select v-model="form.sex">
                                <SelectTrigger class="h-10 border-zinc-200 bg-white hover:bg-zinc-50">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                    <SelectItem value="1" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <Male class="h-3.5 w-3.5 text-blue-500" />
                                            <span>Masculino</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="2" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <Female class="h-3.5 w-3.5 text-pink-500" />
                                            <span>Femenino</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Email -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Mail class="h-3 w-3" />
                                </div>
                                Email
                            </label>
                            <Input
                                v-model="form.email"
                                type="email"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="ejemplo@correo.com"
                            />
                        </div>

                        <!-- Nacionalidad -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Globe class="h-3 w-3" />
                                </div>
                                Nacionalidad
                            </label>
                            <Input
                                v-model="form.country"
                                class="h-10 border-zinc-200 bg-white focus:border-indigo-300 focus:ring-indigo-300"
                                placeholder="Ej: Peruana"
                            />
                        </div>

                        <!-- Estado Civil -->
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
                                <div class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-100 text-indigo-600">
                                    <Heart class="h-3 w-3" />
                                </div>
                                Estado Civil
                            </label>
                            <Select v-model="form.civilstatus">
                                <SelectTrigger class="h-10 border-zinc-200 bg-white hover:bg-zinc-50">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent class="border-zinc-200 bg-white shadow-lg">
                                    <SelectItem value="1" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <User class="h-3.5 w-3.5 text-zinc-500" />
                                            <span>Soltero</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="2" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <Users class="h-3.5 w-3.5 text-blue-500" />
                                            <span>Casado</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="3" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <UserMinus class="h-3.5 w-3.5 text-amber-500" />
                                            <span>Viudo</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="4" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <UserX class="h-3.5 w-3.5 text-red-500" />
                                            <span>Divorciado</span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="5" class="hover:bg-zinc-50">
                                        <div class="flex items-center gap-2">
                                            <UserCheck class="h-3.5 w-3.5 text-emerald-500" />
                                            <span>Conviviente</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Nota informativa -->
                    <div class="mt-4 rounded-lg bg-zinc-50 p-3">
                        <div class="flex items-start gap-2">
                            <Info class="mt-0.5 h-4 w-4 text-zinc-400" />
                            <p class="text-xs text-zinc-500">
                                <span class="font-medium text-zinc-600">* Campos obligatorios</span>
                                - Los campos marcados con asterisco son requeridos para el registro.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <EmergencyContactSection :form="form" />
    </div>
</template>

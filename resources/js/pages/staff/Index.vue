<script setup lang="ts">
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import Input from '@/components/ui/input/Input.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStaffActions } from '@/composables/useStaffActions';
import { useStaffFilter } from '@/composables/useStaffFilter';
import AppLayout from '@/layouts/AppLayout.vue';
import { Business, Cafe, Role, Staff, Unit } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Building2, ChevronLeft, ChevronRight, Coffee, Filter, MapPin, Search, UserCheck, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import StaffRegistrationDialog from './StaffRegistrationDialog.vue';
import StaffMobileCard from './partials/StaffMobileCard.vue';
import StaffTable from './partials/StaffTable.vue';

interface Props {
    cafes: Cafe[];
    staff: Staff[];
    roles: Role[];
    units: Unit[];
    businneses: Business[];
    roleClothes: Record<number, Record<string, Array<{ id: number; name: string }>>>;
}

const props = defineProps<Props>();

// Gestión local del estado para permitir actualizaciones sin recarga completa
const localStaff = ref(props.staff);

watch(
    () => props.staff,
    (newVal) => {
        localStaff.value = newVal;
    },
);

// Composables
const { filteredStaff, searchQuery, selectedUnitId } = useStaffFilter(localStaff);

const { deleteStaff } = useStaffActions((id) => {
    localStaff.value = localStaff.value.filter((s) => s.id !== id);
});

// Paginación
const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / itemsPerPage));

const paginatedStaff = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredStaff.value.slice(start, end);
});

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

// Reset page when filter changes
watch(filteredStaff, () => {
    currentPage.value = 1;
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedUnitId.value = '0';
};
</script>

<template>
    <Head title="Personal" />
    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header Unificado -->
            <PageHeader
                title="Personal Operativo"
                subtitle="Gestión de colaboradores, asignación a unidades de catering y control de dotación"
                badge="Recursos Humanos"
            >
                <template #actions>
                    <StaffRegistrationDialog
                        :cafes="props.cafes"
                        :roles="props.roles"
                        :units="props.units"
                        :businneses="props.businneses"
                        :role-clothes="props.roleClothes"
                    />
                </template>
            </PageHeader>

            <!-- Métricas Clave -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Total Personal"
                    :value="staff.length"
                    subtitle="Colaboradores registrados"
                    color="primary"
                >
                    <template #icon>
                        <Users class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Personal Filtrado"
                    :value="filteredStaff.length"
                    subtitle="Registros visibles"
                    color="info"
                >
                    <template #icon>
                        <UserCheck class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Unidades Operativas"
                    :value="units.length"
                    subtitle="Sedes mineras y campamentos"
                    color="success"
                >
                    <template #icon>
                        <Building2 class="h-5 w-5" />
                    </template>
                </StatCard>
                <StatCard
                    title="Puntos de Atención"
                    :value="cafes.length"
                    subtitle="Comedores y cafeterías"
                    color="purple"
                >
                    <template #icon>
                        <Coffee class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Barra de Filtros -->
            <Card class="border-border/60 shadow-xs">
                <CardContent class="p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-1 flex-col sm:flex-row gap-3">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Buscar personal por DNI o nombre..."
                                    v-model="searchQuery"
                                    class="pl-9 pr-8 h-9"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <div class="w-full sm:w-64">
                                <Select v-model="selectedUnitId">
                                    <SelectTrigger class="h-9">
                                        <div class="flex items-center gap-2 truncate">
                                            <MapPin class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                            <SelectValue placeholder="Todas las unidades" />
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">Todas las unidades</SelectItem>
                                        <SelectItem :value="String(unit.id)" v-for="unit in units" :key="unit.id">
                                            {{ unit.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                v-if="searchQuery || (selectedUnitId && selectedUnitId !== '0')"
                                variant="ghost"
                                size="sm"
                                class="h-9 text-xs gap-1.5"
                                @click="resetFilters"
                            >
                                <X class="h-3.5 w-3.5" />
                                <span>Limpiar</span>
                            </Button>
                        </div>

                        <p class="text-xs text-muted-foreground shrink-0">
                            {{ filteredStaff.length }} colaboradores encontrados
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Contenedor de Tabla y Tarjetas -->
            <Card class="overflow-hidden border-border/60 shadow-xs">
                <!-- Vista Desktop -->
                <StaffTable
                    :staff-list="paginatedStaff"
                    :cafes="props.cafes"
                    :roles="props.roles"
                    :units="props.units"
                    :businesses="props.businneses"
                    :role-clothes="props.roleClothes"
                    @delete-staff="deleteStaff"
                />

                <!-- Vista Mobile: Cards -->
                <div class="space-y-4 p-4 md:hidden">
                    <StaffMobileCard
                        v-for="staff in paginatedStaff"
                        :key="staff.id"
                        :staff="staff"
                        :cafes="props.cafes"
                        :roles="props.roles"
                        :units="props.units"
                        :businesses="props.businneses"
                        :role-clothes="props.roleClothes"
                        @delete-staff="deleteStaff"
                    />

                    <!-- Mensaje cuando no hay datos -->
                    <div v-if="!paginatedStaff || paginatedStaff.length === 0" class="py-12 text-center">
                        <p class="text-muted-foreground text-sm">No hay personal registrado</p>
                    </div>
                </div>

                <!-- Footer de Paginación -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-border/60 bg-muted/10 p-4" v-if="totalPages > 1">
                    <div class="text-muted-foreground text-xs order-2 sm:order-1">
                        Mostrando <span class="font-medium text-foreground">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> a
                        <span class="font-medium text-foreground">{{ Math.min(currentPage * itemsPerPage, filteredStaff.length) }}</span> de
                        <span class="font-medium text-foreground">{{ filteredStaff.length }}</span> colaboradores
                    </div>
                    <div class="flex items-center gap-2 order-1 sm:order-2">
                        <Button variant="outline" size="sm" class="h-8 gap-1 text-xs" :disabled="currentPage === 1" @click="prevPage">
                            <ChevronLeft class="h-3.5 w-3.5" />
                            <span>Anterior</span>
                        </Button>
                        <div class="text-xs font-semibold px-2">
                            Página {{ currentPage }} de {{ totalPages }}
                        </div>
                        <Button variant="outline" size="sm" class="h-8 gap-1 text-xs" :disabled="currentPage === totalPages" @click="nextPage">
                            <span>Siguiente</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>

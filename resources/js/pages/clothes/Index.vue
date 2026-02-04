<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Eye, ChevronLeft, ChevronRight, Package, Plus } from 'lucide-vue-next';
import { useStaffFilter } from '@/composables/useStaffFilter';
import { Staff, Unit } from '@/types';
import StaffClothesDialog from '@/pages/clothes/partials/StaffClothesDialog.vue';

interface ExtendedStaff extends Staff {
    staff_clothes: Array<{
        id: number;
        cloth_id: number;
        color_id: number | null;
        clothing_size: string;
        clothe_name?: string;
        cloth?: { name: string };
        color?: { id: number; name: string };
        status?: string;
    }>;
}

const props = defineProps<{
    staff: ExtendedStaff[]; 
    roleClothes: Record<number, Record<string, Array<{ id: number; name: string }>>>;
    units: any[];
    colors: Array<{ id: number, name: string }>;
}>();

const getClothesForStaff = (person: ExtendedStaff) => {
    if (!person.role_id) return [];
    const roleMap = props.roleClothes[person.role_id];
    if (!roleMap) return [];
    
    const cafeId = person.cafe_id;
    const clothes = roleMap[String(cafeId)] || roleMap['all'] || [];
    return clothes;
};

const localStaff = ref(props.staff as any[]);
const selectedStaff = ref<ExtendedStaff | null>(null);

watch(
    () => props.staff,
    (newVal) => {
        localStaff.value = newVal;
        if (selectedStaff.value) {
            const updated = newVal.find(s => s.id === selectedStaff.value?.id);
            if (updated) {
                selectedStaff.value = updated;
            }
        }
    }
);

const { filteredStaff, searchQuery, selectedUnitId } = useStaffFilter(localStaff as any);

const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / itemsPerPage));

const paginatedStaff = computed(() => {
    if (currentPage.value > totalPages.value && totalPages.value > 0) currentPage.value = 1;
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

watch(filteredStaff, () => {
    currentPage.value = 1;
});

const isModalOpen = ref(false);

const openModal = (staff: ExtendedStaff) => {
    selectedStaff.value = staff;
    isModalOpen.value = true;
};

const updateSize = (staffId: number, clothId: number, size: string) => {
    router.post(route('clothes.staff-size'), {
        staff_id: staffId,
        cloth_id: clothId,
        clothing_size: size
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['staff']
    });
};

const getClothSize = (staff: ExtendedStaff, clothId: number) => {
    const entry = staff.staff_clothes.find((sc) => sc.cloth_id === clothId);
    return entry ? entry.clothing_size : '';
};

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((word: string) => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <Head title="Registro de Tallas" />

    <AppLayout :breadcrumbs="[
        { title: 'Personal', href: route('staff.index') },
        { title: 'Tallas', href: route('clothes.index') }
    ]">
        <div class="p-6">
             <div class="flex justify-between items-center mb-6">
                 <div>
                    <h1 class="text-3xl font-bold tracking-tight">Asignación de Prendas por Rol y Café</h1>
                    <p class="text-muted-foreground text-xs sm:text-sm mt-1">
                        Selecciona las prendas asignadas a cada rol/cargo en el café seleccionado
                    </p>
                 </div>
                 <div class="flex flex-wrap gap-2">
                    <Link :href="route('inventory.index')">
                        <Button variant="outline" class="gap-2">
                            <Package class="h-4 w-4" />
                            Inventario
                        </Button>
                    </Link>
                    <Link :href="route('clothes.manage')">
                        <Button class="gap-2">
                            <Plus class="h-4 w-4" />
                            Configurar Prendas
                        </Button>
                    </Link>
                 </div>
            </div>

            <div class="flex items-center mb-6">
                <Input 
                    type="text" 
                    placeholder="Buscar personal por dni o nombre" 
                    v-model="searchQuery"
                />
                <Select v-model="selectedUnitId">
                    <SelectTrigger class="h-10 border-zinc-200 bg-white hover:bg-zinc-50 ml-2 w-[200px]">
                        <SelectValue placeholder="Seleccionar unidad" />
                    </SelectTrigger>
                    <SelectContent class="border-zinc-200 bg-white shadow-lg">
                        <SelectItem value="0" class="hover:bg-zinc-50"> Ninguna </SelectItem>
                        <SelectItem :value="String(unit.id)" class="hover:bg-zinc-50" v-for="unit in units" :key="unit.id">
                           {{ unit.mine.name }} - {{ unit.name }} 
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 border-b">
                            <tr>
                                <th class="p-4 font-medium">Personal</th>
                                <th class="p-4 font-medium">Cargo</th>
                                <th class="p-4 font-medium">Unidad</th>
                               <!-- <th class="p-4 font-medium">Tallas</th> -->
                                <th class="p-4 font-medium w-16">Prendas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                             <tr v-for="person in paginatedStaff" :key="person.id" class="hover:bg-gray-50/50">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-9 w-9 border">
                                            <AvatarImage 
                                                v-if="person.photo?.url" 
                                                :src="`/storage/${person.photo?.url}`" 
                                                class="object-cover"
                                            />
                                            <AvatarFallback>{{ getInitials(person.name) }}</AvatarFallback>
                                        </Avatar>
                                        <div class="font-medium text-gray-900">{{ person.name }}</div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10" v-if="person.role">
                                        {{ person.role.name }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin cargo</span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10" v-if="person.staffable?.unit">
                                        {{ person.staffable?.unit?.name }} - {{ person.staffable?.unit?.mine?.name }}
                                    </span>
                                    <span v-else class="text-gray-400 italic">Sin unidad</span>
                                </td>
                                <!--
                                <td class="p-4">
                                    <div v-if="person.role_id && roleClothes[person.role_id] && roleClothes[person.role_id].length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <div v-for="cloth in roleClothes[person.role_id]" :key="cloth.id" class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-gray-500">{{ cloth.name }}</label>
                                            <input 
                                                type="text" 
                                                class="h-8 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                                :value="getClothSize(person, cloth.id)"
                                                placeholder="Talla..."
                                                @change="(e) => updateSize(person.id, cloth.id, (e.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic">
                                        No hay prendas configuradas para este cargo.
                                    </div>
                                </td>
                                -->
                                <td class="p-4 text-right">
                                    <Button variant="ghost" size="icon" @click="openModal(person as any)">
                                        <Eye class="h-4 w-4 text-gray-500" />
                                    </Button>
                                </td>
                             </tr>
                             <tr v-if="paginatedStaff.length === 0">
                                 <td colspan="5" class="p-8 text-center text-gray-500">
                                     No se encontró personal.
                                 </td>
                             </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t p-4" v-if="totalPages > 1">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredStaff.length) }} de {{ filteredStaff.length }} registros
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="currentPage === 1" 
                            @click="prevPage"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Anterior
                        </Button>
                        <div class="text-sm font-medium">
                            Página {{ currentPage }} de {{ totalPages }}
                        </div>
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="currentPage === totalPages" 
                            @click="nextPage"
                        >
                            Siguiente
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <StaffClothesDialog 
            :open="isModalOpen" 
            :staff="selectedStaff" 
            :colors="colors"
            @update:open="isModalOpen = $event" 
        />
    </AppLayout>
</template>

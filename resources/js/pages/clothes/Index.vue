<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Avatar, AvatarImage, AvatarFallback } from '@/Components/ui/avatar';
import Pagination from '@/Components/ui/pagination/Pagination.vue'; 
import { Eye } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';

// Note: Assuming Pagination component exists or using a simple one. 
// If generic table is preferred, I'll build a custom one.

const props = defineProps<{
    staff: {
        data: Array<{
            id: number;
            name: string;
            role_id: number;
            role?: { name: string };
            photo?: { date_path_photo: string };
            staff_clothes: Array<{
                id: number;
                cloth_id: number;
                clothing_size: string;
                clothe_name?: string;
                cloth?: { name: string };
            }>;
        }>;
        links: any[];
    };
    roleClothes: Record<number, Array<{ id: number; name: string }>>;
}>();

const isModalOpen = ref(false);
const selectedStaff = ref<any>(null);

const openModal = (staff: any) => {
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
        only: ['staff'] // Optimistic update or just partial reload usually enough
    });
};

const getClothSize = (staff: any, clothId: number) => {
    const entry = staff.staff_clothes.find((sc: any) => sc.cloth_id === clothId);
    return entry ? entry.clothing_size : '';
};

// Helper to get initials
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
        <div class="p-6 space-y-8 max-w-7xl mx-auto">
             <div class="flex justify-between items-center">
                 <div>
                    <h1 class="text-3xl font-bold tracking-tight">Registro de Tallas</h1>
                    <p class="text-muted-foreground mt-1">
                        Gestiona las tallas del personal según las prendas asignadas a su cargo.
                    </p>
                 </div>
                 <Link :href="route('clothes.manage')">
                    <Button>Configurar Prendas y Roles</Button>
                 </Link>
            </div>

            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 border-b">
                            <tr>
                                <th class="p-4 font-medium">Personal</th>
                                <th class="p-4 font-medium">Cargo</th>
                                <th class="p-4 font-medium">Tallas</th>
                                <th class="p-4 font-medium w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                             <tr v-for="person in staff.data" :key="person.id" class="hover:bg-gray-50/50">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-9 w-9 border">
                                            <AvatarImage 
                                                v-if="person.photo" 
                                                :src="`/storage/${person.photo.date_path_photo}`" 
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
                                <td class="p-4 text-right">
                                    <Button variant="ghost" size="icon" @click="openModal(person)">
                                        <Eye class="h-4 w-4 text-gray-500" />
                                    </Button>
                                </td>
                             </tr>
                             <tr v-if="staff.data.length === 0">
                                 <td colspan="4" class="p-8 text-center text-gray-500">
                                     No se encontró personal.
                                 </td>
                             </tr>
                        </tbody>
                    </table>
                </div>
                 <!-- Simple Pagination if needed -->
                 <div v-if="staff.links && staff.links.length > 3" class="p-4 border-t border-gray-100 flex justify-center">
                    <div class="flex gap-1">
                        <Link 
                            v-for="(link, k) in staff.links" 
                            :key="k" 
                            :href="link.url || '#'" 
                            v-html="link.label"
                            class="px-3 py-1 rounded text-sm transition-colors"
                            :class="{ 
                                'bg-black text-white': link.active, 
                                'text-gray-500 hover:bg-gray-100': !link.active,
                                'opacity-50 pointer-events-none': !link.url
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Tallas Asignadas</DialogTitle>
                    <DialogDescription>
                        Resumen de tallas para {{ selectedStaff?.name }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4" v-if="selectedStaff">
                    <div v-if="selectedStaff.staff_clothes && selectedStaff.staff_clothes.length > 0" class="border rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Prenda</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-500">Talla</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in selectedStaff.staff_clothes" :key="item.id">
                                    <td class="px-4 py-2">
                                        {{ item.cloth ? item.cloth.name : (item.clothe_name || 'Prenda Desconocida') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium">
                                        {{ item.clothing_size || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-6 text-gray-500 bg-gray-50 rounded-lg border border-dashed">
                        No hay tallas registradas.
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="isModalOpen = false">
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

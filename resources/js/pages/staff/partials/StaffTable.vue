<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Staff, Cafe, Role, Unit, Business } from '@/types';
import { Trash2, FileUp, CheckSquare, Square } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import ChangeStatusModal from '../ChangeStatusModal.vue';
import FilesModal from '../FilesModal.vue';
import StaffRegistrationDialog from '../StaffRegistrationDialog.vue';
import MassSCTRDialog from './MassSCTRDialog.vue';
import { getStatusColor, getStatusLabel } from '@/composables/useStaffConstants';

interface Props {
    staffList: Staff[];
    cafes: Cafe[];
    roles: Role[];
    units: Unit[];
    businesses: Business[];
    roleClothes: Record<number, Record<string, Array<{ id: number; name: string }>>>;
}

const props = defineProps<Props>();
const emit = defineEmits(['delete-staff', 'change-status']);

// Selección masiva
const selectedStaffIds = ref<number[]>([]);

const isAllSelected = computed({
    get: () => props.staffList.length > 0 && selectedStaffIds.value.length === props.staffList.length,
    set: (val) => {
        if (val) {
            selectedStaffIds.value = props.staffList.map(s => s.id);
        } else {
            selectedStaffIds.value = [];
        }
    }
});

const toggleSelectStaff = (id: number, checked: boolean) => {
    const ids = [...selectedStaffIds.value];
    const index = ids.indexOf(id);
    if (checked && index === -1) {
        ids.push(id);
    } else if (!checked && index > -1) {
        ids.splice(index, 1);
    }
    selectedStaffIds.value = ids;
};

const onMassActionComplete = () => {
    selectedStaffIds.value = [];
};

// Aunque ChangeStatusModal maneja la lógica interna, el padre podría querer saber
const onChangeStatus = () => {
    emit('change-status');
};
</script>

<template>
    <div class="space-y-4">
        <!-- Acciones Masivas -->
        <div v-if="selectedStaffIds.length > 0" class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-lg mb-4">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-blue-700">
                    {{ selectedStaffIds.length }} seleccionados
                </span>
            </div>
            <div class="flex items-center gap-2">
                <MassSCTRDialog :selected-ids="selectedStaffIds" @complete="onMassActionComplete" />
                <Button variant="outline" size="sm" @click="selectedStaffIds = []" class="text-gray-500">
                    Cancelar
                </Button>
            </div>
        </div>

        <div class="bg-card hidden overflow-x-auto rounded-xl border shadow-sm md:block">
            <table class="w-full table-auto border-collapse">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="p-4 text-left w-10">
                            <Checkbox v-model:checked="isAllSelected" />
                        </th>
                        <th class="p-4 text-left text-sm font-semibold">Nombre</th>
                        <th class="p-4 text-left text-sm font-semibold">DNI</th>
                    <th class="p-4 text-left text-sm font-semibold">Celular</th>
                    <th class="p-4 text-left text-sm font-semibold">Comedor/Area</th>
                    <th class="p-4 text-left text-sm font-semibold">Documentación</th>
                    <th class="p-4 text-left text-sm font-semibold">Estado</th>
                    <th class="p-4 text-center text-sm font-semibold">Opciones</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="staff in staffList" :key="staff.id" :class="['hover:bg-muted/30 border-t transition', selectedStaffIds.includes(staff.id) ? 'bg-blue-50/30' : '']">
                    <td class="p-4">
                        <Checkbox :checked="selectedStaffIds.includes(staff.id)" @update:model-value="(val: any) => toggleSelectStaff(staff.id, !!val)" />
                    </td>
                    <td class="p-4">{{ staff.name }}</td>
                    <td class="p-4">{{ staff.dni }}</td>
                    <td class="p-4">{{ staff.cell }}</td>
                    <td class="p-4">
                        <p>
                            {{ staff.staffable?.name || 'Sin asignar' }}
                            <span v-if="staff.staffable?.unit" class="text-gray-500"> ({{ staff.staffable.unit.name }}) </span>
                        </p>
                    </td>
                    <td class="p-4">
                        <TooltipProvider v-for="file in staff.staff_files" :key="file.id">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <a class="text-md rounded-sm text-white" :href="'/storage/' + file.file_path" target="_blank"> 📄 </a>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ file.file_type }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </td>

                    <td class="p-4">
                        <StatusBadge 
                            :label="getStatusLabel(staff.status)" 
                            :color-class="getStatusColor(staff.status)"
                            @click="onChangeStatus" 
                        />
                    </td>

                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-3">
                            <ChangeStatusModal :staff="staff" />

                            <Button
                                variant="ghost"
                                size="icon"
                                class="cursor-pointer text-red-600 hover:text-red-800"
                                @click="$emit('delete-staff', staff.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>

                            <StaffRegistrationDialog
                                :cafes="props.cafes"
                                :roles="props.roles"
                                :units="props.units"
                                :businneses="props.businesses"
                                :role-clothes="props.roleClothes"
                                :staff="staff"
                            />

                            <FilesModal :staff="staff" />
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</template>

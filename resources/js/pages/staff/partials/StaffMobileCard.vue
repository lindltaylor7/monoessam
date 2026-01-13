<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Staff, Cafe, Role, Unit, Business } from '@/types';
import { Trash2 } from 'lucide-vue-next';
import ChangeStatusModal from '../ChangeStatusModal.vue';
import StaffRegistrationDialog from '../StaffRegistrationDialog.vue';
import { getStatusColor, getStatusLabel } from '@/composables/useStaffConstants';

interface Props {
    staff: Staff;
    cafes: Cafe[];
    roles: Role[];
    units: Unit[];
    businesses: Business[];
}

const props = defineProps<Props>();
const emit = defineEmits(['delete-staff', 'change-status']);

const onChangeStatus = () => {
    emit('change-status');
};
</script>

<template>
    <div class="rounded-lg border bg-white p-4 shadow-sm transition hover:shadow-md">
        <!-- Header con nombre y estado -->
        <div class="mb-3 flex items-start justify-between border-b pb-3">
            <div class="flex-1">
                <h3 class="mb-1 text-base font-semibold">{{ staff.name }}</h3>
                <StatusBadge 
                    :label="getStatusLabel(staff.status)" 
                    :color-class="getStatusColor(staff.status)"
                    class="text-xs"
                    @click="onChangeStatus"
                />
            </div>
        </div>

        <!-- Información en grid -->
        <div class="mb-4 space-y-2">
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">DNI:</span>
                <span class="text-sm">{{ staff.dni }}</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Celular:</span>
                <span class="text-sm">{{ staff.cell }}</span>
            </div>

            <div class="flex items-start gap-2">
                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Comedor:</span>
                <span class="flex-1 text-sm"> {{ staff.cafe?.name ?? 'Sin asignar' }} - {{ staff.cafe?.unit?.name }} </span>
            </div>

            <div class="flex items-start gap-2">
                <span class="text-muted-foreground min-w-[80px] text-xs font-medium">Documentos:</span>
                <div class="flex flex-wrap gap-2">
                    <TooltipProvider v-for="file in staff.staff_files" :key="file.id">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <a
                                    class="inline-block rounded-sm text-lg transition hover:scale-110"
                                    :href="'/storage/' + file.file_path"
                                    target="_blank"
                                >
                                    📄
                                </a>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ file.file_type }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <span v-if="!staff.staff_files || staff.staff_files.length === 0" class="text-muted-foreground text-sm">
                        Sin documentos
                    </span>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex items-center justify-end gap-2 border-t pt-3">
            <ChangeStatusModal :staff="staff" />

            <Button variant="ghost" size="icon" class="cursor-pointer text-red-600 hover:text-red-800" @click="$emit('delete-staff', staff.id)">
                <Trash2 class="h-4 w-4" />
            </Button>

            <StaffRegistrationDialog
                :cafes="props.cafes"
                :roles="props.roles"
                :units="props.units"
                :businneses="props.businesses"
                :staff="staff"
            />
        </div>
    </div>
</template>

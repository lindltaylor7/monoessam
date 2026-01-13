<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import { ref } from 'vue';

interface StatusHistory {
    id: number;
    status: number;
    observation: string;
    created_at: string;
    user_name?: string;
}

interface Staff {
    id: number;
    name: string;
    status: number;
    status_history?: StatusHistory[];
}

const props = defineProps({
    staff: {
        type: Object as () => Staff,
        required: true,
    },
});

const page = usePage();

const emit = defineEmits(['save', 'update:open']);

const open = ref(false);

const statusesStaff = {
    0: 'Lista negra',
    1: 'En proceso',
    2: 'Completo - RRHH',
    3: 'Contratado',
    4: 'Cesado',
    5: 'Retirado',
    6: 'Abandono',
    7: 'Cumplió Contrato',
};

const form = useForm({
    staff_id: props.staff.id,
    status: props.staff.status,
    observation: '',
    user_id: page.props.auth.user?.id,
});

const saveStatusChange = () => {
    form.post(route('staff.update-status'), {
        onSuccess: () => {
            emit('save');
            form.reset();
            form.status = props.staff.status;
            open.value = false;
        },
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status: number) => {
    const colors: Record<number, string> = {
        0: 'bg-red-500',
        1: 'bg-yellow-500',
        2: 'En proceso',
        3: 'bg-green-500',
        4: 'bg-gray-500',
        5: 'bg-blue-500',
        6: 'bg-orange-500',
        7: 'bg-purple-500',
    };
    return colors[status] || 'bg-gray-500';
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="cursor-pointer text-green-600 hover:text-green-800">
                <Info class="h-4 w-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-[800px]">
            <DialogHeader>
                <DialogTitle>Cambiar Estado del Personal</DialogTitle>
            </DialogHeader>

            <div class="flex-shrink-0 space-y-4 py-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Personal</label>
                    <p class="text-sm text-gray-600">{{ staff.name }}</p>
                </div>

                <div class="space-y-2">
                    <label for="status" class="text-sm font-medium">Nuevo Estado</label>
                    <select
                        id="status"
                        v-model="form.status"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    >
                        <option v-for="(label, value) in statusesStaff" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                    <p v-if="form.errors.status" class="text-sm text-red-600">
                        {{ form.errors.status }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label for="observation" class="text-sm font-medium">Observación</label>
                    <textarea
                        id="observation"
                        v-model="form.observation"
                        rows="3"
                        placeholder="Ingrese una observación sobre el cambio de estado..."
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    ></textarea>
                    <p v-if="form.errors.observation" class="text-sm text-red-600">
                        {{ form.errors.observation }}
                    </p>
                </div>
            </div>

            <!-- Historial de Estados -->
            <div class="min-h-0 flex-1 border-t pt-4">
                <h3 class="mb-3 text-sm font-medium">Historial de Observaciones</h3>
                <div class="max-h-[300px] overflow-y-auto pr-2">
                    <div v-if="staff.observations && staff.observations.length > 0" class="relative">
                        <!-- Línea vertical -->
                        <div class="absolute top-0 bottom-0 left-[15px] w-0.5 bg-gray-200"></div>

                        <!-- Items del timeline -->
                        <div v-for="(history, index) in staff.observations" :key="history.id" class="relative pb-6 pl-10 last:pb-0">
                            <!-- Punto del timeline -->
                            <div
                                :class="[
                                    'absolute top-1 left-0 flex h-8 w-8 items-center justify-center rounded-full border-4 border-white',
                                    getStatusColor(history.status),
                                ]"
                            >
                                <span class="text-xs font-bold text-white">{{ index + 1 }}</span>
                            </div>

                            <!-- Contenido -->
                            <div class="rounded-lg bg-gray-50 p-3 shadow-sm">
                                <div class="mb-1 flex items-start justify-between">
                                    <span class="text-sm font-medium">
                                        {{ history.user.name }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ formatDate(history.created_at) }}
                                    </span>
                                </div>
                                <p v-if="history.observation" class="mt-1 text-sm text-gray-700">
                                    {{ history.observation }}
                                </p>
                                <p v-if="history.user_name" class="mt-2 text-xs text-gray-500">Por: {{ history.user_name }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-sm text-gray-500">No hay historial de cambios registrado</div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancelar</Button>
                <Button type="submit" @click="saveStatusChange" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

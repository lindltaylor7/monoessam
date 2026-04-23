<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Button from '@/components/ui/button/Button.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, CalendarIcon, Eye, FileSignature, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffFile {
    id: number;
    name: string;
    path: string;
    file_type: string;
    file_path: string;
    expiration_date: string | null;
    created_at: string;
    status: string;
}

interface Staff {
    id: number;
    name: string;
    staff_files?: StaffFile[];
}

const props = defineProps({
    staff: {
        type: Object as () => Staff,
        required: true,
    },
});

const open = ref(false);
const showAlert = ref(false);
const alertMessage = ref('');
const uploading = ref(false);
const deletingFileId = ref<number | null>(null);
const expirationDate = ref<string>('');

const contractFileType = 'Contratos Laborales';

const contracts = computed(() => {
    return props.staff.staff_files?.filter((f) => f.file_type === contractFileType) || [];
});

const handleFileUpload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];

    // Validar tamaño (10MB máx)
    if (file.size > 10 * 1024 * 1024) {
        showAlert.value = true;
        alertMessage.value = `El archivo excede el tamaño máximo de 10MB`;
        input.value = '';
        return;
    }

    if (!expirationDate.value) {
        alert('Es necesario colocar la fecha de expiración para el contrato antes de subirlo.');
        input.value = '';
        return;
    }

    uploading.value = true;

    const uploadForm = useForm({
        file: file,
        fileTypeKey: contractFileType,
        expirationDate: expirationDate.value,
        fileId: 0, // 0 for always creating new
        staffId: props.staff.id,
    });

    uploadForm.post(route('staff.upload-file'), {
        forceFormData: true,
        preserveState: true,
        onSuccess: () => {
            uploading.value = false;
            expirationDate.value = ''; // Reset date
            showAlert.value = false;
        },
        onFinish: () => {
            uploading.value = false;
        },
        onError: () => {
            showAlert.value = true;
            alertMessage.value = 'Error al subir el archivo.';
            uploading.value = false;
        },
    });

    input.value = '';
};

const deleteFile = (fileId: number) => {
    if (!confirm('¿Está seguro de eliminar este contrato?')) return;

    deletingFileId.value = fileId;
    const form = useForm({});
    form.delete(route('staff.delete-file', fileId), {
        onSuccess: () => {
            deletingFileId.value = null;
        },
        onFinish: () => {
            deletingFileId.value = null;
        },
        onError: () => {
            showAlert.value = true;
            alertMessage.value = 'Error al eliminar el archivo.';
            deletingFileId.value = null;
        },
    });
};

const updateFileStatus = (fileId: number, status: string) => {
    const form = useForm({
        fileId: fileId,
        status: status,
    });

    form.post(route('staff.update-file-status'), {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            showAlert.value = true;
            alertMessage.value = 'Error al actualizar el estado del contrato.';
        },
    });
};

const viewFile = (file: StaffFile) => {
    window.open('/storage/' + file.file_path, '_blank');
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatExpirationDate = (date: string | null) => {
    if (!date) return 'Sin fecha';
    return new Date(date).toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const isExpired = (expirationDate: string | null) => {
    if (!expirationDate) return false;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return new Date(expirationDate) < now;
};

const isNearExpiry = (expirationDate: string | null) => {
    if (!expirationDate) return false;
    const expiry = new Date(expirationDate);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const diffTime = expiry.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays > 0 && diffDays <= 30;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9 cursor-pointer rounded-full text-indigo-600 transition-all hover:bg-indigo-50 hover:text-indigo-800" title="Contratos">
                <FileSignature class="h-4 w-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="flex h-[85vh] w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[700px]">
            <DialogHeader class="shrink-0 border-b bg-white px-6 py-4 shadow-sm">
                <DialogTitle class="text-xl font-bold text-zinc-800">Contratos Laborales</DialogTitle>
                <p class="text-sm text-zinc-500">Gestión de contratos para {{ staff.name }}</p>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto bg-zinc-50/50">
                <div class="space-y-6 px-6 py-6">
                    
                    <Alert v-if="showAlert" variant="destructive" class="border-red-200 bg-red-50 text-red-900">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle class="font-bold">Error en la operación</AlertTitle>
                        <AlertDescription>{{ alertMessage }}</AlertDescription>
                        <button class="absolute top-4 right-4 text-red-400 hover:text-red-600" @click="showAlert = false">
                            <X class="h-4 w-4" />
                        </button>
                    </Alert>

                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between">
                            <div class="flex gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <FileSignature class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <h4 class="font-bold text-zinc-900 leading-tight">Añadir Nuevo Contrato</h4>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter">PDF Máx 10MB</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Fecha de expiración (Obligatorio)</label>
                                <input
                                    type="date"
                                    v-model="expirationDate"
                                    class="w-full rounded-lg border-2 border-zinc-100 bg-zinc-50 px-3 py-2 text-sm font-medium transition-all focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none"
                                />
                            </div>

                            <label
                                :class="[
                                    'relative flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-3 text-sm font-bold transition-all duration-300',
                                    uploading
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
                                        : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600',
                                ]"
                            >
                                <template v-if="uploading">
                                    <svg class="h-5 w-5 animate-spin text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Subiendo contrato...</span>
                                </template>
                                <template v-else>
                                    <Upload class="h-4 w-4" />
                                    <span>Seleccionar PDF y Subir</span>
                                </template>
                                <input
                                    type="file"
                                    accept=".pdf"
                                    class="hidden"
                                    :disabled="uploading"
                                    @change="handleFileUpload"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="font-bold text-zinc-800 flex items-center justify-between">
                            Historial de Contratos
                            <span class="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">{{ contracts.length }} registrados</span>
                        </h4>
                        
                        <div v-if="contracts.length === 0" class="text-center py-8 text-zinc-400 text-sm border-2 border-dashed border-zinc-200 rounded-xl">
                            No hay contratos registrados.
                        </div>

                        <TransitionGroup name="list" tag="div" class="space-y-2">
                            <div
                                v-for="file in contracts"
                                :key="file.id"
                                class="flex items-center justify-between rounded-xl border p-3 transition-all duration-300"
                                :class="[
                                    isExpired(file.expiration_date) 
                                        ? 'bg-red-50/50 border-red-200 ring-1 ring-red-100' 
                                        : (isNearExpiry(file.expiration_date) ? 'bg-amber-50/50 border-amber-200' : 'bg-emerald-50/30 border-emerald-100')
                                ]"
                            >
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full" :class="isExpired(file.expiration_date) ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'"></div>
                                        <p class="truncate text-sm font-bold text-zinc-900">{{ file.path || file.file_path.split('/').pop() }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[10px] font-medium text-zinc-500 uppercase">
                                        <span>Subido: {{ formatDate(file.created_at) }}</span>
                                        <span v-if="file.expiration_date" class="flex items-center gap-1" :class="isExpired(file.expiration_date) ? 'text-red-600 font-bold' : 'text-zinc-400'">
                                            <CalendarIcon class="h-3 w-3" />
                                            Vence: {{ formatExpirationDate(file.expiration_date) }}
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <select
                                            v-model="file.status"
                                            @change="updateFileStatus(file.id, ($event.target as HTMLSelectElement).value)"
                                            class="h-7 rounded-md border-zinc-200 text-xs font-semibold text-zinc-600 bg-white/80 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none"
                                            :class="{
                                                'text-blue-600 border-blue-200': file.status === 'Enviado',
                                                'text-green-600 border-green-200': file.status === 'Firmado',
                                                'text-zinc-600 border-zinc-200': file.status === 'Realizado' || !file.status
                                            }"
                                        >
                                            <option value="Realizado">Realizado</option>
                                            <option value="Enviado">Enviado</option>
                                            <option value="Firmado">Firmado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 ml-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg bg-white/50 text-blue-600 shadow-sm hover:bg-blue-600 hover:text-white"
                                        @click="viewFile(file)"
                                        title="Ver contrato"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg bg-white/50 text-red-600 shadow-sm hover:bg-red-600 hover:text-white"
                                        :disabled="deletingFileId === file.id"
                                        @click="deleteFile(file.id)"
                                        title="Eliminar"
                                    >
                                        <template v-if="deletingFileId === file.id">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </template>
                                        <X v-else class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.4s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e4e4e7;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #d4d4d8;
}

input[type='date']::-webkit-calendar-picker-indicator {
    cursor: pointer;
    font-size: 1.2rem;
}
</style>

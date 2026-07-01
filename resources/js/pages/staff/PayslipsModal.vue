<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, Banknote, Eye, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffFile {
    id: number;
    name: string;
    path: string;
    file_type: string;
    file_path: string;
    expiration_date: string | null;
    created_at: string;
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

const FILE_TYPE = 'Boletas de Pago';

const payslips = computed(() =>
    [...(props.staff.staff_files?.filter((f) => f.file_type === FILE_TYPE) || [])].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    ),
);

const handleFileUpload = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];

    if (file.size > 10 * 1024 * 1024) {
        showAlert.value = true;
        alertMessage.value = 'El archivo excede el tamaño máximo de 10MB.';
        input.value = '';
        return;
    }

    uploading.value = true;

    const uploadForm = useForm({
        file: file,
        fileTypeKey: FILE_TYPE,
        expirationDate: null,
        fileId: 0,
        staffId: props.staff.id,
    });

    uploadForm.post(route('staff.upload-file'), {
        forceFormData: true,
        preserveState: true,
        onSuccess: () => { uploading.value = false; showAlert.value = false; },
        onFinish: () => { uploading.value = false; },
        onError: () => {
            showAlert.value = true;
            alertMessage.value = 'Error al subir la boleta.';
            uploading.value = false;
        },
    });

    input.value = '';
};

const deleteFile = (fileId: number) => {
    if (!confirm('¿Está seguro de eliminar esta boleta?')) return;

    deletingFileId.value = fileId;
    const form = useForm({});
    form.delete(route('staff.delete-file', fileId), {
        onSuccess: () => { deletingFileId.value = null; },
        onFinish: () => { deletingFileId.value = null; },
        onError: () => {
            showAlert.value = true;
            alertMessage.value = 'Error al eliminar la boleta.';
            deletingFileId.value = null;
        },
    });
};

const viewFile = (file: StaffFile) => {
    window.open('/storage/' + file.file_path, '_blank');
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 cursor-pointer rounded-full text-emerald-600 transition-all hover:bg-emerald-50 hover:text-emerald-800"
                title="Boletas de Pago"
            >
                <Banknote class="h-4 w-4" />
            </Button>
        </DialogTrigger>

        <DialogContent class="flex h-[80vh] w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[640px]">
            <DialogHeader class="shrink-0 border-b bg-white px-6 py-4 shadow-sm">
                <DialogTitle class="text-xl font-bold text-zinc-800">Boletas de Pago</DialogTitle>
                <p class="text-sm text-zinc-500">Gestión de boletas para {{ staff.name }}</p>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto bg-zinc-50/50">
                <div class="space-y-6 px-6 py-6">

                    <Alert v-if="showAlert" variant="destructive" class="border-red-200 bg-red-50 text-red-900">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>{{ alertMessage }}</AlertDescription>
                        <button class="absolute top-4 right-4 text-red-400 hover:text-red-600" @click="showAlert = false">
                            <X class="h-4 w-4" />
                        </button>
                    </Alert>

                    <!-- Upload -->
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-center gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <Banknote class="h-6 w-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-zinc-900">Subir Nueva Boleta</h4>
                                <span class="text-[10px] font-bold tracking-tighter text-zinc-400 uppercase">PDF · Máx 10MB</span>
                            </div>
                        </div>

                        <label
                            :class="[
                                'relative flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-3 text-sm font-bold transition-all duration-300',
                                uploading
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-600'
                                    : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600',
                            ]"
                        >
                            <template v-if="uploading">
                                <svg class="h-5 w-5 animate-spin text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                <span>Subiendo boleta...</span>
                            </template>
                            <template v-else>
                                <Upload class="h-4 w-4" />
                                <span>Seleccionar PDF y Subir</span>
                            </template>
                            <input type="file" accept=".pdf" class="hidden" :disabled="uploading" @change="handleFileUpload" />
                        </label>
                    </div>

                    <!-- List -->
                    <div class="space-y-3">
                        <h4 class="flex items-center justify-between font-bold text-zinc-800">
                            Historial de Boletas
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                {{ payslips.length }} registradas
                            </span>
                        </h4>

                        <div
                            v-if="payslips.length === 0"
                            class="rounded-xl border-2 border-dashed border-zinc-200 py-8 text-center text-sm text-zinc-400"
                        >
                            No hay boletas registradas.
                        </div>

                        <TransitionGroup name="list" tag="div" class="space-y-2">
                            <div
                                v-for="file in payslips"
                                :key="file.id"
                                class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/30 p-3 transition-all duration-300"
                            >
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-emerald-500" />
                                        <p class="truncate text-sm font-bold text-zinc-900">{{ file.path || file.file_path.split('/').pop() }}</p>
                                    </div>
                                    <p class="text-[10px] font-medium tracking-wider text-zinc-500 uppercase">
                                        Subido: {{ formatDate(file.created_at) }}
                                    </p>
                                </div>

                                <div class="ml-2 flex items-center gap-1.5">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg bg-white/50 text-blue-600 shadow-sm hover:bg-blue-600 hover:text-white"
                                        title="Ver boleta"
                                        @click="viewFile(file)"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg bg-white/50 text-red-600 shadow-sm hover:bg-red-600 hover:text-white"
                                        title="Eliminar"
                                        :disabled="deletingFileId === file.id"
                                        @click="deleteFile(file.id)"
                                    >
                                        <template v-if="deletingFileId === file.id">
                                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
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
.list-leave-active { transition: all 0.4s ease; }
.list-enter-from,
.list-leave-to { opacity: 0; transform: translateX(30px); }

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #e4e4e7; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #d4d4d8; }
</style>

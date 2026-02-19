<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/vue3';
import { CalendarIcon, Eye, File, Upload, X, Award, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffFile {
    id: number;
    name: string;
    path: string;
    file_type: string;
    expiration_date: string | null;
    created_at: string;
    user_name?: string;
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

const page = usePage();
const open = ref(false);
const showAlert = ref(false);
const alertMessage = ref('');
const uploadingFileType = ref<string | null>(null);
const deletingFileId = ref<number | null>(null);
const showDatePicker = ref<number | null>(null);
const selectedExpirationDate = ref<string>('');

// Definición de archivos requeridos
const filesRequired = ref([
    {
        label: 'CV Documentado',
        key: 'cv_documentado',
        accept: '.pdf,.doc,.docx',
        maxSize: 10,
        hasExpiration: false,
        icon: 'FileText'
    },
    {
        label: 'Certificado Único Laboral (CUL)',
        key: 'certificado_unico_laboral',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'Award'
    },
    {
        label: 'Certificado de Estudios',
        key: 'certificado_estudios',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
        icon: 'GraduationCap'
    },
    {
        label: 'Certificados de Trabajo',
        key: 'certificados_trabajo',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
        icon: 'Briefcase'
    },
    {
        label: 'DNI escaneado',
        key: 'dni_escaneado',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 5,
        hasExpiration: true,
        expirationDate: null,
        icon: 'CreditCard'
    },
    {
        label: 'Antecedentes Penales y Policiales',
        key: 'antecedentes_penales',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'ShieldAlert'
    },
    {
        label: 'Carné de sanidad',
        key: 'carne_sanidad',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'Stethoscope'
    },
    {
        label: 'Carné de vacunación contra el COVID',
        key: 'carne_vacunacion_covid',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
        icon: 'Syringe'
    },
    {
        label: 'Examen Medico Ocupacional (EMO)',
        key: 'examen_medico_ocupacional',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'ClipboardCheck'
    },
    {
        label: 'SCTR',
        key: 'sctr',
        accept: '.pdf',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'Activity'
    },
    {
        label: 'Contrato',
        key: 'contrato',
        accept: '.pdf',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
        icon: 'FileSignature'
    },
]);

const form = useForm({
    staff_id: props.staff.id,
    files: [] as File[],
    user_id: page.props.auth.user?.id,
});

// Función para manejar subida de archivos
const handleFileUpload = (event: Event, fileTypeKey: string, fileLabel: string) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    const fileType = filesRequired.value.find((f) => f.label === fileLabel);

    if (!fileType) return;

    // Validar tamaño
    if (file.size > fileType.maxSize * 1024 * 1024) {
        showAlert.value = true;
        alertMessage.value = `El archivo excede el tamaño máximo de ${fileType.maxSize}MB`;
        input.value = '';
        return;
    }

    uploadingFileType.value = fileTypeKey;

    const fileFoundId = props.staff.staff_files?.find((f) => f.file_type == fileLabel)?.id;

    if (fileType.hasExpiration && !fileType.expirationDate) {
        alert('Es necesario colocar la fecha de expiración para este archivo antes de subirlo.');
        uploadingFileType.value = null;
        input.value = '';
        return;
    }

    uploadFile(file, { fileTypeKey: fileLabel, fileFoundId }, fileType.expirationDate || undefined);
    input.value = '';
};

const uploadFile = (file: File, fileProps: any, expirationDate?: string) => {
    const uploadForm = useForm({
        file: file,
        fileTypeKey: fileProps.fileTypeKey,
        expirationDate: expirationDate,
        fileId: fileProps.fileFoundId ?? 0,
        staffId: props.staff.id,
    });

    uploadForm.post(route('staff.upload-file'), {
        forceFormData: true,
        preserveState: true,
        onSuccess: () => {
            uploadingFileType.value = null;
        },
        onFinish: () => {
            uploadingFileType.value = null;
        },
        onError: (errors) => {
            showAlert.value = true;
            alertMessage.value = 'Error al subir el archivo.';
            uploadingFileType.value = null;
        },
    });
};

// Función para eliminar archivo
const deleteFile = (fileId: number) => {
    if (!confirm('¿Está seguro de eliminar este archivo?')) return;

    deletingFileId.value = fileId;
    form.delete(route('staff.delete-file', fileId), {
        onSuccess: () => {
            deletingFileId.value = null;
        },
        onFinish: () => {
            deletingFileId.value = null;
        },
        onError: (errors) => {
            showAlert.value = true;
            alertMessage.value = 'Error al eliminar el archivo.';
            deletingFileId.value = null;
        },
    });
};

// Función para ver archivo
const viewFile = (file: StaffFile) => {
    window.open('/storage/' + file.path, '_blank');
};

// Función para actualizar fecha de expiración
const updateExpirationDate = (fileId: number, expirationDate: string) => {
    const updateForm = useForm({
        fileId: fileId,
        expirationDate: expirationDate,
    });

    updateForm.post(route('staff.update-filedate'), {
        onError: () => {
             alert('Error al actualizar la fecha');
        },
    });
};

// Agrupar archivos por tipo
const groupedFiles = computed(() => {
    const grouped: Record<string, StaffFile[]> = {};

    filesRequired.value.forEach((fileType) => {
        grouped[fileType.label] = [];
    });

    if (props.staff.staff_files) {
        props.staff.staff_files.forEach((file) => {
            const fileType = filesRequired.value.find((f) => f.label === file.file_type);
            if (fileType) {
                grouped[fileType.label].push(file);
            }
        });
    }

    return grouped;
});

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

// Verificar si un archivo está vencido
const isExpired = (expirationDate: string | null) => {
    if (!expirationDate) return false;
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return new Date(expirationDate) < now;
};

// Verificar si está por vencer (30 días)
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
            <Button variant="ghost" size="icon" class="h-9 w-9 cursor-pointer rounded-full text-blue-600 transition-all hover:bg-blue-50 hover:text-blue-800">
                <File class="h-4 w-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="flex h-[95vh] w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-[950px]">
            <DialogHeader class="shrink-0 border-b bg-white px-6 py-4 shadow-sm">
                <DialogTitle class="text-xl font-bold text-zinc-800">Expediente Digital</DialogTitle>
                <p class="text-sm text-zinc-500">Gestión de documentos para {{ staff.name }}</p>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto bg-zinc-50/50">
                <div class="space-y-6 px-6 py-6">
                    <!-- Banner Info -->
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="col-span-1 rounded-xl border border-blue-100 bg-blue-50/50 p-4 lg:col-span-2">
                            <div class="flex gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200">
                                    <File class="h-6 w-6" />
                                </div>
                                <div class="space-y-1">
                                    <h4 class="font-bold text-blue-900">Instrucciones de Carga</h4>
                                    <p class="text-xs leading-relaxed text-blue-700/80">
                                        Suba los documentos en formato <span class="font-bold">PDF, JPG o PNG</span>. El tamaño máximo permitido es de <span class="font-bold">15MB</span> por archivo. Los documentos resaltados en naranja requieren una fecha de vigencia.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex h-full flex-col justify-center gap-1 text-center">
                                <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Total Documentos</span>
                                <span class="text-3xl font-black text-zinc-800">{{ props.staff.staff_files?.length || 0 }} / {{ filesRequired.length }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Alerta de error -->
                    <Alert v-if="showAlert" variant="destructive" class="border-red-200 bg-red-50 text-red-900">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle class="font-bold">Error en la operación</AlertTitle>
                        <AlertDescription>{{ alertMessage }}</AlertDescription>
                        <button class="absolute top-4 right-4 text-red-400 hover:text-red-600" @click="showAlert = false">
                            <X class="h-4 w-4" />
                        </button>
                    </Alert>

                    <!-- Document List Grid -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div 
                            v-for="(fileType, index) in filesRequired" 
                            :key="index"
                            class="group relative flex flex-col rounded-2xl border border-zinc-200 bg-white p-5 transition-all duration-300 hover:border-blue-300 hover:shadow-xl hover:shadow-blue-500/5 shadow-sm"
                        >
                            <div class="mb-4 flex items-start justify-between">
                                <div class="flex gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 transition-colors group-hover:bg-blue-50 group-hover:text-blue-600">
                                        <!-- <component :is="fileType.icon" v-if="groupedFiles[fileType.label]?.length > 0" class="h-6 w-6" /> -->
                                        <File class="h-6 w-6" />
                                    </div>
                                    <div class="space-y-1">
                                        <h4 class="font-bold text-zinc-900 leading-tight">{{ fileType.label }}</h4>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter">Máx {{ fileType.maxSize }}MB</span>
                                            <span v-if="fileType.hasExpiration" class="flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 uppercase">
                                                <CalendarIcon class="h-3 w-3" />
                                                Vigencia obligatoria
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div class="mb-4 space-y-3">
                                <div v-if="fileType.hasExpiration" class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Establecer fecha de expiración</label>
                                    <input
                                        type="date"
                                        v-model="fileType.expirationDate"
                                        class="w-full rounded-lg border-2 border-zinc-100 bg-zinc-50 px-3 py-2 text-sm font-medium transition-all focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100 outline-none"
                                        :min="new Date().toISOString().split('T')[0]"
                                    />
                                </div>

                                <label
                                    :class="[
                                        'relative flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-3 text-sm font-bold transition-all duration-300',
                                        uploadingFileType === fileType.key
                                            ? 'border-blue-500 bg-blue-50 text-blue-600'
                                            : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600',
                                    ]"
                                >
                                    <template v-if="uploadingFileType === fileType.key">
                                        <svg class="h-5 w-5 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Procesando...</span>
                                    </template>
                                    <template v-else>
                                        <Upload class="h-4 w-4" />
                                        <span>{{ groupedFiles[fileType.label]?.length > 0 ? 'Actualizar archivo' : 'Subir documento' }}</span>
                                    </template>
                                    <input
                                        type="file"
                                        :accept="fileType.accept"
                                        class="hidden"
                                        :disabled="uploadingFileType === fileType.key"
                                        @change="handleFileUpload($event, fileType.key, fileType.label)"
                                    />
                                </label>
                            </div>

                            <!-- List of Uploaded Files for this type -->
                            <div v-if="groupedFiles[fileType.label]?.length > 0" class="mt-auto space-y-2">
                                <TransitionGroup name="list">
                                    <div
                                        v-for="file in groupedFiles[fileType.label]"
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
                                                <p class="truncate text-xs font-bold text-zinc-900">{{ file.path }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 text-[10px] font-medium text-zinc-500 uppercase">
                                                <span>{{ formatDate(file.created_at) }}</span>
                                                <span v-if="file.expiration_date" class="flex items-center gap-1" :class="isExpired(file.expiration_date) ? 'text-red-600 font-bold' : 'text-zinc-400'">
                                                    <CalendarIcon class="h-3 w-3" />
                                                    Vence: {{ formatExpirationDate(file.expiration_date) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5 ml-2">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 rounded-lg bg-white/50 text-blue-600 shadow-sm hover:bg-blue-600 hover:text-white"
                                                @click="viewFile(file)"
                                                title="Ver documentación"
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
                </div>
            </div>

            <DialogFooter class="z-20 border-t bg-white px-6 py-4">
                <div class="flex w-full items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex -space-x-2">
                            <div v-for="i in 3" :key="i" class="h-8 w-8 rounded-full border-2 border-white bg-zinc-200"></div>
                        </div>
                        <span class="text-xs font-medium text-zinc-500">Expediente auditado por {{ page.props.auth.user?.name }}</span>
                    </div>
                    <Button variant="outline" @click="open = false" class="rounded-xl font-bold px-6">Finalizar Gestión</Button>
                </div>
            </DialogFooter>
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

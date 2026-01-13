<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/vue3';
import { CalendarIcon, Eye, File, Upload, X } from 'lucide-vue-next';
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
    },
    {
        label: 'Certificado Único Laboral (CUL)',
        key: 'certificado_unico_laboral',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'Certificado de Estudios',
        key: 'certificado_estudios',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
    },
    {
        label: 'Certificados de Trabajo',
        key: 'certificados_trabajo',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
    },
    {
        label: 'DNI escaneado',
        key: 'dni_escaneado',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 5,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'Antecedentes Penales y Policiales',
        key: 'antecedentes_penales',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'Carné de sanidad',
        key: 'carne_sanidad',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'Carné de vacunación contra el COVID',
        key: 'carne_vacunacion_covid',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: false,
    },
    {
        label: 'Examen Medico Ocupacional (EMO)',
        key: 'examen_medico_ocupacional',
        accept: '.pdf,.jpg,.jpeg,.png',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'SCTR',
        key: 'sctr',
        accept: '.pdf',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
    {
        label: 'Contrato',
        key: 'contrato',
        accept: '.pdf',
        maxSize: 10,
        hasExpiration: true,
        expirationDate: null,
    },
]);

const form = useForm({
    staff_id: props.staff.id,
    files: [] as File[],
    user_id: page.props.auth.user?.id,
});

// Función para manejar subida de archivos
const handleFileUpload = (event: Event, fileTypeKey: string, fileIndex?: number) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    const fileType = filesRequired.value.find((f) => f.label === fileTypeKey);

    if (!fileType) return;

    // Validar tamaño
    if (file.size > fileType.maxSize * 1024 * 1024) {
        showAlert.value = true;
        alertMessage.value = `El archivo excede el tamaño máximo de ${fileType.maxSize}MB`;
        input.value = '';
        return;
    }

    // Validar tipo de archivo
    const validExtensions = fileType.accept.split(',').map((ext) => ext.trim().toLowerCase());
    const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();

    if (!validExtensions.includes(fileExtension)) {
        showAlert.value = true;
        alertMessage.value = `Tipo de archivo no válido. Formatos aceptados: ${fileType.accept}`;
        input.value = '';
        return;
    }

    uploadingFileType.value = fileTypeKey;

    const fileFoundId = props.staff.staff_files?.find((f) => f.file_type == fileTypeKey)?.id;

    if (fileType.hasExpiration && fileType.expirationDate == null) {
        alert('Es necesario colocar la fecha de expiración a este archivo');
        return;
    } else if (!fileType.hasExpiration && fileType.expirationDate == null) {
        uploadFile(file, { fileTypeKey, fileFoundId });
    } else if (fileType.hasExpiration && fileType.expirationDate != null) {
        uploadFile(file, { fileTypeKey, fileFoundId }, fileType.expirationDate);
    }

    input.value = '';
};

// Función para subir archivo con fecha de expiración
const uploadFileWithDate = () => {
    if (showDatePicker.value === null || !selectedExpirationDate.value) {
        showAlert.value = true;
        alertMessage.value = 'Debe seleccionar una fecha de expiración';
        return;
    }

    console.log(filesRequired.value[showDatePicker.value]);

    const fileTypeKey = filesRequired.value[showDatePicker.value].key;
    const fileLabel = filesRequired.value[showDatePicker.value].label;
    const input = document.querySelector(`input[data-file-type="${fileTypeKey}"]`) as HTMLInputElement;

    if (!input || !input.files || input.files.length === 0) {
        showAlert.value = true;
        alertMessage.value = 'No se encontró el archivo';
        return;
    }

    const val = selectedExpirationDate.value;

    // Convertimos a string y rellenamos con '0' a la izquierda si es necesario
    const year = val.year;
    const month = String(val.month).padStart(2, '0');
    const day = String(val.day).padStart(2, '0');

    const dateString = `${year}-${month}-${day}`;

    console.log(dateString);

    const file = input.files[0];
    uploadFile(file, { fileLabel }, dateString);

    // Resetear
    showDatePicker.value = null;
    selectedExpirationDate.value = '';
    input.value = '';
};

const uploadFile = (file: File, fileProps: any, expirationDate?: string) => {
    console.log(expirationDate);

    const form = useForm({
        file: file,
        fileTypeKey: fileProps.fileTypeKey || fileProps.fileLabel,
        expirationDate: expirationDate,
        fileId: fileProps.fileFoundId ?? 0,
        staffId: props.staff.id,
    });

    form.post(route('staff.upload-file'), {
        forceFormData: true,
        preserveState: true,
        onSuccess: (res) => {
            alert('Archivo actualizado correctamente');
        },
        onError: (errors) => {
            showAlert.value = true;
            alertMessage.value = 'Error en la conexión';
            console.error(error);
        },
    });
};

// Función para eliminar archivo
const deleteFile = (fileId: number) => {
    if (!confirm('¿Está seguro de eliminar este archivo?')) return;

    form.delete(route('staff.delete-file', fileId), {
        onSuccess: () => {
            alert('Archivo eliminado correctamente');
        },
        onError: (errors) => {
            showAlert.value = true;
            alertMessage.value = 'Error en la conexión';
            console.error(errors);
        },
    });
};

// Función para descargar archivo
const downloadFile = (file: StaffFile) => {
    window.open(route('staff.download-file', file.id), '_blank');
};

// Función para ver archivo
const viewFile = (file: StaffFile) => {
    window.open('/storage/' + file.file_path, '_blank');
};

// Función para actualizar fecha de expiración
const updateExpirationDate = (fileId: number, expirationDate: string) => {
    const form = useForm({
        fileId: fileId,
        expirationDate: expirationDate,
    });

    form.post(route('staff.update-filedate'), {
        onSuccess: () => {
            alert('Fecha actualizada correctamente');
        },
        onError: (errors) => {
            alert('Error en la actualizacion');
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

const emit = defineEmits(['update-files']);

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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

// Obtener el ícono según tipo de archivo
const getFileIcon = (fileName: string) => {
    const ext = fileName.split('.').pop()?.toLowerCase();
    if (ext === 'pdf') return '📄';
    if (['doc', 'docx'].includes(ext || '')) return '📝';
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext || '')) return '🖼️';
    return '📎';
};

// Verificar si un archivo está vencido
const isExpired = (expirationDate: string | null) => {
    if (!expirationDate) return false;
    return new Date(expirationDate) < new Date();
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="cursor-pointer text-blue-600 hover:text-blue-800">
                <File class="h-4 w-4" />
            </Button>
        </DialogTrigger>
        <DialogContent class="flex max-h-[90vh] w-full flex-col sm:max-w-[900px]">
            <DialogHeader class="shrink-0 border-b pb-3">
                <DialogTitle>Gestión de Documentos del Personal</DialogTitle>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto pr-2">
                <!-- Información del personal -->
                <div class="rounded-lg bg-blue-50 p-4">
                    <div class="flex items-center space-x-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                            <File class="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">{{ staff.name }}</h3>
                            <p class="text-sm text-gray-600">Gestión de documentos y archivos</p>
                        </div>
                    </div>
                </div>

                <!-- Alerta informativa -->
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-start">
                        <div class="mt-0.5 mr-3">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-600">!</div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-amber-800">Atención</h4>
                            <p class="mt-1 text-sm text-amber-700">
                                Asegúrese de que los archivos sean legibles (PDF, DOC, JPG, PNG) y que pesen menos de 10MB. Los documentos marcados
                                con 📅 requieren fecha de expiración.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Alerta de error -->
                <Alert v-if="showAlert" variant="destructive" class="relative">
                    <button class="absolute top-3 right-3 text-sm" @click="showAlert = false">✕</button>
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{{ alertMessage }}</AlertDescription>
                </Alert>

                <!-- Lista de documentos -->
                <div class="space-y-6">
                    <div v-for="(fileType, index) in filesRequired" :key="index" class="space-y-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <!-- Encabezado del tipo de documento -->
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100">
                                        <span class="text-sm font-medium">{{ index + 1 }}</span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ fileType.label }}</h4>
                                        <div class="flex items-center space-x-2 text-sm text-gray-500">
                                            <span>Formatos: {{ fileType.accept.replace(/\./g, '').toUpperCase() }}</span>
                                            <span>•</span>
                                            <span>Máx: {{ fileType.maxSize }}MB</span>
                                            <span v-if="fileType.hasExpiration" class="inline-flex items-center">
                                                <CalendarIcon class="ml-2 h-4 w-4 text-amber-600" />
                                                <span class="ml-1 text-amber-600">Requiere fecha</span>
                                                <input
                                                    type="date"
                                                    v-model="fileType.expirationDate"
                                                    class="ms-1 rounded border border-gray-300 px-2 py-1 text-xs"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                />
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botón para subir archivo -->
                                <label
                                    :class="[
                                        'cursor-pointer rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                        uploadingFileType === fileType.key
                                            ? 'cursor-not-allowed bg-blue-400 text-white'
                                            : 'bg-blue-600 text-white hover:bg-blue-700',
                                    ]"
                                >
                                    <span v-if="uploadingFileType === fileType.key">
                                        <span class="inline-flex items-center">
                                            <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path
                                                    class="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                ></path>
                                            </svg>
                                            Subiendo...
                                        </span>
                                    </span>
                                    <span v-else class="inline-flex items-center">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Subir
                                    </span>
                                    <input
                                        type="file"
                                        :accept="fileType.accept"
                                        class="hidden"
                                        :data-file-type="fileType.key"
                                        @change="handleFileUpload($event, fileType.label, index)"
                                    />
                                </label>
                            </div>

                            <!-- Archivos existentes -->
                            <div v-if="groupedFiles[fileType.label] && groupedFiles[fileType.label].length > 0" class="space-y-2">
                                <div
                                    v-for="file in groupedFiles[fileType.label]"
                                    :key="file.id"
                                    :class="[
                                        'rounded-lg border p-3 transition-colors',
                                        isExpired(file.expiration_date) ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50',
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <!-- <span class="text-xl">{{ getFileIcon(file.path) }}</span> -->
                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <p class="text-sm font-medium text-gray-700">{{ file.path }}</p>
                                                    <span
                                                        v-if="isExpired(file.expiration_date)"
                                                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                                    >
                                                        VENCIDO
                                                    </span>
                                                </div>
                                                <div class="mt-1 space-y-1 text-xs text-gray-500">
                                                    <p>Subido: {{ formatDate(file.created_at) }}</p>
                                                    <div class="flex items-center space-x-2">
                                                        <span v-if="file.user_name">Por: {{ file.user_name }}</span>
                                                        <span v-if="fileType.hasExpiration" class="inline-flex items-center">
                                                            <CalendarIcon class="mr-1 h-3 w-3" />
                                                            Vence: {{ formatExpirationDate(file.expiration_date) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Acciones -->
                                        <div class="flex items-center space-x-1">
                                            <!-- Input para fecha de expiración -->
                                            <div v-if="fileType.hasExpiration" class="mr-2">
                                                <input
                                                    type="date"
                                                    :value="file.expiration_date || ''"
                                                    @change="updateExpirationDate(file.id, ($event.target as HTMLInputElement).value)"
                                                    class="rounded border border-gray-300 px-2 py-1 text-xs"
                                                    :min="new Date().toISOString().split('T')[0]"
                                                />
                                            </div>

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-blue-600 hover:bg-blue-100"
                                                @click="viewFile(file)"
                                                title="Vista previa"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <!-- <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-green-600 hover:bg-green-100"
                                                @click="downloadFile(file)"
                                                title="Descargar"
                                            >
                                                <Download class="h-4 w-4" />
                                            </Button> -->
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-red-600 hover:bg-red-100"
                                                @click="deleteFile(file.id)"
                                                title="Eliminar"
                                            >
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mensaje cuando no hay archivos -->
                            <div v-else class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center">
                                <File class="mx-auto h-8 w-8 text-gray-400" />
                                <p class="mt-2 text-sm font-medium text-gray-900">No hay archivos subidos</p>
                                <p class="mt-1 text-xs text-gray-500">Haz clic en "Subir" para agregar un documento</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t pt-4">
                <div class="flex w-full items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <span class="font-medium">{{ props.staff.staff_files?.length || 0 }}</span> archivos en total
                    </div>
                    <Button variant="outline" @click="open = false">Cerrar</Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

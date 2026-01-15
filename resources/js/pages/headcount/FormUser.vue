<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Cafe, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref } from 'vue';
import AlertErrors from './AlertErrors.vue';
import InputSearchRole from './InputSearchRole.vue';
import InputSearchSelectable from './InputSearchSelectable.vue';

interface Props {
    cafes: Cafe[];
    roles: Role[];
}

const props = defineProps<Props>();

// Estado para controlar si el modal está abierto
const isOpen = ref(false);
const activeTab = ref('personal');
const fileInput = ref<HTMLInputElement | null>(null);
const imagePreview = ref<string | null>(null);
const selectedFile = ref<File | null>(null);

const prendasFijas = ref([
    { label: 'Polo', talla: '' },
    { label: 'Cafarena', talla: '' },
    { label: 'Overall', talla: '' },
    { label: 'Casaca', talla: '' },
    { label: 'Chaleco', talla: '' },
    { label: 'Chaqueta Blanca', talla: '' },
    { label: 'Pantalón', talla: '' },
    { label: 'Camisa/Blusa', talla: '' },
    { label: 'Guardapolvo', talla: '' },
    { label: 'Guantes', talla: '' },
    { label: 'Botas Blancas', talla: '' },
    { label: 'Zapatos', talla: '' },
    { label: 'Lentes', talla: '' },
]);

const filesRequired = ref([
    { label: 'CV Documentado', file: {} },
    { label: 'Certificado Único Laboral (CUL)', file: {} },
    { label: 'Certificado de Estudios', file: {} },
    { label: 'Certificados de Trabajo', file: {} },
    { label: 'DNI escaneado', file: {} },
    { label: 'Antecedentes Penales y Policiales', file: {} },
    { label: 'Carné de sanidad', file: {} },
    { label: 'Carné de vacunación contra el COVID', file: {} },
    { label: 'Examen Medico Ocupacional (EMO)', file: {} },
    { label: 'SCTR', file: {} },
    { label: 'Contrato', file: {} },
]);

const form = useForm({
    name: '',
    dni: '',
    cell: '',
    birthdate: '',
    age: 0,
    sex: 0,
    email: '',
    country: '',
    civilstatus: 0,
    contactname: '',
    contactcell: '',
    cafeId: null,
    files: [],
    tipoContrato: '',
    regimenLaboral: '',
    fechaIngreso: '',
    fechaFinContract: '',
    cc: '',
    cci: '',
    children: 0,
    prendas: [],
    district: '',
    province: '',
    department: '',
    afp: '',
    onp: '',
    position: '',
    address: '',
    workSystem: '',
    replacement: '',
    unitId: 0,
    salary: 0.0,
    observations: '',
    fondo: 0,
    roleId: null,
    unitSelectedText: '',
});

const errorsSend = ref([]);
const showErrors = ref(false);

const nextTab = () => {
    const tabs = ['personal', 'adjuntos', 'financiero', 'tallas'];
    const currentIndex = tabs.indexOf(activeTab.value);
    if (currentIndex < tabs.length - 1) {
        activeTab.value = tabs[currentIndex + 1];
    }
};

const prevTab = () => {
    const tabs = ['personal', 'adjuntos', 'financiero', 'tallas'];
    const currentIndex = tabs.indexOf(activeTab.value);
    if (currentIndex > 0) {
        activeTab.value = tabs[currentIndex - 1];
    }
};

const handleSubmit = () => {
    form.prendas = prendasFijas.value;

    form.post(route('staff.store'), {
        onSuccess: () => {
            form.reset();
            isOpen.value = false;
            activeTab.value = 'personal';
        },
        onError: (errors) => {
            showErrors.value = true;
            errorsSend.value = errors;
            console.error('Error al subir el archivo:', errors);
        },
    });
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        // Validar tipo de archivo
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecciona solo archivos de imagen');
            return;
        }

        // Validar tamaño (ej: máximo 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB en bytes
        if (file.size > maxSize) {
            alert('La imagen es demasiado grande. Máximo 5MB');
            return;
        }

        selectedFile.value = file;

        // Crear preview
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        // Emitir evento si es necesario
        // emit('image-uploaded', file);
    }
};

const removeImage = () => {
    imagePreview.value = null;
    selectedFile.value = null;

    // Resetear input file
    if (fileInput.value) {
        fileInput.value.value = '';
    }

    // Emitir evento si es necesario
    // emit('image-removed');
};

const selectCafe = (cafe: Cafe) => {
    form.cafeId = cafe.id;
    form.unitId = cafe.unit_id;
    form.unitSelectedText = cafe.unit.name;
};

const selectRole = (role: Role) => {
    form.roleId = role.id;
};

const MAX_FILE_SIZE = 9 * 1024 * 1024; // 5MB example
const showAlert = ref(false);
const alertMessage = ref('');

const handleFileUpload = (event: any, fileLabel: string) => {
    const files = event.target.files;
    if (files.length > 0) {
        const newFile = files[0];
        if (newFile.size > MAX_FILE_SIZE) {
            alertMessage.value = 'El archivo es demasiado grande. El máximo es 9MB.';
            showAlert.value = true;
            event.target.value = null;
        } else {
            const registerFileFound = filesRequired.value.find((file) => file.label == fileLabel);
            if (registerFileFound) {
                // Include the label in the filename or use FormData
                const originalName = newFile.name;
                const extension = originalName.split('.').pop();
                // Create a new file with label in name or use a separate field
                const modifiedFile = new File([newFile], `${fileLabel}_${Date.now()}.${extension}`, {
                    type: newFile.type,
                });
                modifiedFile.label = fileLabel; // Still add label for FormData

                form.files.push(modifiedFile);
            }
        }
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <!-- Botón que abre el Modal -->
        <DialogTrigger as-child>
            <Button variant="default" class="cursor-pointer bg-blue-600 text-white hover:bg-blue-700">Nuevo Personal</Button>
        </DialogTrigger>
        <DialogContent class="flex h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
            <!-- Encabezado Fijo -->
            <DialogHeader class="z-10 border-b bg-white px-6 py-4">
                <DialogTitle class="text-xl font-bold text-zinc-800 md:text-2xl"> Ficha de Registro de Colaborador </DialogTitle>
                <DialogDescription> Complete los datos del proceso de alta en las secciones a continuación. </DialogDescription>
                <AlertErrors :show="showErrors" :errors="errorsSend" />
            </DialogHeader>

            <!-- Cuerpo Scrolleable (Aquí va el formulario pesado) -->
            <div class="flex-1 overflow-y-auto bg-gray-50/50 px-6 py-4">
                <Tabs v-model="activeTab" class="w-full">
                    <!-- Navegación de Pestañas -->
                    <TabsList class="mb-6 grid w-full grid-cols-4 bg-zinc-100 p-1">
                        <TabsTrigger value="personal" class="text-xs md:text-sm">1. Personal</TabsTrigger>
                        <TabsTrigger value="adjuntos" class="text-xs md:text-sm">2. Adjuntos</TabsTrigger>
                        <TabsTrigger value="financiero" class="text-xs md:text-sm">3. Financiero</TabsTrigger>
                        <TabsTrigger value="tallas" class="text-xs md:text-sm">4. Tallas</TabsTrigger>
                    </TabsList>

                    <!-- PESTAÑA 1: Datos Personales -->
                    <TabsContent value="personal" class="mt-0 space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                            <!-- Foto -->
                            <div class="flex flex-col items-center space-y-3 rounded-lg border bg-white p-4 shadow-sm md:col-span-1">
                                <div class="relative">
                                    <img
                                        :src="imagePreview || 'https://placehold.co/150x200/52525B/FFFFFF?text=FOTO'"
                                        alt="Foto Colaborador"
                                        class="h-40 w-32 rounded-md border object-cover shadow-sm"
                                    />

                                    <!-- Botón para eliminar imagen (opcional) -->
                                    <button
                                        v-if="imagePreview"
                                        @click="removeImage"
                                        type="button"
                                        class="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white shadow-sm hover:bg-red-600"
                                    >
                                        <X :size="14" />
                                    </button>
                                </div>

                                <!-- Input file oculto y botón personalizado -->
                                <div class="relative w-full">
                                    <input type="file" ref="fileInput" @change="handleImageUpload" accept="image/*" class="hidden" id="file-upload" />
                                    <Button variant="outline" size="sm" class="w-full" @click="triggerFileInput" type="button">
                                        {{ imagePreview ? 'Cambiar Foto' : 'Subir Foto' }}
                                    </Button>
                                </div>
                                <Input placeholder="Cód. Colaborador" class="text-center" />

                                <InputSearchSelectable :cafes="props.cafes" @selectCafe="selectCafe" :cafeSelected="form.cafeId" />
                            </div>

                            <!-- Datos Texto -->
                            <div class="space-y-4 md:col-span-3">
                                <h3 class="border-b pb-2 text-lg font-semibold text-zinc-700">Datos Generales</h3>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <div class="space-y-1">
                                        <Label for="nombres">Nombre Completo *</Label>
                                        <Input id="nombres" v-model="form.name" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="doc">DNI / C.E. *</Label>
                                        <Input id="doc" v-model="form.dni" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="cel">Celular *</Label>
                                        <Input id="cel" v-model="form.cell" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label>F. Nacimiento</Label>
                                        <Input type="date" v-model="form.birthdate" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="age">Edad</Label>
                                        <Input id="age" v-model="form.age" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label>Sexo</Label>
                                        <Select v-model="form.sex">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">Masculino</SelectItem>
                                                <SelectItem value="2">Femenino</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-1">
                                        <Label>Email</Label>
                                        <Input v-model="form.email" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label>Nacionalidad</Label>
                                        <Input v-model="form.country" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label>Estado Civil</Label>
                                        <Select v-model="form.civilstatus">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">Soltero</SelectItem>
                                                <SelectItem value="2">Casadoo</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergencia -->
                        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50/50 p-4">
                            <h3 class="border-b border-red-200 pb-2 text-sm font-semibold tracking-wider text-red-700 uppercase">
                                Contacto Emergencia
                            </h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-1"><Label>Nombre</Label><Input v-model="form.contactname" class="bg-white" /></div>

                                <div class="space-y-1"><Label>Celular</Label><Input v-model="form.contactcell" class="bg-white" /></div>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- PESTAÑA 2: Adjuntos -->
                    <TabsContent value="adjuntos" class="mt-0 space-y-4">
                        <div class="mb-4 flex items-center rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="alert">
                            <svg
                                class="mr-3 inline h-4 w-4 flex-shrink-0"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"
                                />
                            </svg>
                            <span class="sr-only">Info</span>
                            <div><span class="font-medium">Atención!</span> Asegúrese de que los archivos sean legibles (PDF o JPG).</div>
                        </div>

                        <Alert v-if="showAlert" variant="destructive" class="relative mb-4">
                            <button class="absolute top-3 right-3 text-sm" @click="showAlert = false">✕</button>

                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription>
                                {{ alertMessage }}
                            </AlertDescription>
                        </Alert>

                        <div class="grid grid-cols-1 gap-2">
                            <div
                                v-for="(file, index) in filesRequired"
                                :key="index"
                                class="flex items-center justify-between rounded-lg border bg-white p-3 shadow-sm"
                            >
                                <span class="text-sm font-medium text-zinc-700">{{ file.label }}</span>
                                <Input
                                    type="file"
                                    class="w-[350px] text-xs file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200"
                                    accept="application/pdf, image/jpeg"
                                    @change="handleFileUpload($event, file.label)"
                                />
                            </div>
                        </div>
                    </TabsContent>

                    <!-- PESTAÑA 3: Financiero -->
                    <TabsContent value="financiero" class="mt-0 space-y-6">
                        <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
                            <h3 class="border-b pb-2 text-lg font-semibold text-zinc-800">Datos del colaborador</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-1"><Label>Distrito</Label><Input v-model="form.district" /></div>
                                <div class="space-y-1"><Label>Provincia</Label><Input v-model="form.province" /></div>
                                <div class="space-y-1"><Label>Departamento</Label><Input v-model="form.department" /></div>
                                <div class="space-y-1">
                                    <Label>Fondo de pensiones</Label>
                                    <Select v-model="form.fondo">
                                        <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">AFP</SelectItem>
                                            <SelectItem value="2">ONP</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1" v-if="form.fondo == 1"><Label>Nombre de AFP</Label><Input v-model="form.afp" /></div>

                                <div class="space-y-1">
                                    <Label>Rol</Label>
                                    <InputSearchRole @selectRole="selectRole" :roles="props.roles" :roleSelected="form.roleId" />
                                </div>
                                <div class="space-y-1"><Label>Dirección</Label><Input v-model="form.address" /></div>
                                <div class="space-y-1"><Label>Sistema de Trabajo</Label><Input v-model="form.workSystem" /></div>
                                <div class="space-y-1"><Label>Reemplazo</Label><Input v-model="form.replacement" /></div>
                                <div class="space-y-1"><Label>Unidad</Label><Input v-model="form.unitSelectedText" readonly /></div>
                                <div class="space-y-1"><Label>Sueldo</Label><Input v-model="form.salary" /></div>
                                <div class="space-y-1"><Label>Observaciones</Label><Input v-model="form.observations" /></div>
                            </div>
                        </div>
                        <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
                            <h3 class="border-b pb-2 text-lg font-semibold text-zinc-800">Datos financieros</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-1">
                                    <Label>Entidad Bancaria</Label>
                                    <Select v-model="form.tipoContrato">
                                        <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                        <SelectContent><SelectItem value="1">BBVA</SelectItem><SelectItem value="2">BCP</SelectItem></SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1"><Label>Número de Cuenta</Label><Input v-model="form.cc" /></div>
                                <div class="space-y-1"><Label>Número de Cuenta CI</Label><Input v-model="form.cci" /></div>
                                <div class="space-y-1">
                                    <Label>Aportación</Label>
                                    <Select v-model="form.regimenLaboral">
                                        <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                        <SelectContent><SelectItem value="G">General</SelectItem></SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1">
                                    <Label>Fecha de ingreso</Label>
                                    <Input type="date" v-model="form.fechaIngreso" />
                                </div>
                                <div class="space-y-1">
                                    <Label>Fecha de Fin de Contrato</Label>
                                    <Input type="date" v-model="form.fechaFinContract" />
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
                            <h3 class="border-b pb-2 text-lg font-semibold text-zinc-800">Carga Familiar</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="space-y-1"><Label>Número de Hijos</Label><Input v-model="form.children" /></div>
                                <div class="space-y-1">
                                    <Label>DNIs en PDF</Label>
                                    <Input
                                        type="file"
                                        class="w-[350px] text-xs file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200"
                                        accept="application/pdf, image/jpeg"
                                        @change="handleFileUpload($event, 'DNIs hijos')"
                                    />
                                </div>
                            </div>
                        </div>
                    </TabsContent>
                    <!-- PESTAÑA 4: Tallas -->
                    <TabsContent value="tallas" class="mt-0 space-y-6">
                        <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
                            <div class="mb-4 flex flex-col justify-between border-b pb-2 sm:flex-row sm:items-center">
                                <h3 class="text-lg font-semibold text-zinc-800">Implementos</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <div
                                    v-for="(prenda, index) in prendasFijas"
                                    :key="index"
                                    class="flex items-start space-x-3 rounded border border-transparent p-2 hover:border-zinc-100 hover:bg-zinc-50"
                                >
                                    <div class="space-y-1">
                                        <Label :for="'prenda-' + index" class="cursor-pointer text-sm font-normal">
                                            {{ prenda.label }}
                                        </Label>
                                        <Select v-model="prenda.talla" v-if="index < 9">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent
                                                ><SelectItem value="s">S</SelectItem><SelectItem value="m">M</SelectItem
                                                ><SelectItem value="l">L</SelectItem><SelectItem value="xl">XL</SelectItem
                                                ><SelectItem value="xxl">XXL</SelectItem></SelectContent
                                            >
                                        </Select>
                                        <Select v-model="prenda.talla" v-else-if="index == 9">
                                            <SelectTrigger><SelectValue placeholder="Seleccionar" /></SelectTrigger>
                                            <SelectContent><SelectItem value="8">8</SelectItem><SelectItem value="9">9</SelectItem></SelectContent>
                                        </Select>
                                        <Input class="text" placeholder="Talla" v-model="prenda.talla" v-else />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Footer Fijo -->
            <DialogFooter class="z-10 flex w-full flex-row items-center justify-between border-t bg-white px-6 py-4 sm:justify-between">
                <!-- Botón Izquierda (Anterior) -->
                <div>
                    <Button v-if="activeTab !== 'personal'" @click="prevTab" variant="outline" type="button"> ← Anterior </Button>
                </div>

                <!-- Botón Derecha (Siguiente / Guardar) -->
                <div>
                    <Button v-if="activeTab !== 'tallas'" @click="nextTab" type="button" class="bg-blue-600 text-white hover:bg-blue-700">
                        Siguiente →
                    </Button>
                    <Button v-else @click="handleSubmit" type="button" class="bg-green-600 text-white hover:bg-green-700">
                        Finalizar Registro
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

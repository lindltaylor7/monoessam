<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { Sheet } from 'lucide-vue-next';
import { ref } from 'vue';

const open = ref(false);

const form = useForm({
    file: null,
});

const handleFileChange = (event: any) => {
    form.file = event.target.files[0]; // Asigna el archivo seleccionado al form
};

const submit = () => {
    // Verifica que se haya seleccionado un archivo
    if (!form.file) {
        alert('Por favor selecciona un archivo');
        return;
    }

    // Crea un FormData para enviar el archivo
    const formData = new FormData();
    formData.append('file', form.file);

    // Envía el formulario
    form.post(route('dinners.excel'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
        onError: (errors: any) => {
            console.error('Error al subir el archivo:', errors);
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                title="Agregar comensales"
                class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-md shadow-emerald-200 transition-all hover:bg-emerald-700"
            >
                <Sheet class="h-4 w-4" />
                Carga Masiva (Excel)
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-xl font-bold text-slate-900">
                    <Sheet class="h-5 w-5 text-emerald-600" />
                    Carga Masiva de Comensales
                </DialogTitle>
                <p class="mt-2 text-sm text-slate-500">Importa múltiples comensales desde un archivo Excel siguiendo el formato especificado</p>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- Instructions Card -->
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-bold text-blue-900">
                        <Icon name="info" size="16" class="text-blue-600" />
                        Instrucciones de Formato
                    </h3>
                    <p class="mb-3 text-xs text-blue-800">Tu archivo Excel debe contener las siguientes columnas en este orden:</p>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div class="rounded-lg border border-blue-100 bg-white p-2">
                            <span class="text-xs font-bold text-blue-900">Columna A:</span>
                            <span class="ml-1 text-xs text-slate-600">Nombre completo</span>
                        </div>
                        <div class="rounded-lg border border-blue-100 bg-white p-2">
                            <span class="text-xs font-bold text-blue-900">Columna B:</span>
                            <span class="ml-1 text-xs text-slate-600">DNI (8 dígitos)</span>
                        </div>
                        <div class="rounded-lg border border-blue-100 bg-white p-2">
                            <span class="text-xs font-bold text-blue-900">Columna C:</span>
                            <span class="ml-1 text-xs text-slate-600">Teléfono</span>
                        </div>
                        <div class="rounded-lg border border-blue-100 bg-white p-2">
                            <span class="text-xs font-bold text-blue-900">Columna D:</span>
                            <span class="ml-1 text-xs text-slate-600">ID Subconcesionaria</span>
                        </div>
                        <div class="rounded-lg border border-blue-100 bg-white p-2 md:col-span-2">
                            <span class="text-xs font-bold text-blue-900">Columna E:</span>
                            <span class="ml-1 text-xs text-slate-600">ID Cafetería</span>
                        </div>
                    </div>
                </div>

                <!-- Visual Example -->
                <div class="space-y-3">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <Icon name="image" size="16" class="text-slate-600" />
                        Ejemplo Visual
                    </h3>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <img
                            src="/images/examples/excel_format.png"
                            alt="Formato de Excel para importación"
                            class="w-full rounded-lg border border-slate-300 shadow-md"
                        />
                        <p class="mt-3 text-center text-xs text-slate-500 italic">Ejemplo de formato correcto para la importación de comensales</p>
                    </div>
                </div>

                <!-- Important Notes -->
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-bold text-amber-900">
                        <Icon name="alert-triangle" size="16" class="text-amber-600" />
                        Notas Importantes
                    </h3>
                    <ul class="space-y-1.5 text-xs text-amber-800">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 text-amber-600">•</span>
                            <span>No incluyas encabezados en la primera fila</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 text-amber-600">•</span>
                            <span>Los IDs de subconcesionaria y cafetería deben ser numéricos y válidos</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 text-amber-600">•</span>
                            <span>El DNI debe tener exactamente 8 dígitos</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 text-amber-600">•</span>
                            <span>Formatos aceptados: .xlsx, .xls, .csv</span>
                        </li>
                    </ul>
                </div>

                <!-- File Upload -->
                <div class="space-y-2">
                    <label class="text-sm font-bold text-slate-700">Seleccionar Archivo</label>
                    <Input
                        type="file"
                        @change="handleFileChange"
                        placeholder="Selecciona un archivo Excel"
                        class="h-11 cursor-pointer rounded-xl border-slate-300 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100"
                        accept=".xlsx,.xls,.csv"
                    />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false" class="rounded-xl"> Cancelar </Button>
                <Button @click="submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-700">
                    <Icon name="upload" size="16" class="mr-2" />
                    Cargar Comensales
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

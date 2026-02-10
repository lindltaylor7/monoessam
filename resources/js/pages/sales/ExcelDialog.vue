<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { Sheet } from 'lucide-vue-next';
import { ref } from 'vue';
import Icon from '@/components/Icon.vue';

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
            <Button title="Agregar comensales" class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-200 transition-all flex items-center justify-center gap-2">
                <Sheet class="h-4 w-4" /> 
                Carga Masiva (Excel)
            </Button>
        </DialogTrigger>
        <DialogContent  class="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="text-xl font-bold text-slate-900 flex items-center gap-2">
                    <Sheet class="h-5 w-5 text-emerald-600" />
                    Carga Masiva de Comensales
                </DialogTitle>
                <p class="text-sm text-slate-500 mt-2">Importa múltiples comensales desde un archivo Excel siguiendo el formato especificado</p>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- Instructions Card -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <h3 class="font-bold text-blue-900 text-sm mb-3 flex items-center gap-2">
                        <Icon name="info" size="16" class="text-blue-600" />
                        Instrucciones de Formato
                    </h3>
                    <p class="text-xs text-blue-800 mb-3">Tu archivo Excel debe contener las siguientes columnas en este orden:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="font-bold text-blue-900 text-xs">Columna A:</span>
                            <span class="text-xs text-slate-600 ml-1">Nombre completo</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="font-bold text-blue-900 text-xs">Columna B:</span>
                            <span class="text-xs text-slate-600 ml-1">DNI (8 dígitos)</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="font-bold text-blue-900 text-xs">Columna C:</span>
                            <span class="text-xs text-slate-600 ml-1">Teléfono</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="font-bold text-blue-900 text-xs">Columna D:</span>
                            <span class="text-xs text-slate-600 ml-1">ID Subconcesionaria</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100 md:col-span-2">
                            <span class="font-bold text-blue-900 text-xs">Columna E:</span>
                            <span class="text-xs text-slate-600 ml-1">ID Cafetería</span>
                        </div>
                    </div>
                </div>

                <!-- Visual Example -->
                <div class="space-y-3">
                    <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        <Icon name="image" size="16" class="text-slate-600" />
                        Ejemplo Visual
                    </h3>
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <img 
                            src="/images/examples/excel_format.png" 
                            alt="Formato de Excel para importación" 
                            class="w-full rounded-lg shadow-md border border-slate-300"
                        />
                        <p class="text-xs text-slate-500 mt-3 text-center italic">
                            Ejemplo de formato correcto para la importación de comensales
                        </p>
                    </div>
                </div>

                <!-- Important Notes -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <h3 class="font-bold text-amber-900 text-sm mb-2 flex items-center gap-2">
                        <Icon name="alert-triangle" size="16" class="text-amber-600" />
                        Notas Importantes
                    </h3>
                    <ul class="space-y-1.5 text-xs text-amber-800">
                        <li class="flex items-start gap-2">
                            <span class="text-amber-600 mt-0.5">•</span>
                            <span>No incluyas encabezados en la primera fila</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-amber-600 mt-0.5">•</span>
                            <span>Los IDs de subconcesionaria y cafetería deben ser numéricos y válidos</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-amber-600 mt-0.5">•</span>
                            <span>El DNI debe tener exactamente 8 dígitos</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-amber-600 mt-0.5">•</span>
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
                        class="h-11 border-slate-300 rounded-xl cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" 
                        accept=".xlsx,.xls,.csv" 
                    />
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false" class="rounded-xl">
                    Cancelar
                </Button>
                <Button @click="submit" class="bg-emerald-600 hover:bg-emerald-700 rounded-xl">
                    <Icon name="upload" size="16" class="mr-2" />
                    Cargar Comensales
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

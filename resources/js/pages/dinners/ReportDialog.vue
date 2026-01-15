<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { FileChartColumn, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { utils, writeFile } from 'xlsx';

const open = ref(false);
const isLoading = ref(false);

const form = useForm({
    file: null,
});

const resultsReport = ref([]);
const dateInitial = ref('');
const dateFinal = ref('');

const handleFileChange = async () => {
    if (!dateInitial.value || !dateFinal.value) {
        alert('Por favor selecciona ambas fechas');
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(`/sales/report/${dateInitial.value}/${dateFinal.value}`);
        resultsReport.value = response.data;
    } catch (error) {
        console.error('Error al obtener el reporte:', error);
        alert('Error al generar el reporte');
    } finally {
        isLoading.value = false;
    }
};

const exportToExcel = () => {
    if (resultsReport.value.length === 0) {
        alert('No hay datos para exportar');
        return;
    }

    // Preparar los datos para Excel
    const dataToExport = resultsReport.value.map((item) => ({
        Sucursal: item.dinner.subdealership.name,
        Fecha: new Date(item.date + 'T00:00:00').toLocaleDateString('es-PE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            timeZone: 'America/Lima',
        }),
        DNI: item.dinner.dni,
        Nombre: item.dinner.name,
        Tipo: item.tickets[0].ticket_details[0].service_name,
        Total: `S./${parseFloat(item.total).toFixed(2)}`,
    }));

    // Crear hoja de trabajo
    const worksheet = utils.json_to_sheet(dataToExport);

    // Crear libro de trabajo
    const workbook = utils.book_new();
    utils.book_append_sheet(workbook, worksheet, 'Reporte de Ventas');

    // Generar archivo Excel
    writeFile(workbook, `Reporte_Ventas_${dateInitial.value}_a_${dateFinal.value}.xlsx`, {
        compression: true,
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button title="Generar reporte" class="gap-2 bg-green-600 hover:bg-green-700">
                <FileChartColumn class="h-4 w-4" />
                <span>Reporte de Ventas</span>
            </Button>
        </DialogTrigger>
        <DialogContent class="w-full max-w-[90vw] lg:max-w-[1200px]">
            <DialogHeader>
                <DialogTitle class="text-lg font-semibold text-gray-800">Generar Reporte de Ventas</DialogTitle>
                <p class="text-sm text-gray-500">Selecciona el rango de fechas para generar el reporte</p>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-6 p-4 md:grid-cols-3">
                <!-- Panel de controles -->
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Fecha inicial</label>
                        <Input type="date" v-model="dateInitial" class="w-full" @change="handleFileChange" />
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Fecha final</label>
                        <Input type="date" v-model="dateFinal" class="w-full" @change="handleFileChange" :min="dateInitial" />
                    </div>

                    <div class="pt-4">
                        <Button @click="handleFileChange" class="w-full bg-blue-600 hover:bg-blue-700" :disabled="isLoading">
                            <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isLoading ? 'Generando...' : 'Actualizar Vista Previa' }}
                        </Button>
                    </div>
                </div>

                <!-- Vista previa estilo Excel -->
                <div class="md:col-span-2">
                    <div class="overflow-hidden rounded-lg border shadow-sm">
                        <div class="flex items-center justify-between border-b bg-gray-100 px-4 py-2">
                            <h3 class="font-medium text-gray-700">Vista Previa del Reporte</h3>
                            <span class="text-xs text-gray-500">{{ resultsReport.length }} registros</span>
                        </div>

                        <div class="max-h-[400px] overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Sucursal</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Fecha</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">DNI</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Nombre</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Tipo</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-if="resultsReport.length === 0">
                                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">
                                            {{ isLoading ? 'Cargando datos...' : 'Selecciona fechas para generar el reporte' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(result, index) in resultsReport"
                                        :key="result.id"
                                        :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                    >
                                        <td class="px-4 py-2 text-sm whitespace-nowrap text-gray-900">
                                            {{ result.dinner.subdealership.name }}
                                        </td>

                                        <td class="px-4 py-2 text-sm whitespace-nowrap text-gray-500">
                                            {{
                                                new Date(result.date + 'T00:00:00').toLocaleDateString('es-PE', {
                                                    year: 'numeric',
                                                    month: '2-digit',
                                                    day: '2-digit',
                                                    timeZone: 'America/Lima',
                                                })
                                            }}
                                        </td>
                                        <td class="px-4 py-2 text-sm whitespace-nowrap text-gray-900">
                                            {{ result.dinner.dni }}
                                        </td>
                                        <td class="px-4 py-2 text-sm whitespace-nowrap text-gray-900">
                                            {{ result.dinner.name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm whitespace-nowrap text-gray-900">
                                            {{ result.tickets[0].ticket_details[0]?.service_name }}
                                        </td>
                                        <td class="px-4 py-2 text-sm font-medium whitespace-nowrap text-green-600">
                                            S./{{ parseFloat(result.total).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t pt-4">
                <Button type="button" variant="outline" @click="open = false" class="mr-2"> Cancelar </Button>
                <Button @click="exportToExcel" class="bg-green-600 hover:bg-green-700" :disabled="resultsReport.length === 0">
                    Exportar a Excel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileUp, Loader2, CheckCircle2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';

const props = defineProps<{
    selectedIds: number[];
}>();

const emit = defineEmits(['complete']);

const open = ref(false);

const form = useForm({
    staffIds: [] as number[],
    sctr_vida_ley: null as File | null,
    sctr_vida_ley_exp: '',
    sctr_pension_salud: null as File | null,
    sctr_pension_salud_exp: '',
    sctr_socavon: null as File | null,
    sctr_socavon_exp: '',
});

const handleFileChange = (e: Event, field: 'sctr_vida_ley' | 'sctr_pension_salud' | 'sctr_socavon') => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form[field] = target.files[0];
    }
};

const onSubmit = () => {
    form.staffIds = props.selectedIds;
    
    form.post(route('staff.mass-upload-sctr'), {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: 'Documentos cargados exitosamente',
                timer: 2000,
                showConfirmButton: false
            });
            open.value = false;
            form.reset();
            emit('complete');
        },
        onError: () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al cargar los documentos',
            });
        }
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white flex gap-2">
                <FileUp class="h-4 w-4" />
                Asignar SCTR (Masivo)
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Carga Masiva de SCTR</DialogTitle>
                <DialogDescription>
                    Se asignarán estos documentos a los {{ selectedIds.length }} colaboradores seleccionados.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 py-4">
                <div class="space-y-4 rounded-lg border p-3 bg-zinc-50/50">
                    <div class="grid gap-2">
                        <Label for="vida_ley" class="text-sm font-semibold">SCTR Vida Ley (PDF)</Label>
                        <Input id="vida_ley" type="file" accept=".pdf" @change="handleFileChange($event, 'sctr_vida_ley')" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="vida_ley_exp" class="text-xs text-muted-foreground">Fecha Expiración Vida Ley</Label>
                        <Input id="vida_ley_exp" type="date" v-model="form.sctr_vida_ley_exp" />
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border p-3 bg-zinc-50/50">
                    <div class="grid gap-2">
                        <Label for="pension_salud" class="text-sm font-semibold">SCTR Pensión y Salud (PDF)</Label>
                        <Input id="pension_salud" type="file" accept=".pdf" @change="handleFileChange($event, 'sctr_pension_salud')" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pension_salud_exp" class="text-xs text-muted-foreground">Fecha Expiración Pensión y Salud</Label>
                        <Input id="pension_salud_exp" type="date" v-model="form.sctr_pension_salud_exp" />
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border p-3 bg-zinc-50/50">
                    <div class="grid gap-2">
                        <Label for="socavon" class="text-sm font-semibold">SCTR Socavón (PDF)</Label>
                        <Input id="socavon" type="file" accept=".pdf" @change="handleFileChange($event, 'sctr_socavon')" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="socavon_exp" class="text-xs text-muted-foreground">Fecha Expiración Socavón</Label>
                        <Input id="socavon_exp" type="date" v-model="form.sctr_socavon_exp" />
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false" :disabled="form.processing">
                    Cancelar
                </Button>
                <Button @click="onSubmit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700">
                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                    Subir y Asignar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

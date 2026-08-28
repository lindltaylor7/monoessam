<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { ShoppingCart } from 'lucide-vue-next';
import { ref } from 'vue';

interface ServiceType {
    value: string;
    label: string;
}

interface Service {
    id?: number;
    code: string;
    name: string;
    description: string;
    type: string;
}

const props = defineProps({
    editingService: {
        type: Object as () => Service | null,
        default: null,
    },
    serviceTypes: {
        type: Array as () => ServiceType[],
        required: true,
    },
});

const emit = defineEmits(['save', 'update:open']);

const open = ref(false);

const form = useForm({
    code: '',
    name: '',
    description: '',
    type: '',
});

/* watch(
    () => props.editingService,
    (service) => {
        if (service) {
            setValues({
                code: service.code,
                name: service.name,
                description: service.description,
                type: service.type,
            });
        } else {
            resetForm();
        }
    },
    { immediate: true },
); */

const saveService = () => {
    form.post(route('services.store'), {
        onSuccess: () => {
            emit('fetchServices');
            form.reset();
        },
    });
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button class="bg-green-400 hover:bg-green-500">
                <ShoppingCart class="mr-2 h-4 w-4" />
                Agregar Servicio
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>
                    {{ editingService ? 'Editar Servicio' : 'Nuevo Servicio' }}
                </DialogTitle>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <label for="code" class="text-right">Código</label>
                    <Input id="code" v-model="form.code" class="col-span-3" />
                </div>

                <div class="grid grid-cols-4 items-center gap-4">
                    <label for="name" class="text-right">Nombre</label>
                    <Input id="name" v-model="form.name" class="col-span-3" />
                </div>

                <div class="grid grid-cols-4 items-center gap-4">
                    <label for="description" class="text-right">Descripción</label>
                    <Textarea id="description" v-model="form.description" class="col-span-3" rows="3" />
                </div>

                <div class="grid grid-cols-4 items-center gap-4">
                    <label for="type" class="text-right">Tipo</label>
                    <Select v-model="form.type">
                        <SelectTrigger class="col-span-3">
                            <SelectValue placeholder="Seleccione un tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in props.serviceTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false"> Cancelar </Button>
                <Button type="submit" @click="saveService"> Guardar </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

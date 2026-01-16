<script lang="ts" setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import Icon from '@/components/Icon.vue';

interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone: string | null;
    subdealership_id: number;
    cafe_id: number;
}

interface Cafe {
    id: number;
    name: string;
}

interface Subdealership {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    dinner: Dinner | null;
    cafes: Cafe[];
    subdealerships: Subdealership[];
}>();

const emit = defineEmits(['update:open', 'success']);

const form = useForm({
    name: '',
    dni: '',
    phone: '',
    subdealership_id: '',
    cafe_id: '',
});

// Accessing route globally from Ziggy
declare const route: any;

watch(() => props.open, (newVal) => {
    if (newVal) {
        if (props.dinner) {
            form.name = props.dinner.name;
            form.dni = props.dinner.dni;
            form.phone = props.dinner.phone || '';
            form.subdealership_id = props.dinner.subdealership_id.toString();
            form.cafe_id = props.dinner.cafe_id.toString();
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    if (props.dinner) {
        form.put(route('dinners.update', props.dinner.id), {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(route('dinners.store'), {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[500px] overflow-hidden p-0 gap-0 border-none shadow-2xl">
            <div class="bg-primary px-6 py-8 text-primary-foreground relative">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold">{{ dinner ? 'Editar Comensal' : 'Nuevo Comensal' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/70">
                        {{ dinner ? 'Actualiza la información del comensal.' : 'Ingresa los datos del nuevo comensal.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="absolute -bottom-6 right-6 h-12 w-12 rounded-full bg-background flex items-center justify-center shadow-lg border">
                     <Icon :name="dinner ? 'user' : 'user'" class="h-6 w-6 text-primary" />
                </div>
            </div>

            <form @submit.prevent="submit" class="px-6 pt-10 pb-6 bg-background">
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div class="grid gap-2">
                        <Label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Nombre Completo</Label>
                        <Input id="name" v-model="form.name" placeholder="Ej. Juan Pérez" :class="{'border-destructive shadow-sm': form.errors.name}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.name" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="dni" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">DNI</Label>
                            <Input id="dni" v-model="form.dni" placeholder="12345678" :class="{'border-destructive shadow-sm': form.errors.dni}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                            <p v-if="form.errors.dni" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.dni }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" placeholder="987654321" :class="{'border-destructive shadow-sm': form.errors.phone}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                            <p v-if="form.errors.phone" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="subdealership_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Subconcesionaria</Label>
                        <Select v-model="form.subdealership_id">
                            <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.subdealership_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sub in subdealerships" :key="sub.id" :value="sub.id.toString()">
                                    {{ sub.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.subdealership_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.subdealership_id }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="cafe_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Cafetería</Label>
                        <Select v-model="form.cafe_id">
                            <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.cafe_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                <SelectValue placeholder="Seleccionar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()">
                                    {{ cafe.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.cafe_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.cafe_id }}</p>
                    </div>
                </div>

                <DialogFooter class="pt-8 flex gap-2">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" class="flex-1 bg-primary hover:bg-primary/90 shadow-md">
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        {{ dinner ? 'Guardar Cambios' : 'Agregar Comensal' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #e2e8f0;
    border-radius: 20px;
}
</style>

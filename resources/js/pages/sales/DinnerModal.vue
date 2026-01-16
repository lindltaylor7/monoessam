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
            <div class="bg-gradient-to-r from-primary to-primary/80 px-8 py-10 text-primary-foreground relative overflow-hidden">
                <div class="absolute top-0 right-0 -mr-8 -mt-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -ml-8 -mb-8 h-24 w-24 rounded-full bg-black/10 blur-xl"></div>
                
                <DialogHeader class="relative z-10">
                    <DialogTitle class="text-3xl font-extrabold tracking-tight">{{ dinner ? 'Editar Comensal' : 'Nuevo Comensal' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/80 font-medium mt-1">
                        {{ dinner ? 'Actualiza los datos del perfil del comensal.' : 'Completa el formulario para registrar un nuevo comensal.' }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="absolute -bottom-6 right-8 h-14 w-14 rounded-2xl bg-white flex items-center justify-center shadow-xl border border-primary/10 rotate-3 group-hover:rotate-0 transition-transform">
                     <Icon :name="dinner ? 'user-cog' : 'user-plus'" class="h-7 w-7 text-primary" />
                </div>
            </div>

            <form @submit.prevent="submit" class="p-8 bg-white">
                <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <!-- Basic Information Section -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="h-1 w-8 bg-primary rounded-full"></div>
                            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Información Personal</span>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label for="name" class="text-xs font-bold text-slate-700 ml-1">Nombre Completo</Label>
                            <div class="relative group">
                                <Icon name="user" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-primary transition-colors" />
                                <Input id="name" v-model="form.name" placeholder="Ej. Juan Pérez" :class="{'border-destructive ring-destructive/20': form.errors.name}" class="h-11 pl-10 border-slate-200 rounded-xl focus:ring-primary/20 focus:border-primary transition-all shadow-sm" />
                            </div>
                            <p v-if="form.errors.name" class="text-[0.7rem] font-bold text-destructive mt-1 ml-1 flex items-center gap-1">
                                <Icon name="alert-circle" size="10" /> {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="dni" class="text-xs font-bold text-slate-700 ml-1">DNI</Label>
                                <div class="relative group">
                                    <Icon name="id-card" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-primary transition-colors" />
                                    <Input id="dni" v-model="form.dni" placeholder="12345678" maxlength="8" :class="{'border-destructive ring-destructive/20': form.errors.dni}" class="h-11 pl-10 border-slate-200 rounded-xl focus:ring-primary/20 focus:border-primary transition-all shadow-sm" />
                                </div>
                                <p v-if="form.errors.dni" class="text-[0.7rem] font-bold text-destructive mt-1 ml-1 flex items-center gap-1">
                                    <Icon name="alert-circle" size="10" /> {{ form.errors.dni }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="phone" class="text-xs font-bold text-slate-700 ml-1">Teléfono</Label>
                                <div class="relative group">
                                    <Icon name="phone" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-primary transition-colors" />
                                    <Input id="phone" v-model="form.phone" placeholder="987654321" :class="{'border-destructive ring-destructive/20': form.errors.phone}" class="h-11 pl-10 border-slate-200 rounded-xl focus:ring-primary/20 focus:border-primary transition-all shadow-sm" />
                                </div>
                                <p v-if="form.errors.phone" class="text-[0.7rem] font-bold text-destructive mt-1 ml-1 flex items-center gap-1">
                                    <Icon name="alert-circle" size="10" /> {{ form.errors.phone }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Section -->
                    <div class="space-y-4 pt-2">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="h-1 w-8 bg-primary rounded-full"></div>
                            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Asignaciones</span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="subdealership_id" class="text-xs font-bold text-slate-700 ml-1">Subconcesionaria</Label>
                            <Select v-model="form.subdealership_id">
                                <SelectTrigger :class="{'border-destructive ring-destructive/20': form.errors.subdealership_id}" class="h-11 border-slate-200 rounded-xl focus:ring-primary/20 focus:border-primary transition-all shadow-sm">
                                    <div class="flex items-center gap-2">
                                        <Icon name="building" class="h-4 w-4 text-slate-400" />
                                        <SelectValue placeholder="Seleccionar subconcesionaria" />
                                    </div>
                                </SelectTrigger>
                                <SelectContent class="rounded-xl shadow-xl border-slate-100">
                                    <SelectItem v-for="sub in subdealerships" :key="sub.id" :value="sub.id.toString()" class="rounded-lg my-1 mx-1 focus:bg-primary/10 focus:text-primary transition-colors">
                                        {{ sub.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.subdealership_id" class="text-[0.7rem] font-bold text-destructive mt-1 ml-1 flex items-center gap-1">
                                <Icon name="alert-circle" size="10" /> {{ form.errors.subdealership_id }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="cafe_id" class="text-xs font-bold text-slate-700 ml-1">Cafetería</Label>
                            <Select v-model="form.cafe_id">
                                <SelectTrigger :class="{'border-destructive ring-destructive/20': form.errors.cafe_id}" class="h-11 border-slate-200 rounded-xl focus:ring-primary/20 focus:border-primary transition-all shadow-sm">
                                    <div class="flex items-center gap-2">
                                        <Icon name="coffee" class="h-4 w-4 text-slate-400" />
                                        <SelectValue placeholder="Seleccionar cafetería" />
                                    </div>
                                </SelectTrigger>
                                <SelectContent class="rounded-xl shadow-xl border-slate-100">
                                    <SelectItem v-for="cafe in cafes" :key="cafe.id" :value="cafe.id.toString()" class="rounded-lg my-1 mx-1 focus:bg-primary/10 focus:text-primary transition-colors">
                                        {{ cafe.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.cafe_id" class="text-[0.7rem] font-bold text-destructive mt-1 ml-1 flex items-center gap-1">
                                <Icon name="alert-circle" size="10" /> {{ form.errors.cafe_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-8 flex flex-col-reverse sm:flex-row gap-3">
                    <Button type="button" variant="outline" @click="$emit('update:open', false)" class="h-11 rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50 font-bold flex-1 transition-all">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="h-11 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-bold shadow-lg shadow-primary/20 flex-[2] transition-all active:scale-95 disabled:opacity-70">
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        <Icon v-else name="check-circle" class="mr-2 h-4 w-4" />
                        {{ dinner ? 'Actualizar Información' : 'Crear Comensal' }}
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

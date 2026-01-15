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
import { Checkbox } from '@/components/ui/checkbox';
import Icon from '@/components/Icon.vue';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string }[];
    areas: { id: number; name: string; pivot?: { role_id: number; area_id: number } }[];
    units: { id: number; name: string }[];
}

interface Role {
    id: number;
    name: string;
}

interface Area {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    user: User | null;
    roles: Role[];
    areas: Area[];
    units: Unit[];
}>();

const emit = defineEmits(['update:open', 'success']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    area_id: '',
    unit_ids: [] as number[],
});

// Accessing route globally from Ziggy
declare const route: any;

watch(() => props.open, (newVal) => {
    if (newVal) {
        if (props.user) {
            form.name = props.user.name;
            form.email = props.user.email;
            form.password = '';
            form.role_id = props.user.roles[0]?.id.toString() || '';
            form.area_id = props.user.areas[0]?.id.toString() || '';
            form.unit_ids = props.user.units.map(u => u.id);
        } else {
            form.reset();
        }
        form.clearErrors();
    }
});

const submit = () => {
    if (props.user) {
        form.put(route('users.update', props.user.id), {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    }
};

const toggleUnit = (unitId: number) => {
    console.log('calling unit')
    const isChecked = form.unit_ids.includes(unitId);
    
    if (isChecked) {
        // Desmarcar: filtrar el ID del array
        form.unit_ids = form.unit_ids.filter(id => id !== unitId);
    } else {
        // Marcar: agregar el ID al array
        form.unit_ids = [...form.unit_ids, unitId];
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[500px] overflow-hidden p-0 gap-0 border-none shadow-2xl">
            <div class="bg-primary px-6 py-8 text-primary-foreground relative">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold">{{ user ? 'Editar Usuario' : 'Crear Usuario' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/70">
                        {{ user ? 'Actualiza la información y permisos del usuario.' : 'Ingresa los datos del nuevo usuario para el sistema.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="absolute -bottom-6 right-6 h-12 w-12 rounded-full bg-background flex items-center justify-center shadow-lg border">
                     <Icon :name="user ? 'user-cog' : 'user-plus'" class="h-6 w-6 text-primary" />
                </div>
            </div>

            <form @submit.prevent="submit" class="px-6 pt-10 pb-6 bg-background">
                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div class="grid gap-2">
                        <Label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Nombre Completo</Label>
                        <Input id="name" v-model="form.name" placeholder="Ej. Juan Pérez" :class="{'border-destructive shadow-sm': form.errors.name}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.name" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Correo Electrónico</Label>
                        <Input id="email" type="email" v-model="form.email" placeholder="juan@empresa.com" :class="{'border-destructive shadow-sm': form.errors.email}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.email" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.email }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Contraseña 
                            <span v-if="user" class="text-muted-foreground/60 font-normal lowercase">(Dejar en blanco para no cambiar)</span>
                        </Label>
                        <Input id="password" type="password" v-model="form.password" :class="{'border-destructive shadow-sm': form.errors.password}" class="h-10 border-muted-foreground/20 focus-visible:ring-primary" />
                        <p v-if="form.errors.password" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.password }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="role_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Rol Asignado</Label>
                            <Select v-model="form.role_id">
                                <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.role_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.role_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.role_id }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="area_id" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Área Principal</Label>
                            <Select v-model="form.area_id">
                                <SelectTrigger :class="{'border-destructive shadow-sm': form.errors.area_id}" class="h-10 border-muted-foreground/20 focus:ring-primary">
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="area in areas" :key="area.id" :value="area.id.toString()">
                                        {{ area.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.area_id" class="text-[0.7rem] font-semibold text-destructive uppercase tracking-tight">{{ form.errors.area_id }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3 pt-2">
                        <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Unidades de Acceso</Label>
                        <div class="grid grid-cols-2 gap-3 p-3 border rounded-lg bg-muted/5">
                            <div v-for="unit in units" :key="unit.id" class="flex items-center space-x-2">
                                <Checkbox 
                                    :id="'unit-' + unit.id" 
                                    @click="toggleUnit(unit.id)"
                                />
                                <label 
                                    :for="'unit-' + unit.id" 
                                    class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                >
                                    {{ unit.name }}
                                </label>
                            </div>
                        </div>
                        <p v-if="units.length === 0" class="text-xs italic text-muted-foreground">No hay unidades disponibles.</p>
                    </div>
                </div>

                <DialogFooter class="pt-8 flex gap-2">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" class="flex-1 bg-primary hover:bg-primary/90 shadow-md">
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        {{ user ? 'Guardar Cambios' : 'Crear Usuario' }}
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

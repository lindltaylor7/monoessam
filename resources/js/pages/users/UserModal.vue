<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string }[];
    areas: { id: number; name: string; pivot?: { role_id: number; area_id: number } }[];
    units: { id: number; name: string }[];
    mine: Mine | null;
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
    mine_id: number | null;
}

interface Mine {
    id: number;
    name: string;
}

interface Business {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    user: User | null;
    roles: Role[];
    areas: Area[];
    units: Unit[];
    mines: Mine[];
    businesses: Business[];
}>();

const emit = defineEmits(['update:open', 'success']);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    area_id: '',
    mine_id: '',
    business_id: '',
    unit_ids: [] as number[],
});

// Accessing route globally from Ziggy
declare const route: any;

watch(
    () => props.open,
    (newVal) => {
        if (newVal) {
            if (props.user) {
                form.name = props.user.name;
                form.email = props.user.email;
                form.password = '';
                form.role_id = props.user.roles[0]?.id.toString() || '';
                form.area_id = props.user.areas[0]?.id.toString() || '';
                form.mine_id = props.user.mine?.id.toString() || '';
                form.business_id = (props.user as any).business_id?.toString() || '';
                form.unit_ids = props.user.units.map((u) => u.id);
            } else {
                form.reset();
            }
            form.clearErrors();
        }
    },
);

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

const filteredUnits = computed(() => {
    if (!form.mine_id) return props.units;
    return props.units.filter((u) => u.mine_id === Number(form.mine_id));
});

watch(
    () => form.mine_id,
    () => {
        const validIds = filteredUnits.value.map((u) => u.id);
        form.unit_ids = form.unit_ids.filter((id) => validIds.includes(id));
    },
);

const toggleUnit = (unitId: number) => {
    console.log('calling unit');
    const isChecked = form.unit_ids.includes(unitId);

    if (isChecked) {
        // Desmarcar: filtrar el ID del array
        form.unit_ids = form.unit_ids.filter((id) => id !== unitId);
    } else {
        // Marcar: agregar el ID al array
        form.unit_ids = [...form.unit_ids, unitId];
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[500px]">
            <div class="bg-primary text-primary-foreground relative px-6 py-8">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold">{{ user ? 'Editar Usuario' : 'Crear Usuario' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/70">
                        {{ user ? 'Actualiza la información y permisos del usuario.' : 'Ingresa los datos del nuevo usuario para el sistema.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="bg-background absolute right-6 -bottom-6 flex h-12 w-12 items-center justify-center rounded-full border shadow-lg">
                    <Icon :name="user ? 'user-cog' : 'user-plus'" class="text-primary h-6 w-6" />
                </div>
            </div>

            <form @submit.prevent="submit" class="bg-background px-6 pt-10 pb-6">
                <div class="custom-scrollbar max-h-[60vh] space-y-4 overflow-y-auto pr-2">
                    <div class="grid gap-2">
                        <Label for="name" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Nombre Completo</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="Ej. Juan Pérez"
                            :class="{ 'border-destructive shadow-sm': form.errors.name }"
                            class="border-muted-foreground/20 focus-visible:ring-primary h-10"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Correo Electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="juan@empresa.com"
                            :class="{ 'border-destructive shadow-sm': form.errors.email }"
                            class="border-muted-foreground/20 focus-visible:ring-primary h-10"
                        />
                        <p v-if="form.errors.email" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                            Contraseña
                            <span v-if="user" class="text-muted-foreground/60 font-normal lowercase">(Dejar en blanco para no cambiar)</span>
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            v-model="form.password"
                            :class="{ 'border-destructive shadow-sm': form.errors.password }"
                            class="border-muted-foreground/20 focus-visible:ring-primary h-10"
                        />
                        <p v-if="form.errors.password" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="role_id" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Rol Asignado</Label>
                            <Select v-model="form.role_id">
                                <SelectTrigger
                                    :class="{ 'border-destructive shadow-sm': form.errors.role_id }"
                                    class="border-muted-foreground/20 focus:ring-primary h-10"
                                >
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.role_id" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                                {{ form.errors.role_id }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="area_id" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Área Principal</Label>
                            <Select v-model="form.area_id">
                                <SelectTrigger
                                    :class="{ 'border-destructive shadow-sm': form.errors.area_id }"
                                    class="border-muted-foreground/20 focus:ring-primary h-10"
                                >
                                    <SelectValue placeholder="Seleccionar" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="area in areas" :key="area.id" :value="area.id.toString()">
                                        {{ area.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.area_id" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                                {{ form.errors.area_id }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="mine_id" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Mina Asignada</Label>
                            <Select v-model="form.mine_id">
                                <SelectTrigger
                                    :class="{ 'border-destructive shadow-sm': form.errors.mine_id }"
                                    class="border-muted-foreground/20 focus:ring-primary h-10"
                                >
                                    <SelectValue placeholder="Seleccionar mina" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">
                                        {{ mine.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.mine_id" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                                {{ form.errors.mine_id }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business_id" class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Empresa</Label>
                            <Select v-model="form.business_id">
                                <SelectTrigger
                                    :class="{ 'border-destructive shadow-sm': form.errors.business_id }"
                                    class="border-muted-foreground/20 focus:ring-primary h-10"
                                >
                                    <SelectValue placeholder="Seleccionar empresa" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="business in businesses" :key="business.id" :value="business.id.toString()">
                                        {{ business.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.business_id" class="text-destructive text-[0.7rem] font-semibold tracking-tight uppercase">
                                {{ form.errors.business_id }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 pt-2">
                        <Label class="text-muted-foreground text-xs font-bold tracking-wider uppercase">Unidades de Acceso</Label>
                        <div class="bg-muted/5 grid grid-cols-2 gap-3 rounded-lg border p-3">
                            <div v-for="unit in filteredUnits" :key="unit.id" class="flex items-center space-x-2">
                                <Checkbox :id="'unit-' + unit.id" :checked="form.unit_ids.includes(unit.id)" @click="toggleUnit(unit.id)" />
                                <label
                                    :for="'unit-' + unit.id"
                                    class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    {{ unit.name }}
                                </label>
                            </div>
                        </div>
                        <p v-if="filteredUnits.length === 0" class="text-muted-foreground text-xs italic">
                            {{ form.mine_id ? 'No hay unidades para esta mina.' : 'Selecciona una mina para ver sus unidades.' }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="flex gap-2 pt-8">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/90 flex-1 shadow-md">
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

<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
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
        <DialogContent class="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[580px]">
            <!-- Header -->
            <div class="bg-primary text-primary-foreground relative px-6 py-7">
                <DialogHeader>
                    <DialogTitle class="text-xl font-bold">{{ user ? 'Editar Usuario' : 'Crear Usuario' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/70 text-sm">
                        {{ user ? 'Actualiza la información y permisos del usuario.' : 'Ingresa los datos del nuevo usuario.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="bg-background absolute right-6 -bottom-5 flex h-10 w-10 items-center justify-center rounded-full border shadow-md">
                    <Icon :name="user ? 'user-cog' : 'user-plus'" class="text-primary h-5 w-5" />
                </div>
            </div>

            <form @submit.prevent="submit" class="bg-background px-6 pt-8 pb-5">
                <div class="custom-scrollbar max-h-[65vh] space-y-5 overflow-y-auto pr-1">

                    <!-- Datos personales -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Datos personales</p>

                        <div class="grid gap-1.5">
                            <Label for="name" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Nombre completo</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Ej. Juan Pérez"
                                :class="{ 'border-destructive': form.errors.name }"
                                class="border-slate-200 focus-visible:ring-primary h-10 text-sm"
                            />
                            <p v-if="form.errors.name" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="email" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Correo electrónico</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    placeholder="juan@empresa.com"
                                    :class="{ 'border-destructive': form.errors.email }"
                                    class="border-slate-200 focus-visible:ring-primary h-10 text-sm"
                                />
                                <p v-if="form.errors.email" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.email }}</p>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="password" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">
                                    Contraseña
                                    <span v-if="user" class="text-muted-foreground/50 ml-1 font-normal normal-case">(opcional)</span>
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    v-model="form.password"
                                    :class="{ 'border-destructive': form.errors.password }"
                                    class="border-slate-200 focus-visible:ring-primary h-10 text-sm"
                                />
                                <p v-if="form.errors.password" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.password }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100"></div>

                    <!-- Rol y área -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Permisos y acceso</p>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="role_id" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Rol asignado</Label>
                                <Select v-model="form.role_id">
                                    <SelectTrigger :class="{ 'border-destructive': form.errors.role_id }" class="border-slate-200 focus:ring-primary h-10 text-sm">
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">{{ role.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.role_id" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.role_id }}</p>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="area_id" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Área principal</Label>
                                <Select v-model="form.area_id">
                                    <SelectTrigger :class="{ 'border-destructive': form.errors.area_id }" class="border-slate-200 focus:ring-primary h-10 text-sm">
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="area in areas" :key="area.id" :value="area.id.toString()">{{ area.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.area_id" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.area_id }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="mine_id" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Mina asignada</Label>
                                <Select v-model="form.mine_id">
                                    <SelectTrigger :class="{ 'border-destructive': form.errors.mine_id }" class="border-slate-200 focus:ring-primary h-10 text-sm">
                                        <SelectValue placeholder="Seleccionar mina" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="mine in mines" :key="mine.id" :value="mine.id.toString()">{{ mine.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.mine_id" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.mine_id }}</p>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="business_id" class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Empresa</Label>
                                <Select v-model="form.business_id">
                                    <SelectTrigger :class="{ 'border-destructive': form.errors.business_id }" class="border-slate-200 focus:ring-primary h-10 text-sm">
                                        <SelectValue placeholder="Seleccionar empresa" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="business in businesses" :key="business.id" :value="business.id.toString()">{{ business.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.business_id" class="text-destructive text-[0.65rem] font-semibold tracking-tight uppercase">{{ form.errors.business_id }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100"></div>

                    <!-- Unidades de acceso -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Unidades de acceso</p>
                            <span
                                v-if="form.unit_ids.length > 0"
                                class="bg-primary/10 text-primary rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                            >
                                {{ form.unit_ids.length }} seleccionada{{ form.unit_ids.length !== 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div v-if="filteredUnits.length > 0" class="custom-scrollbar max-h-[150px] overflow-y-auto rounded-xl border border-slate-200 p-2">
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="unit in filteredUnits"
                                    :key="unit.id"
                                    type="button"
                                    @click="toggleUnit(unit.id)"
                                    class="group flex items-center justify-between rounded-lg border px-3 py-2.5 text-left text-xs font-bold transition-all"
                                    :class="
                                        form.unit_ids.includes(unit.id)
                                            ? 'border-primary bg-primary text-white shadow-sm'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-primary/40 hover:bg-primary/5'
                                    "
                                >
                                    <span class="truncate">{{ unit.name }}</span>
                                    <Icon
                                        :name="form.unit_ids.includes(unit.id) ? 'check-circle' : 'circle'"
                                        size="14"
                                        class="ml-1 shrink-0 transition-opacity"
                                        :class="form.unit_ids.includes(unit.id) ? 'opacity-100' : 'opacity-30 group-hover:opacity-60'"
                                    />
                                </button>
                            </div>
                        </div>

                        <div v-else class="flex items-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-4">
                            <Icon name="info" size="14" class="shrink-0 text-slate-300" />
                            <p class="text-xs text-slate-400 italic">
                                {{ form.mine_id ? 'No hay unidades disponibles para esta mina.' : 'Selecciona una mina para ver sus unidades.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border border-slate-200">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/90 flex-1 shadow-sm">
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

<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';

interface Dinner {
    id: number;
    name: string;
    dni: string;
    phone: string | null;
    subdealership_id: number;
}

interface Subdealership {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    dinner: Dinner | null;
    subdealerships: Subdealership[];
}>();

const emit = defineEmits(['update:open', 'success']);

const form = useForm({
    name: '',
    dni: '',
    phone: '',
    subdealership_id: '',
});

// Accessing route globally from Ziggy
declare const route: any;

// DNI real-time uniqueness check
const dniChecking = ref(false);
const dniTaken = ref(false);
let dniTimeout: ReturnType<typeof setTimeout> | null = null;

const onDniInput = () => {
    dniTaken.value = false;
    form.clearErrors('dni');
    if (dniTimeout) clearTimeout(dniTimeout);
    const val = form.dni.trim();
    if (val.length !== 8) return;
    dniTimeout = setTimeout(async () => {
        dniChecking.value = true;
        try {
            const params: Record<string, string> = { dni: val };
            if (props.dinner) params.exclude_id = props.dinner.id.toString();
            const res = await axios.get(route('dinners.check-dni'), { params });
            dniTaken.value = res.data.exists;
        } finally {
            dniChecking.value = false;
        }
    }, 350);
};

// Subdealership combobox state
const subSearch = ref('');
const subResults = ref<Subdealership[]>([]);
const subDropdownOpen = ref(false);
const subSearching = ref(false);
let subTimeout: ReturnType<typeof setTimeout> | null = null;

const onSubInput = () => {
    form.subdealership_id = '';
    if (subTimeout) clearTimeout(subTimeout);
    const q = subSearch.value.trim();
    if (!q) {
        subResults.value = [];
        subDropdownOpen.value = false;
        return;
    }
    subTimeout = setTimeout(async () => {
        subSearching.value = true;
        try {
            const res = await axios.get(route('subdealerships.search'), { params: { q } });
            subResults.value = res.data;
            subDropdownOpen.value = true;
        } finally {
            subSearching.value = false;
        }
    }, 300);
};

const selectSub = (sub: Subdealership) => {
    form.subdealership_id = sub.id.toString();
    subSearch.value = sub.name;
    subDropdownOpen.value = false;
    subResults.value = [];
};

const onSubBlur = () => {
    setTimeout(() => { subDropdownOpen.value = false; }, 150);
};

watch(
    () => props.open,
    (newVal) => {
        if (newVal) {
            dniTaken.value = false;
            dniChecking.value = false;
            if (dniTimeout) clearTimeout(dniTimeout);
            if (props.dinner) {
                form.name = props.dinner.name;
                form.dni = props.dinner.dni;
                form.phone = props.dinner.phone || '';
                form.subdealership_id = props.dinner.subdealership_id.toString();
                const sub = props.subdealerships.find((s) => s.id === props.dinner!.subdealership_id);
                subSearch.value = sub?.name ?? '';
            } else {
                form.reset();
                subSearch.value = '';
            }
            subResults.value = [];
            subDropdownOpen.value = false;
            form.clearErrors();
        }
    },
);

const submit = () => {
    if (props.dinner) {
        form.put(route('dinners.update', props.dinner.id), {
            onSuccess: () => {
                emit('update:open', false);
                emit('success');
            },
        });
    } else {
        form.post(route('dinners.save'), {
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
        <DialogContent class="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-[600px]">
            <div class="from-primary to-primary/80 text-primary-foreground relative overflow-hidden bg-gradient-to-r px-8 py-10">
                <div class="absolute top-0 right-0 -mt-8 -mr-8 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 -mb-8 -ml-8 h-24 w-24 rounded-full bg-black/10 blur-xl"></div>

                <DialogHeader class="relative z-10">
                    <DialogTitle class="text-3xl font-extrabold tracking-tight">{{ dinner ? 'Editar Comensal' : 'Nuevo Comensal' }}</DialogTitle>
                    <DialogDescription class="text-primary-foreground/80 mt-1 font-medium">
                        {{ dinner ? 'Actualiza los datos del perfil del comensal.' : 'Completa el formulario para registrar un nuevo comensal.' }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="border-primary/10 absolute right-8 -bottom-6 flex h-14 w-14 rotate-3 items-center justify-center rounded-2xl border bg-white shadow-xl transition-transform group-hover:rotate-0"
                >
                    <Icon :name="dinner ? 'user-cog' : 'user-plus'" class="text-primary h-7 w-7" />
                </div>
            </div>

            <form @submit.prevent="submit" class="bg-white p-8">
                <div class="space-y-6">
                    <!-- Basic Information Section -->
                    <div class="space-y-4">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="bg-primary h-1 w-8 rounded-full"></div>
                            <span class="text-[10px] font-bold tracking-[0.2em] text-slate-400 uppercase">Información Personal</span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="name" class="ml-1 text-xs font-bold text-slate-700">Nombre Completo</Label>
                            <div class="group relative">
                                <Icon
                                    name="user"
                                    class="group-focus-within:text-primary absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors"
                                />
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Ej. Juan Pérez"
                                    :class="{ 'border-destructive ring-destructive/20': form.errors.name }"
                                    class="focus:ring-primary/20 focus:border-primary h-11 rounded-xl border-slate-200 pl-10 shadow-sm transition-all"
                                />
                            </div>
                            <p v-if="form.errors.name" class="text-destructive mt-1 ml-1 flex items-center gap-1 text-[0.7rem] font-bold">
                                <Icon name="alert-circle" size="10" /> {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="dni" class="ml-1 text-xs font-bold text-slate-700">DNI</Label>
                                <div class="group relative">
                                    <Icon
                                        name="id-card"
                                        class="group-focus-within:text-primary absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors"
                                    />
                                    <Input
                                        id="dni"
                                        v-model="form.dni"
                                        placeholder="12345678"
                                        maxlength="8"
                                        @input="onDniInput"
                                        :class="{
                                            'border-destructive ring-destructive/20': form.errors.dni || dniTaken,
                                            'border-emerald-400 ring-emerald-100': !dniTaken && !form.errors.dni && form.dni.length === 8 && !dniChecking,
                                        }"
                                        class="focus:ring-primary/20 focus:border-primary h-11 rounded-xl border-slate-200 pl-10 pr-10 shadow-sm transition-all"
                                    />
                                    <Icon
                                        v-if="dniChecking"
                                        name="loader-2"
                                        class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-slate-400"
                                    />
                                    <Icon
                                        v-else-if="dniTaken"
                                        name="x-circle"
                                        class="text-destructive absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2"
                                    />
                                    <Icon
                                        v-else-if="!form.errors.dni && form.dni.length === 8"
                                        name="check-circle"
                                        class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-emerald-500"
                                    />
                                </div>
                                <p v-if="dniTaken" class="text-destructive mt-1 ml-1 flex items-center gap-1 text-[0.7rem] font-bold">
                                    <Icon name="alert-circle" size="10" /> Este DNI ya está registrado.
                                </p>
                                <p v-else-if="form.errors.dni" class="text-destructive mt-1 ml-1 flex items-center gap-1 text-[0.7rem] font-bold">
                                    <Icon name="alert-circle" size="10" /> {{ form.errors.dni }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="phone" class="ml-1 text-xs font-bold text-slate-700">Teléfono</Label>
                                <div class="group relative">
                                    <Icon
                                        name="phone"
                                        class="group-focus-within:text-primary absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors"
                                    />
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        placeholder="987654321"
                                        :class="{ 'border-destructive ring-destructive/20': form.errors.phone }"
                                        class="focus:ring-primary/20 focus:border-primary h-11 rounded-xl border-slate-200 pl-10 shadow-sm transition-all"
                                    />
                                </div>
                                <p v-if="form.errors.phone" class="text-destructive mt-1 ml-1 flex items-center gap-1 text-[0.7rem] font-bold">
                                    <Icon name="alert-circle" size="10" /> {{ form.errors.phone }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Section -->
                    <div class="space-y-4 pt-2">
                        <div class="mb-2 flex items-center gap-2">
                            <div class="bg-primary h-1 w-8 rounded-full"></div>
                            <span class="text-[10px] font-bold tracking-[0.2em] text-slate-400 uppercase">Asignaciones</span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="subdealership_search" class="ml-1 text-xs font-bold text-slate-700">Subconcesionaria</Label>
                            <div class="relative">
                                <div class="group relative">
                                    <Icon
                                        name="building"
                                        class="group-focus-within:text-primary absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400 transition-colors"
                                    />
                                    <Icon
                                        v-if="subSearching"
                                        name="loader-2"
                                        class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-slate-400"
                                    />
                                    <Input
                                        id="subdealership_search"
                                        v-model="subSearch"
                                        @input="onSubInput"
                                        @blur="onSubBlur"
                                        @focus="subDropdownOpen = subResults.length > 0"
                                        placeholder="Buscar subconcesionaria..."
                                        :class="{ 'border-destructive ring-destructive/20': form.errors.subdealership_id }"
                                        class="focus:ring-primary/20 focus:border-primary h-11 rounded-xl border-slate-200 pl-10 shadow-sm transition-all"
                                        autocomplete="off"
                                    />
                                </div>
                                <div
                                    v-if="subDropdownOpen && subResults.length > 0"
                                    class="absolute z-50 mt-1 w-full rounded-xl border border-slate-100 bg-white shadow-xl"
                                >
                                    <div class="max-h-48 overflow-y-auto p-1">
                                        <button
                                            v-for="sub in subResults"
                                            :key="sub.id"
                                            type="button"
                                            @mousedown.prevent="selectSub(sub)"
                                            class="hover:bg-primary/10 hover:text-primary flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 transition-colors"
                                        >
                                            <Icon name="building" class="h-4 w-4 flex-shrink-0 text-slate-400" />
                                            {{ sub.name }}
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-else-if="subDropdownOpen && !subSearching && subSearch.trim()"
                                    class="absolute z-50 mt-1 w-full rounded-xl border border-slate-100 bg-white p-3 text-center text-sm text-slate-500 shadow-xl"
                                >
                                    No se encontraron resultados
                                </div>
                            </div>
                            <p v-if="form.errors.subdealership_id" class="text-destructive mt-1 ml-1 flex items-center gap-1 text-[0.7rem] font-bold">
                                <Icon name="alert-circle" size="10" /> {{ form.errors.subdealership_id }}
                            </p>
                        </div>

                    </div>
                </div>

                <DialogFooter class="flex flex-col-reverse gap-3 pt-8 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        @click="$emit('update:open', false)"
                        class="h-11 flex-1 rounded-xl border-slate-200 font-bold text-slate-600 transition-all hover:bg-slate-50"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || dniTaken || dniChecking"
                        class="bg-primary hover:bg-primary/90 text-primary-foreground shadow-primary/20 h-11 flex-[2] rounded-xl font-bold shadow-lg transition-all active:scale-95 disabled:opacity-70"
                    >
                        <Icon v-if="form.processing || dniChecking" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        <Icon v-else name="check-circle" class="mr-2 h-4 w-4" />
                        {{ dinner ? 'Actualizar Información' : 'Crear Comensal' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>


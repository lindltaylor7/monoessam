<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, ShieldCheck, Settings2, UserCircle, CheckCircle2 } from 'lucide-vue-next';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter 
} from '@/components/ui/dialog';

const props = defineProps<{
    cafe: any;
    roles: any[];
    open: boolean;
}>();

const emit = defineEmits(['update:open']);

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

const roleSearchQuery = ref('');
const selectedRoleIds = ref<number[]>([]);

// Sincronizar roles seleccionados con el café cuando se abre el modal
watch(() => props.open, (newVal) => {
    if (newVal && props.cafe) {
        selectedRoleIds.value = props.cafe.roles ? props.cafe.roles.map((r: any) => r.id) : [];
        roleSearchQuery.value = '';
    }
});

const filteredRoles = computed(() => {
    if (!roleSearchQuery.value) return props.roles;
    return props.roles.filter(role => 
        role.name.toLowerCase().includes(roleSearchQuery.value.toLowerCase())
    );
});

const toggleRole = (roleId: number) => {
    const index = selectedRoleIds.value.indexOf(roleId);
    if (index === -1) {
        selectedRoleIds.value.push(roleId);
    } else {
        selectedRoleIds.value.splice(index, 1);
    }
};

const handleSave = () => {
    if (!props.cafe) return;

    router.post(route('cafes.roles.sync', props.cafe.id), {
        role_ids: selectedRoleIds.value
    }, {
        onSuccess: () => {
            isOpen.value = false;
        },
        preserveScroll: true
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-[500px] p-0 overflow-hidden border-none rounded-3xl shadow-2xl">
            <DialogHeader class="p-8 bg-slate-900 text-white">
                <DialogTitle class="text-2xl font-black flex items-center gap-3">
                    <ShieldCheck class="h-6 w-6 text-amber-500" />
                    Gestionar Roles
                </DialogTitle>
                <DialogDescription class="text-slate-400 font-medium">
                    Configura qué roles están habilitados para: 
                    <span class="text-white font-black underline decoration-amber-500 underline-offset-4">
                        {{ cafe?.name }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="p-8 pb-0">
                <div class="relative">
                    <Search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <Input 
                        v-model="roleSearchQuery"
                        placeholder="Buscar rol..." 
                        class="pl-10 h-10 bg-slate-50 border-slate-100 rounded-xl focus:ring-amber-500"
                    />
                </div>
            </div>

            <div class="p-8 space-y-4 max-h-[50vh] overflow-y-auto custom-scrollbar">
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-4">Roles Disponibles en el Sistema</p>
                
                <div class="grid grid-cols-1 gap-2">
                    <button 
                        v-for="role in filteredRoles" 
                        :key="role.id"
                        @click="toggleRole(role.id)"
                        class="w-full text-left p-4 rounded-2xl border transition-all duration-200 flex items-center justify-between group"
                        :class="selectedRoleIds.includes(role.id) 
                            ? 'bg-indigo-50 border-indigo-200 ring-2 ring-indigo-600/10' 
                            : 'bg-white border-slate-100 hover:border-slate-300'"
                    >
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-xl flex items-center justify-center transition-colors"
                                :class="selectedRoleIds.includes(role.id) ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-400 group-hover:bg-slate-100'">
                                <UserCircle class="h-5 w-5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight" :class="selectedRoleIds.includes(role.id) ? 'text-indigo-900' : 'text-slate-600'">
                                {{ role.name }}
                            </span>
                        </div>
                        
                        <CheckCircle2 
                            class="h-5 w-5 transition-all duration-300"
                            :class="selectedRoleIds.includes(role.id) ? 'text-indigo-600 scale-110 opacity-100' : 'text-slate-100 opacity-0 group-hover:opacity-100'"
                        />
                    </button>
                </div>
                
                <div v-if="filteredRoles.length === 0" class="py-12 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100">
                    <UserCircle class="h-10 w-10 mx-auto text-slate-200 mb-2" />
                    <p class="text-slate-400 text-xs font-bold">No se encontraron roles con ese nombre.</p>
                </div>
            </div>

            <DialogFooter class="p-8 bg-slate-50 border-t border-slate-100 flex flex-row gap-2 sm:justify-between items-center">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    {{ selectedRoleIds.length }} seleccionados
                </p>
                <div class="flex gap-3">
                    <Button variant="ghost" @click="isOpen = false" class="rounded-xl font-bold uppercase text-[10px] tracking-widest text-slate-500 hover:bg-slate-100">
                        Cancelar
                    </Button>
                    <Button @click="handleSave" class="rounded-xl bg-slate-900 hover:bg-black text-white px-8 font-black uppercase text-[10px] tracking-widest shadow-lg shadow-slate-200">
                        Guardar Cambios
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>

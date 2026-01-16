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
import { Checkbox } from '@/components/ui/checkbox';
import Icon from '@/components/Icon.vue';
import { Shield } from 'lucide-vue-next';

interface Permission {
    id: number;
    name: string;
    sidebar_name?: string | null;
}

interface User {
    id: number;
    name: string;
    permissions: Permission[];
    roles: {
        id: number;
        name: string;
        permissions: Permission[];
    }[];
}

const props = defineProps<{
    open: boolean;
    user: User | null;
    permissions: Permission[];
}>();

const emit = defineEmits(['update:open']);

const form = useForm({
    permissions: [] as number[],
    user_id: null as number | null,
});

// Accessing route globally from Ziggy
declare const route: any;

watch(() => props.open, (newVal) => {
    if (newVal && props.user) {
        form.user_id = props.user.id;
        
        // Obtener IDs de permisos directos
        const directPermissionIds = props.user.permissions.map(p => p.id);
        
        // Obtener IDs de permisos de todos los roles
        const rolePermissionIds = props.user.roles.flatMap(role => 
            role.permissions.map(p => p.id)
        );
        
        // Unir y eliminar duplicados usando un Set
        form.permissions = [...new Set([...directPermissionIds, ...rolePermissionIds])];
    }
});

const isSelected = (id: number) => form.permissions.includes(id);

const togglePermission = (id: number) => {
    const permissions = [...form.permissions];
    const index = permissions.indexOf(id);
    if (index > -1) {
        permissions.splice(index, 1);
    } else {
        permissions.push(id);
    }
    form.permissions = permissions;
};

const submit = () => {
    if (!props.user) return;
    
    form.post(route('permissions.user.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[700px] overflow-hidden p-0 gap-0 border-none shadow-2xl">
            <div class="bg-orange-600 px-6 py-8 text-white relative">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-bold">Permisos de Usuario</DialogTitle>
                    <DialogDescription class="text-orange-100">
                        Gestiona los permisos específicos para <span class="font-bold text-white">{{ user?.name }}</span>.
                    </DialogDescription>
                </DialogHeader>
                <div class="absolute -bottom-6 right-6 h-12 w-12 rounded-full bg-background flex items-center justify-center shadow-lg border">
                     <Shield class="h-6 w-6 text-orange-600" />
                </div>
            </div>

            <div class="px-6 pt-10 pb-6 bg-background">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-muted-foreground">Listado de Permisos</h3>
                    <div class="text-xs text-muted-foreground">
                        <span class="font-bold text-orange-600">{{ form.permissions.length }}</span> seleccionados
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar border rounded-xl p-4 bg-muted/5">
                    <div 
                        v-for="permission in permissions" 
                        :key="permission.id"
                        class="flex items-center space-x-3 p-2 rounded-lg transition-colors hover:bg-muted/10 border border-transparent"
                        :class="{'bg-orange-50 border-orange-100': isSelected(permission.id)}"
                    >
                        <Checkbox 
                            :id="'perm-' + permission.id" 
                            :checked="isSelected(permission.id)"
                            @click="togglePermission(permission.id)"
                        />
                        <label 
                            :for="'perm-' + permission.id" 
                            class="text-sm font-medium leading-none cursor-pointer flex-1"
                        >
                            {{ permission.sidebar_name || permission.name }}
                        </label>
                    </div>
                </div>

                <DialogFooter class="pt-8 flex gap-2">
                    <Button type="button" variant="ghost" @click="$emit('update:open', false)" class="border">Cancelar</Button>
                    <Button 
                        @click="submit" 
                        :disabled="form.processing" 
                        class="flex-1 bg-orange-600 hover:bg-orange-700 shadow-md text-white font-semibold"
                    >
                        <Icon v-if="form.processing" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                        <Icon v-else name="save" class="mr-2 h-4 w-4" />
                        Sincronizar Permisos
                    </Button>
                </DialogFooter>
            </div>
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

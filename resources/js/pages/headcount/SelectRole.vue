<script setup lang="ts">
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const form = useForm({
    userId: 0,
    roleId: 0,
});

const props = defineProps<{
    roles?: Role[];
    user?: object;
}>();

const selectedRoleId = ref<string | null>(null);

watch(selectedRoleId, (newVal) => {
    if (newVal) {
        const roleId = parseInt(newVal);
        updateRoleUser(roleId);
    }
});

if (props.user.roles.length > 0) {
    selectedRoleId.value = props.user.roles[0].id;
}

const updateRoleUser = (roleId: number) => {
    form.userId = props.user.id;
    form.roleId = roleId;
    form.post(route('role-user'));
};
</script>

<template>
    <Select v-model="selectedRoleId">
        <SelectTrigger>
            <SelectValue placeholder="Selecciona un rol" />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup>
                <SelectLabel>Roles</SelectLabel>
                <SelectItem v-for="role in roles" :value="role.id" :key="role.id"> {{ role.name }} </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>

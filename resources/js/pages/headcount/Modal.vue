<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Area, Cafe, Headquarter, Role } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { ref, watch } from 'vue';
const open = ref(false);

const areasSelected = ref([]);

const rolesSelected = ref([]);

const props = defineProps<{
    areas: Area[];
    cafes: Cafe[];
    headquarters: Headquarter[];
    roles: Role[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    cafe_id: null,
    headquarter_id: null,
    area_id: null,
    role_id: null,
});

watch(form, (newVal) => {
    if (newVal.cafe_id) {
        const cafeSelected = props.cafes.find((cafe) => cafe.id == newVal.cafe_id);
        console.log(cafeSelected);
        if (cafeSelected) {
            areasSelected.value = cafeSelected.areas;
        } else {
            areasSelected.value = [];
        }
    } else {
        const headquarterSelected = props.headquarters.find((headquarter) => headquarter.id == newVal.headquarter_id);
        if (headquarterSelected) {
            areasSelected.value = headquarterSelected.areas;
        } else {
            areasSelected.value = [];
        }
    }
    if (newVal.area_id) {
        const areaSelected = areasSelected.value.find((area) => area.id == newVal.area_id);
        console.log(areaSelected);
        if (areaSelected) {
            rolesSelected.value = areaSelected.area_roles;
        } else {
            rolesSelected.value = [];
        }
    }
});

const submit = () => {
    form.post(route('users'), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger
            ><Button title="Agregar Usuario" class="h-full w-auto bg-blue-400"><UserPlus /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nuevo Colaborador</DialogTitle>
                <Input v-model="form.name" type="text" placeholder="Nombre" />
                <Input v-model="form.email" type="email" placeholder="Email" />
                <Input v-model="form.password" type="password" placeholder="Contraseña" />
            </DialogHeader>
            <div class="flex w-full flex-row">
                <div v-show="form.headquarter_id == null" class="w-full">
                    <Select class="w-full" v-model="form.cafe_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona una cafetería" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Cafeterías</SelectLabel>
                                <SelectItem v-for="cafe in props.cafes" :value="cafe.id" :key="cafe.id"> {{ cafe.name }} </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
                <div v-show="form.cafe_id == null && form.headquarter_id == null">
                    <p class="p-2">o</p>
                </div>
                <div v-show="form.cafe_id == null" class="w-full">
                    <Select v-model="form.headquarter_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona una sede" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Sedes</SelectLabel>
                                <SelectItem v-for="headquarter in headquarters" :value="headquarter.id" :key="headquarter.id">
                                    {{ headquarter.name }} - {{ headquarter.business.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="flex flex-row">
                <Select v-model="form.area_id">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Selecciona un area(depende de cafetería o sede)" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Areas</SelectLabel>
                            <SelectItem v-for="area in areasSelected" :value="area.id" :key="area.id">
                                {{ area.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex flex-row">
                <Select v-model="form.role_id">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Selecciona un rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Roles</SelectLabel>
                            <SelectItem v-for="role in rolesSelected" :value="role.id" :key="role.id">
                                {{ role.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
            <DialogFooter>
                <Button @click="submit">Agregar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Business, Staff, Unit } from '@/types';
import { Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import AlertErrors from '../users/AlertErrors.vue';
import { useFileUpload } from './composables/useFileUpload';
import { useImageUpload } from './composables/useImageUpload';
import { useStaffForm } from './composables/useStaffForm';
import AttachmentsTab from './tabs/AttachmentsTab.vue';
import FinancialDataTab from './tabs/FinancialDataTab.vue';
import PersonalDataTab from './tabs/PersonalDataTab.vue';
import SizesTab from './tabs/SizesTab.vue';

interface Props {
    cafes: any[];
    roles: any[];
    units: Unit[];
    businneses: Business[];
    staff: Staff;
}

const props = defineProps<Props>();

const isOpen = ref(false);
const activeTab = ref('personal');

const { form, errorsSend, showErrors, prendasFijas, cafesUnitSelected, handleSubmit, updateStaff, selectCafe, selectRole, selectUnit, selectArea } =
    useStaffForm();

if (props.staff) {
    console.log(props.staff);

    form.name = props.staff.name;
    form.dni = props.staff.dni;
    form.cell = props.staff.cell;
    form.birthdate = String(props.staff.birthdate);
    form.age = props.staff.age;
    form.sex = String(props.staff.sex);
    form.email = props.staff.email;
    form.country = props.staff.country;
    form.civilstatus = String(props.staff.civilstatus);
    form.contactname = props.staff.contactname;
    form.contactcell = props.staff.contactcell;

    form.district = props.staff.staff_financial?.district;
    form.province = props.staff.staff_financial?.province;
    form.department = props.staff.staff_financial?.department;
    form.address = props.staff.staff_financial?.address;
    form.workSystem = props.staff.staff_financial?.system_work;
    form.replacement = props.staff.staff_financial?.replacement;
    form.salary = props.staff.staff_financial?.salary;
    form.observations = props.staff.staff_financial?.observations;

    form.bankEntity = String(props.staff.staff_financial?.bank_entity);
    form.cc = props.staff.staff_financial?.account_number;
    form.cci = props.staff.staff_financial?.cci;
    form.pensioncontribution = String(props.staff.staff_financial?.pensioncontribution);
    form.startDate = String(props.staff.staff_financial?.start_date);
    form.contractEndDate = String(props.staff.staff_financial?.contract_end_date);

    form.children = props.staff.staff_financial?.children;

    props.staff.staff_clothes.forEach((clothe) => {
        const prendaFound = prendasFijas.value.find((prenda) => prenda.label == clothe.clothe_name);
        prendaFound.talla = clothe.clothing_size;
    });
}

import { watch } from 'vue';

const { fileInput, imagePreview, triggerFileInput, handleImageUpload, removeImage, selectedFile } = useImageUpload();

watch(
    () => props.staff,
    (newStaff) => {
        if (newStaff && newStaff.photo) {
            imagePreview.value = newStaff.photo.url.startsWith('http')
                ? newStaff.photo.url
                : `/storage/${newStaff.photo.url}`;
        }
    },
    { immediate: true }
);

watch(selectedFile, (newFile) => {
    form.photo = newFile;
});

const { filesRequired, showAlert, alertMessage, handleFileUpload: uploadFile, handleDateFile: dateUpload } = useFileUpload();

const nextTab = () => {
    const tabs = ['personal', 'adjuntos', 'financiero', 'tallas'];
    const currentIndex = tabs.indexOf(activeTab.value);

    if (props.staff && currentIndex == 0) {
        activeTab.value = tabs[currentIndex + 2];
    } else if (currentIndex < tabs.length - 1) {
        activeTab.value = tabs[currentIndex + 1];
    }
};

const prevTab = () => {
    const tabs = ['personal', 'adjuntos', 'financiero', 'tallas'];
    const currentIndex = tabs.indexOf(activeTab.value);
    if (props.staff && currentIndex == 2) {
        activeTab.value = tabs[currentIndex - 2];
    } else if (currentIndex > 0) {
        activeTab.value = tabs[currentIndex - 1];
    }
};

const onSubmit = () => {
    if (props.staff) {
        updateStaff(() => {
            isOpen.value = false;
            activeTab.value = 'personal';
        }, props.staff.id);
    } else {
        handleSubmit(() => {
            isOpen.value = false;
            activeTab.value = 'personal';
        }, filesRequired);
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="ghost" size="icon" class="cursor-pointer text-blue-600 hover:text-blue-800" v-if="props.staff">
                <Pencil />
            </Button>
            <Button variant="default" class="cursor-pointer bg-blue-600 text-white hover:bg-blue-700" v-else> Nuevo Personal </Button>
        </DialogTrigger>

        <DialogContent class="flex h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
            <input type="file" ref="fileInput" @change="handleImageUpload" accept="image/*" class="hidden" />

            <DialogHeader class="z-10 border-b bg-white px-6 py-4">
                <DialogTitle class="text-xl font-bold text-zinc-800 md:text-2xl"> Ficha de Registro de Colaborador </DialogTitle>
                <DialogDescription> Complete los datos del proceso de alta en las secciones a continuación. </DialogDescription>
                <AlertErrors :show="showErrors" :errors="errorsSend" />
            </DialogHeader>

            <div class="flex-1 overflow-y-auto bg-gray-50/50 px-6 py-4">
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="mb-6 grid w-full grid-cols-4 bg-zinc-100 p-1">
                        <TabsTrigger value="personal" class="text-xs md:text-sm">Personal</TabsTrigger>
                        <TabsTrigger value="adjuntos" class="text-xs md:text-sm" v-if="!staff">Adjuntos</TabsTrigger>
                        <TabsTrigger value="financiero" class="text-xs md:text-sm">Financiero</TabsTrigger>
                        <TabsTrigger value="tallas" class="text-xs md:text-sm">Tallas</TabsTrigger>
                    </TabsList>

                    <TabsContent value="personal" class="mt-0">
                        <PersonalDataTab
                            :staff="staff"
                            :form="form"
                            :cafes="cafesUnitSelected"
                            :units="units"
                            :roles="roles"
                            :businneses="businneses"
                            :image-preview="imagePreview"
                            @trigger-upload="triggerFileInput"
                            @remove-image="removeImage"
                            @select-cafe="selectCafe"
                            @select-unit="selectUnit"
                            @select-role="selectRole"
                            @select-area="selectArea"
                        />
                    </TabsContent>

                    <TabsContent value="adjuntos" class="mt-0">
                        <AttachmentsTab
                            :files-required="filesRequired"
                            :show-alert="showAlert"
                            :alert-message="alertMessage"
                            :form="form"
                            @upload-file="uploadFile"
                            @upload-date="dateUpload"
                        />
                    </TabsContent>

                    <TabsContent value="financiero" class="mt-0">
                        <FinancialDataTab :form="form" :roles="roles" @select-role="selectRole" @upload-file="uploadFile" :staff="staff" />
                    </TabsContent>

                    <TabsContent value="tallas" class="mt-0">
                        <SizesTab :prendas="prendasFijas" />
                    </TabsContent>
                </Tabs>
            </div>

            <DialogFooter class="z-10 flex w-full flex-row items-center justify-between border-t bg-white px-6 py-4">
                <div>
                    <Button v-if="activeTab !== 'personal'" @click="prevTab" variant="outline" type="button"> ← Anterior </Button>
                </div>
                <div>
                    <Button v-if="activeTab !== 'tallas'" @click="nextTab" type="button" class="bg-blue-600 text-white hover:bg-blue-700">
                        Siguiente →
                    </Button>
                    <Button v-else @click="onSubmit" type="button" class="bg-green-600 text-white hover:bg-green-700"> Finalizar Registro </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

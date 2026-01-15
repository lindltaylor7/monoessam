<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Business, Staff, Unit } from '@/types';
import { Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AlertErrors from '../headcount/AlertErrors.vue';
import { useFileUpload } from './composables/useFileUpload';
import { useImageUpload } from './composables/useImageUpload';
import { useStaffForm } from './composables/useStaffForm';
import { useStaffInitialization } from './composables/useStaffInitialization';
import { useTabNavigation } from './composables/useTabNavigation';
import AttachmentsTab from './tabs/AttachmentsTab.vue';
import FinancialDataTab from './tabs/FinancialDataTab.vue';
import PersonalDataTab from './tabs/PersonalDataTab.vue';
import SizesTab from './tabs/SizesTab.vue';

interface Props {
    cafes: any[];
    roles: any[];
    units: Unit[];
    businneses: Business[];
    staff?: Staff;
}

const props = defineProps<Props>();

const isOpen = ref(false);
const isEditMode = computed(() => !!props.staff);

// Composables
const { form, errorsSend, showErrors, prendasFijas, cafesUnitSelected, handleSubmit, updateStaff, selectCafe, selectRole, selectUnit, selectArea } =
    useStaffForm();

const { fileInput, imagePreview, triggerFileInput, handleImageUpload, removeImage, selectedFile } = useImageUpload();

const { filesRequired, showAlert, alertMessage, handleFileUpload: uploadFile, handleDateFile: dateUpload } = useFileUpload();

const { initializeStaffData } = useStaffInitialization(form, prendasFijas, imagePreview, cafesUnitSelected);

const { activeTab, nextTab, prevTab, resetTab, shouldShowTab } = useTabNavigation(isEditMode);

// Watchers
watch(() => props.staff, (newStaff) => initializeStaffData(newStaff, props.units), { immediate: true });

watch(selectedFile, (newFile) => {
    form.photo = newFile;
});

// Handlers
const onSubmit = () => {
    const onSuccess = () => {
        isOpen.value = false;
        resetTab();
    };

    if (isEditMode.value) {
        updateStaff(onSuccess, props.staff!.id);
    } else {
        handleSubmit(onSuccess, filesRequired);
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button v-if="isEditMode" variant="ghost" size="icon" class="cursor-pointer text-blue-600 hover:text-blue-800">
                <Pencil />
            </Button>
            <Button v-else variant="default" class="cursor-pointer bg-blue-600 text-white hover:bg-blue-700"> Nuevo Personal </Button>
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
                    <TabsList class="mb-6 grid w-full bg-zinc-100 p-1" :class="isEditMode ? 'grid-cols-3' : 'grid-cols-4'">
                        <TabsTrigger value="personal" class="text-xs md:text-sm">Personal</TabsTrigger>
                        <TabsTrigger v-if="shouldShowTab('adjuntos')" value="adjuntos" class="text-xs md:text-sm">Adjuntos</TabsTrigger>
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

                    <TabsContent v-if="shouldShowTab('adjuntos')" value="adjuntos" class="mt-0">
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
                <Button v-if="activeTab !== 'personal'" @click="prevTab" variant="outline" type="button"> ← Anterior </Button>
                <div v-else></div>

                <Button v-if="activeTab !== 'tallas'" @click="nextTab" type="button" class="bg-blue-600 text-white hover:bg-blue-700">
                    Siguiente →
                </Button>
                <Button v-else @click="onSubmit" type="button" class="bg-green-600 text-white hover:bg-green-700"> Finalizar Registro </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
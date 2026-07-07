<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { MapPinPlus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    businessId: number;
}>();

const open = ref(false);

const form = useForm({
    name: '',
    address: '',
    business_id: props.businessId,
});

const submit = () => {
    form.transform((data) => ({ ...data, business_id: props.businessId })).post(route('headquarters.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button
                class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 transition-colors hover:bg-indigo-100 hover:text-indigo-700"
                title="Agregar sede"
                type="button"
            >
                <MapPinPlus class="h-3.5 w-3.5" />
            </button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[420px]">
            <DialogHeader>
                <DialogTitle>Nueva Sede</DialogTitle>
                <DialogDescription>Por favor, complete los campos siguientes</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-3">
                <div class="space-y-1.5">
                    <Input v-model="form.name" type="text" placeholder="Nombre de la Sede" />
                    <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                </div>
                <Input v-model="form.address" type="text" placeholder="Dirección (opcional)" />

                <DialogFooter>
                    <button
                        type="submit"
                        :disabled="!form.name.trim() || form.processing"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-violet-600 px-4 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Guardando…' : 'Agregar' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

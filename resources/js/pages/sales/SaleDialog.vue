<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ShoppingCart } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    services: [],
});

const open = ref(false);

const submit = () => {
    // Assuming you have a function to handle the form submission
    // You can use the form data here
    console.log('Form submitted');
};
</script>
<template>
    <Dialog v-model:open="open">
        <DialogTrigger>
            <Button class="bg-green-400"><ShoppingCart /></Button
        ></DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Registrar Venta </DialogTitle>
            </DialogHeader>
            <div class="flex flex-col gap-2">
                <label for="cafe">Servicio</label>
                <select id="cafe" class="w-full rounded-md border p-2">
                    <option value="" disabled>Seleccione servicio</option>
                    <option v-for="service in props.services" :value="service.id">{{ service.name }}</option>
                </select>
                <label for="amount">Monto</label>
                <input id="amount" type="number" placeholder="Ingrese el monto" class="w-full rounded-md border p-2" />
            </div>
            <DialogFooter @click="submit"> Registrar </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped></style>

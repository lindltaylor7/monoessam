<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, Lock, Mail } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isPasswordFocused = ref(false);
const isEmailFocused = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Bienvenido a ESSAM" description="Ingresa tus credenciales de acceso">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- Email Input con efectos modernos -->
                <div class="group relative">
                    <Label
                        for="email"
                        class="mb-2 block text-sm font-semibold text-gray-700 transition-colors duration-200"
                        :class="{ 'text-red-600': isEmailFocused }"
                    >
                        Correo Electrónico
                    </Label>
                    <div class="relative">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 transition-all duration-200"
                            :class="isEmailFocused ? 'text-red-600' : 'text-gray-400'"
                        >
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="tu@email.com"
                            @focus="isEmailFocused = true"
                            @blur="isEmailFocused = false"
                            class="h-12 rounded-xl border-2 pr-4 pl-12 text-base transition-all duration-200 focus:border-red-500 focus:ring-2 focus:ring-red-100"
                            :class="form.errors.email ? 'border-red-300 bg-red-50' : 'border-gray-200 hover:border-gray-300'"
                        />
                        <!-- Animación de línea inferior -->
                        <!-- <div
                            class="absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-red-600 to-pink-600 transition-all duration-300"
                            :class="isEmailFocused ? 'w-full' : 'w-0'"
                        ></div> -->
                    </div>
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <!-- Password Input con efectos modernos -->
                <div class="group relative">
                    <div class="mb-2 flex items-center justify-between">
                        <Label
                            for="password"
                            class="text-sm font-semibold text-gray-700 transition-colors duration-200"
                            :class="{ 'text-red-600': isPasswordFocused }"
                        >
                            Contraseña
                        </Label>
                       <!--  <TextLink
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="group/link relative text-sm font-medium text-gray-600 transition-colors duration-200 hover:text-red-600"
                            :tabindex="5"
                        >
                            <span class="relative">
                                ¿Olvidaste tu contraseña?
                                <span
                                    class="absolute -bottom-0.5 left-0 h-0.5 w-0 bg-red-600 transition-all duration-300 group-hover/link:w-full"
                                ></span>
                            </span>
                        </TextLink> -->
                    </div>
                    <div class="relative">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 transition-all duration-200"
                            :class="isPasswordFocused ? 'text-red-600' : 'text-gray-400'"
                        >
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="••••••••"
                            @focus="isPasswordFocused = true"
                            @blur="isPasswordFocused = false"
                            class="h-12 rounded-xl border-2 pr-4 pl-12 text-base transition-all duration-200 focus:border-red-500 focus:ring-2 focus:ring-red-100"
                            :class="form.errors.password ? 'border-red-300 bg-red-50' : 'border-gray-200 hover:border-gray-300'"
                        />
                        <!-- Animación de línea inferior -->
                        <!-- <div
                            class="absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-red-600 to-pink-600 transition-all duration-300"
                            :class="isPasswordFocused ? 'w-full' : 'w-0'"
                        ></div> -->
                    </div>
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <!-- Botón de Ingresar mejorado -->
                <Button
                    type="submit"
                    class="group relative mt-4 h-12 w-full overflow-hidden rounded-xl bg-gradient-to-r from-red-600 to-red-700 font-semibold text-white shadow-lg shadow-red-500/30 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:shadow-red-500/40 active:scale-[0.98] disabled:opacity-70 disabled:hover:scale-100"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <!-- Efecto de brillo al hover -->
                    <div
                        class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 group-hover:translate-x-full"
                    ></div>

                    <span class="relative flex items-center justify-center gap-2">
                        <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" />
                        <template v-else>
                            <span>Ingresar</span>
                            <ArrowRight class="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" />
                        </template>
                    </span>
                </Button>

                <!-- Texto adicional (opcional) -->
                <div class="mt-2 text-center">
                    <p class="text-sm text-gray-500">
                        Al iniciar sesión, aceptas nuestros
                        <a href="#" class="font-medium text-red-600 hover:underline">términos y condiciones</a>
                    </p>
                </div>
            </div>
        </form>
    </AuthBase>
</template>

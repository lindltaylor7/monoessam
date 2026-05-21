<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertCircle, ArrowUp, Clock, FileCheck, FileText, LogIn, PieChart, Users } from 'lucide-vue-next';
import Card from '../components/ui/card/Card.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Banner Principal -->
            <div
                class="relative mb-2 flex flex-col items-center justify-between overflow-hidden rounded-2xl bg-[#0f62fe] px-6 py-10 shadow-lg sm:px-10 md:flex-row">
                <!-- Background Decor -->
                <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full bg-blue-500/50 blur-3xl"></div>
                <div class="pointer-events-none absolute right-40 -bottom-20 h-64 w-64 rounded-full bg-blue-400/20 blur-3xl"></div>

                <div class="relative z-10 text-white md:w-3/5">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Bienvenido de vuelta</h1>
                    <p class="mt-4 max-w-2xl text-blue-100 sm:text-lg">
                        Revisa todo el rendimiento del personal y los expedientes desde aquí. Se ha registrado un incremento de
                        <span class="rounded bg-blue-500/40 px-2 py-0.5 font-bold text-white">15%</span> en asistencia y cumplimiento este mes.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-6">
                        <button
                            class="rounded-lg bg-white px-6 py-2.5 font-semibold text-blue-600 shadow-sm transition-colors duration-200 hover:bg-blue-50"
                        >
                            Revisar Expedientes
                        </button>
                        <div class="flex items-center gap-6">
                            <div>
                                <div class="text-2xl font-bold tracking-tight text-white">1,240</div>
                                <div class="text-sm font-medium text-blue-200">Personal Activo</div>
                            </div>
                            <div class="h-8 w-px bg-blue-400/50"></div>
                            <div>
                                <div class="text-2xl font-bold tracking-tight text-white">92%</div>
                                <div class="text-sm font-medium text-blue-200">Cumplimiento</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Abstract UI Illustration (Right) -->
                <div class="relative z-10 hidden w-2/5 cursor-pointer md:block">
                    <div class="flex justify-end pr-4">
                        <div
                            class="relative h-52 w-72 transform rounded-2xl border border-white/20 bg-white/10 p-5 shadow-2xl backdrop-blur-md transition-transform duration-500 hover:rotate-0 md:-rotate-3"
                        >
                            <div class="mb-4 flex gap-2">
                                <div class="h-3 w-3 rounded-full bg-red-400/90"></div>
                                <div class="h-3 w-3 rounded-full bg-amber-400/90"></div>
                                <div class="h-3 w-3 rounded-full bg-green-400/90"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="h-4 w-3/4 rounded bg-white/20"></div>
                                <div class="h-4 w-1/2 rounded bg-white/20"></div>
                                <div class="mt-8 flex items-center gap-4">
                                    <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-xl bg-blue-300/30">
                                        <Users class="h-8 w-8 text-blue-100" />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <div class="h-3 w-full rounded-full bg-white/20"></div>
                                        <div class="h-3 w-4/5 rounded-full bg-white/20"></div>
                                        <div class="h-3 w-2/3 rounded-full bg-white/20"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating badge -->
                            <div
                                class="absolute -right-6 -bottom-6 flex rotate-6 transform animate-bounce items-center gap-3 rounded-xl bg-white p-3 shadow-xl"
                                style="animation-duration: 3s"
                            >
                                <div class="rounded-lg bg-green-100 p-2 text-green-600"><ArrowUp class="h-5 w-5" /></div>
                                <div>
                                    <span class="block text-xs font-bold text-gray-800">Crecimiento</span>
                                    <span class="block text-[10px] text-gray-500">Último Trimestre</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Cards Row -->
            <div class="mb-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Stats Card 1 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                >
                    <Card class="h-full border-0 bg-transparent p-6 shadow-none">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Total Plantilla</h3>
                            <div class="rounded-lg bg-blue-50 p-2 text-blue-600"><Users class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">1,240</p>
                        <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center font-medium text-green-600"><ArrowUp class="mr-1 h-3 w-3" /> 4.5%</span>
                            <span class="ml-2">este mes</span>
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 2 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                >
                    <Card class="h-full border-0 bg-transparent p-6 shadow-none">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Expedientes Digitales</h3>
                            <div class="rounded-lg bg-purple-50 p-2 text-purple-600"><FileText class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">890</p>
                        <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex items-center font-medium text-green-600"><ArrowUp class="mr-1 h-3 w-3" /> 12.3%</span>
                            <span class="ml-2">completados</span>
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 3 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                >
                    <Card class="h-full border-0 bg-transparent p-6 shadow-none">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Entrega de EPP</h3>
                            <div class="rounded-lg bg-amber-50 p-2 text-amber-600"><PieChart class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">65%</p>
                        <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-medium tracking-tight text-amber-600">Faltan asignaciones en Mina</span>
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 4 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
                >
                    <Card class="h-full border-0 bg-transparent p-6 shadow-none">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Alertas</h3>
                            <div class="rounded-lg bg-red-50 p-2 text-red-600"><AlertCircle class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">12</p>
                        <p class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-medium text-red-500">Archivos por vencer (SCTR)</span>
                        </p>
                    </Card>
                </div>
            </div>

            <!-- Main Content Area -->
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border bg-white shadow-sm lg:min-h-min dark:bg-gray-800"
            >
                <div class="p-6">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Actividad Reciente</h2>
                        <button
                            class="rounded-md px-4 py-1.5 text-sm font-semibold text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                        >
                            Ver Todo
                        </button>
                    </div>

                    <div class="mt-4 space-y-2">
                        <!-- Activity Item -->
                        <div
                            class="flex items-start rounded-xl border border-transparent p-4 transition-colors hover:border-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-green-900/50 dark:text-green-400"
                            >
                                <FileCheck class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Expediente actualizado</p>
                                    <time class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400"
                                        ><Clock class="mr-1 h-3 w-3" /> Hace 2 horas</time
                                    >
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Se han subido nuevos documentos al expediente de <strong>Juan Pérez</strong> (SCTR).
                                </p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div
                            class="flex items-start rounded-xl border border-transparent p-4 transition-colors hover:border-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400"
                            >
                                <LogIn class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Nuevo Alta de Personal</p>
                                    <time class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400"
                                        ><Clock class="mr-1 h-3 w-3" /> Hace 5 horas</time
                                    >
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    <strong>María Góngora</strong> ha sido registrada como <em>Administradora</em> en la unidad Mina 1.
                                </p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div
                            class="flex items-start rounded-xl border border-transparent p-4 transition-colors hover:border-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        >
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-purple-900/50 dark:text-purple-400"
                            >
                                <AlertCircle class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Alerta de inasistencia</p>
                                    <time class="flex items-center text-xs font-medium text-gray-500 dark:text-gray-400"
                                        ><Clock class="mr-1 h-3 w-3" /> Ayer</time
                                    >
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    Se detectó inasistencia no justificada para <strong>Roberto Hernández</strong> en Comedor Principal.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

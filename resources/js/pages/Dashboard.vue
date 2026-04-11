<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Card from '../components/ui/card/Card.vue';
import { ArrowUp, User, Users, FileText, PieChart, AlertCircle, Clock, FileCheck, LogIn } from 'lucide-vue-next';

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
            <div class="relative overflow-hidden rounded-2xl bg-[#0f62fe] px-6 py-10 sm:px-10 shadow-lg mb-2 flex flex-col md:flex-row items-center justify-between">
                <!-- Background Decor -->
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-blue-500/50 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-20 right-40 h-64 w-64 rounded-full bg-blue-400/20 blur-3xl pointer-events-none"></div>
                
                <div class="relative z-10 md:w-3/5 text-white">
                    <h1 class="text-3xl font-bold sm:text-4xl tracking-tight">Bienvenido de vuelta</h1>
                    <p class="mt-4 text-blue-100 sm:text-lg max-w-2xl">
                        Revisa todo el rendimiento del personal y los expedientes desde aquí. Se ha registrado un incremento de
                        <span class="font-bold text-white bg-blue-500/40 px-2 py-0.5 rounded">15%</span> en asistencia y cumplimiento este mes.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-6">
                        <button class="bg-white text-blue-600 hover:bg-blue-50 font-semibold px-6 py-2.5 rounded-lg shadow-sm transition-colors duration-200">
                            Revisar Expedientes
                        </button>
                        <div class="flex items-center gap-6">
                            <div>
                                <div class="text-2xl font-bold text-white tracking-tight">1,240</div>
                                <div class="text-sm text-blue-200 font-medium">Personal Activo</div>
                            </div>
                            <div class="h-8 w-px bg-blue-400/50"></div>
                            <div>
                                <div class="text-2xl font-bold text-white tracking-tight">92%</div>
                                <div class="text-sm text-blue-200 font-medium">Cumplimiento</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Abstract UI Illustration (Right) -->
                <div class="hidden md:block relative z-10 w-2/5 cursor-pointer">
                    <div class="flex justify-end pr-4">
                        <div class="relative w-72 h-52 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-5 shadow-2xl transform md:-rotate-3 hover:rotate-0 transition-transform duration-500">
                            <div class="flex gap-2 mb-4">
                                <div class="w-3 h-3 rounded-full bg-red-400/90"></div>
                                <div class="w-3 h-3 rounded-full bg-amber-400/90"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400/90"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="h-4 w-3/4 rounded bg-white/20"></div>
                                <div class="h-4 w-1/2 rounded bg-white/20"></div>
                                <div class="flex gap-4 mt-8 items-center">
                                    <div class="h-16 w-16 rounded-xl bg-blue-300/30 flex-shrink-0 flex items-center justify-center">
                                        <Users class="text-blue-100 h-8 w-8" />
                                    </div>
                                    <div class="space-y-2 flex-1">
                                        <div class="h-3 w-full rounded-full bg-white/20"></div>
                                        <div class="h-3 w-4/5 rounded-full bg-white/20"></div>
                                        <div class="h-3 w-2/3 rounded-full bg-white/20"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Floating badge -->
                            <div class="absolute -right-6 -bottom-6 bg-white rounded-xl p-3 shadow-xl flex items-center gap-3 transform rotate-6 animate-bounce" style="animation-duration: 3s">
                                <div class="bg-green-100 text-green-600 rounded-lg p-2"><ArrowUp class="w-5 h-5" /></div>
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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-2">
                <!-- Stats Card 1 -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white dark:bg-gray-800 shadow-sm transition-shadow hover:shadow-md">
                    <Card class="h-full p-6 border-0 shadow-none bg-transparent">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Total Plantilla</h3>
                            <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><Users class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">1,240</p>
                        <p class="mt-2 text-sm text-gray-500 flex items-center dark:text-gray-400">
                            <span class="text-green-600 font-medium flex items-center"><ArrowUp class="w-3 h-3 mr-1"/> 4.5%</span> <span class="ml-2">este mes</span>
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 2 -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white dark:bg-gray-800 shadow-sm transition-shadow hover:shadow-md">
                    <Card class="h-full p-6 border-0 shadow-none bg-transparent">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Expedientes Digitales</h3>
                            <div class="p-2 bg-purple-50 text-purple-600 rounded-lg"><FileText class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">890</p>
                        <p class="mt-2 text-sm text-gray-500 flex items-center dark:text-gray-400">
                            <span class="text-green-600 font-medium flex items-center"><ArrowUp class="w-3 h-3 mr-1"/> 12.3%</span> <span class="ml-2">completados</span>
                        </p>
                    </Card>
                </div>
                
                <!-- Stats Card 3 -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white dark:bg-gray-800 shadow-sm transition-shadow hover:shadow-md">
                    <Card class="h-full p-6 border-0 shadow-none bg-transparent">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Entrega de EPP</h3>
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-lg"><PieChart class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">65%</p>
                        <p class="mt-2 text-sm text-gray-500 flex items-center dark:text-gray-400">
                            <span class="text-amber-600 font-medium tracking-tight">Faltan asignaciones en Mina</span>
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 4 -->
                <div class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden rounded-xl border bg-white dark:bg-gray-800 shadow-sm transition-shadow hover:shadow-md">
                    <Card class="h-full p-6 border-0 shadow-none bg-transparent">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Alertas</h3>
                            <div class="p-2 bg-red-50 text-red-600 rounded-lg"><AlertCircle class="h-5 w-5" /></div>
                        </div>
                        <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">12</p>
                        <p class="mt-2 text-sm text-gray-500 flex items-center dark:text-gray-400">
                            <span class="text-red-500 font-medium">Archivos por vencer (SCTR)</span>
                        </p>
                    </Card>
                </div>
            </div>

            <!-- Main Content Area -->
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border bg-white lg:min-h-min dark:bg-gray-800 shadow-sm"
            >
                <div class="p-6">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Actividad Reciente</h2>
                        <button
                            class="rounded-md px-4 py-1.5 text-sm font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30 transition-colors"
                        >
                            Ver Todo
                        </button>
                    </div>

                    <div class="mt-4 space-y-2">
                        <!-- Activity Item -->
                        <div class="flex items-start rounded-xl p-4 hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-green-900/50 dark:text-green-400 flex-shrink-0"
                            >
                                <FileCheck class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Expediente actualizado</p>
                                    <time class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center"><Clock class="w-3 h-3 mr-1"/> Hace 2 horas</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Se han subido nuevos documentos al expediente de <strong>Juan Pérez</strong> (SCTR).</p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div class="flex items-start rounded-xl p-4 hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400 flex-shrink-0"
                            >
                                <LogIn class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Nuevo Alta de Personal</p>
                                    <time class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center"><Clock class="w-3 h-3 mr-1"/> Hace 5 horas</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300"><strong>María Góngora</strong> ha sido registrada como <em>Administradora</em> en la unidad Mina 1.</p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div class="flex items-start rounded-xl p-4 hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-purple-900/50 dark:text-purple-400 flex-shrink-0"
                            >
                                <AlertCircle class="h-6 w-6" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900 dark:text-white">Alerta de inasistencia</p>
                                    <time class="text-xs font-medium text-gray-500 dark:text-gray-400 flex items-center"><Clock class="w-3 h-3 mr-1"/> Ayer</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Se detectó inasistencia no justificada para <strong>Roberto Hernández</strong> en Comedor Principal.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

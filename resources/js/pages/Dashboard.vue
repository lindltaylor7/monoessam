<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Card from '../components/ui/card/Card.vue';
import { ArrowUp, DollarSign, MessageCircle, ShoppingBag, ShoppingCart, User } from 'lucide-vue-next';

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
            <!-- Top Cards Row -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- Welcome Card -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30"
                >
                    <Card class="flex h-full flex-col justify-between p-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Bienvenido a Essam!</h2>
                            <p class="mt-2 text-gray-600 dark:text-gray-300">Here's what's happening with your store today.</p>
                        </div>
                        <div class="mt-4 flex items-center">
                            <span
                                class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800 dark:bg-blue-800/50 dark:text-blue-200"
                            >
                                <ArrowUp class="mr-1 h-4 w-4" />
                                12% avance
                            </span>
                        </div>
                    </Card>
                </div>

                <!-- Stats Card 1 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border bg-white dark:bg-gray-800"
                >
                    <Card class="h-full p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Ganancia Total</h3>
                            <DollarSign class="h-6 w-6 text-green-500" />
                        </div>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">$24,780</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="text-green-600 dark:text-green-400">+12.5%</span> desde el mes pasado
                        </p>
                    </Card>
                </div>

                <!-- Stats Card 2 -->
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border bg-white dark:bg-gray-800"
                >
                    <Card class="h-full p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Nuevas ordenes</h3>
                            <ShoppingBag class="h-6 w-6 text-blue-500" />
                        </div>
                        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">1,429</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="text-green-600 dark:text-green-400">+8.2%</span> desde el mes pasado
                        </p>
                    </Card>
                </div>
            </div>

            <!-- Main Content Area -->
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border bg-white md:min-h-min dark:bg-gray-800"
            >
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Actividad Reciente</h2>
                        <button
                            class="rounded-md px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                        >
                            View All
                        </button>
                    </div>

                    <div class="mt-6 space-y-4">
                        <!-- Activity Item -->
                        <div class="flex items-start rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400"
                            >
                                <ShoppingCart class="h-5 w-5" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-900 dark:text-white">New order #3210</p>
                                    <time class="text-sm text-gray-500 dark:text-gray-400">2 hours ago</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Customer: John Smith - $125.00</p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div class="flex items-start rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400"
                            >
                                <User class="h-5 w-5" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-900 dark:text-white">New customer registered</p>
                                    <time class="text-sm text-gray-500 dark:text-gray-400">5 hours ago</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Sarah Johnson - Premium plan</p>
                            </div>
                        </div>

                        <!-- Activity Item -->
                        <div class="flex items-start rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900/50 dark:text-purple-400"
                            >
                                <MessageCircle class="h-5 w-5" />
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-gray-900 dark:text-white">New support ticket</p>
                                    <time class="text-sm text-gray-500 dark:text-gray-400">1 day ago</time>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Robert Chen - Order delivery issue</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

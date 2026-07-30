<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Subdealership } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';
import SubdealershipModal from './SubdealershipModal.vue';
import {
    Building2,
    Search,
    X,
    Plus,
    Phone,
    Mail,
    FileText,
    MapPin,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Pencil,
    Trash2,
    CheckCircle2,
    SearchX,
    SlidersHorizontal,
} from 'lucide-vue-next';

interface Props {
    subdealerships: Subdealership[];
    allSubdealerships: Subdealership[];
}

const props = defineProps<Props>();

const displayedSubdealerships = ref<Subdealership[]>([...(props.subdealerships || [])]);

watch(
    () => props.subdealerships,
    (val) => {
        displayedSubdealerships.value = [...(val || [])];
    },
    { immediate: true },
);

// ── Search & Pagination State ──────────────────────────────────────────
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const itemsPerPageOptions = [5, 10, 15, 25, 50, 100];

// Filtered items based on global search
const filteredSubdealerships = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return displayedSubdealerships.value;

    return displayedSubdealerships.value.filter((item) => {
        const name = (item.name || '').toLowerCase();
        const ruc = (item.ruc || '').toLowerCase();
        const phone = (item.phone || '').toLowerCase();
        const email = (item.email || '').toLowerCase();
        const fiscal = (item.fiscal_address || '').toLowerCase();
        const legal = (item.legal_address || '').toLowerCase();

        return (
            name.includes(q) ||
            ruc.includes(q) ||
            phone.includes(q) ||
            email.includes(q) ||
            fiscal.includes(q) ||
            legal.includes(q)
        );
    });
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredSubdealerships.value.length / itemsPerPage.value));
});

// Paginated items
const paginatedSubdealerships = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredSubdealerships.value.slice(start, end);
});

// Pagination Summary (e.g. 1 to 10 of 45)
const paginationSummary = computed(() => {
    const total = filteredSubdealerships.value.length;
    if (total === 0) return { from: 0, to: 0, total: 0 };
    const from = (currentPage.value - 1) * itemsPerPage.value + 1;
    const to = Math.min(currentPage.value * itemsPerPage.value, total);
    return { from, to, total };
});

// KPI Quick Stats
const stats = computed(() => {
    const total = displayedSubdealerships.value.length;
    const withRuc = displayedSubdealerships.value.filter((s) => !!s.ruc && s.ruc.trim().length > 0).length;
    const withContact = displayedSubdealerships.value.filter(
        (s) => (!!s.phone && s.phone.trim().length > 0) || (!!s.email && s.email.trim().length > 0),
    ).length;
    return { total, withRuc, withContact };
});

// Visible Page Numbers Array
const visiblePages = computed(() => {
    const pages: (number | string)[] = [];
    const maxVisible = 5;
    const total = totalPages.value;

    if (total <= maxVisible) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        let start = Math.max(1, currentPage.value - 2);
        let end = Math.min(total, currentPage.value + 2);

        if (currentPage.value <= 3) {
            start = 1;
            end = 5;
        } else if (currentPage.value >= total - 2) {
            start = total - 4;
            end = total;
        }

        if (start > 1) {
            pages.push(1);
            if (start > 2) pages.push('...');
        }

        for (let i = start; i <= end; i++) {
            if (!pages.includes(i)) pages.push(i);
        }

        if (end < total) {
            if (end < total - 1) pages.push('...');
            pages.push(total);
        }
    }
    return pages;
});

// Watch search & perPage changes to reset to page 1
watch([searchQuery, itemsPerPage], () => {
    currentPage.value = 1;
});

watch(totalPages, (newTotalPages) => {
    if (currentPage.value > newTotalPages) {
        currentPage.value = newTotalPages;
    }
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const clearSearch = () => {
    searchQuery.value = '';
};

// ── Modals & Actions ───────────────────────────────────────────────────
const showModal = ref(false);
const editingSubdealership = ref<Subdealership | null>(null);

const form = useForm({
    name: '',
    ruc: '',
    fiscal_address: '',
    legal_address: '',
    phone: '',
    email: '',
});

const openCreateModal = () => {
    editingSubdealership.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (subdealership: Subdealership) => {
    editingSubdealership.value = subdealership;
    form.name = subdealership.name || '';
    form.ruc = subdealership.ruc || '';
    form.fiscal_address = subdealership.fiscal_address || '';
    form.legal_address = subdealership.legal_address || '';
    form.phone = subdealership.phone || '';
    form.email = subdealership.email || '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingSubdealership.value = null;
    form.reset();
    form.clearErrors();
};

const submitCreate = (formArg: any) => {
    if (editingSubdealership.value) {
        formArg.put(route('subdealerships.update', editingSubdealership.value.id), {
            onSuccess: () => {
                closeModal();
                router.reload({ only: ['subdealerships', 'allSubdealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'La subconcesionaria se ha actualizado correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        formArg.post(route('subdealerships.store'), {
            onSuccess: () => {
                closeModal();
                router.reload({ only: ['subdealerships', 'allSubdealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Registrado!',
                    text: 'La subconcesionaria se ha creado correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        });
    }
};

const attachExisting = (id: number) => {
    router.post(
        route('subdealerships.attach', { subdealership: id }),
        {},
        {
            onSuccess: () => {
                router.reload({ only: ['subdealerships', 'allSubdealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Asociada!',
                    text: 'La subconcesionaria existente se ha asociado a tu mina correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        },
    );
};

const deleteSubdealership = async (subdealership: Subdealership) => {
    const result = await Swal.fire({
        title: '¿Eliminar subconcesionaria?',
        html: `¿Estás seguro de eliminar <strong>${subdealership.name}</strong>?<br><span class="text-xs text-slate-500">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-2xl',
        },
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`/subdealerships/${subdealership.id}`);
            displayedSubdealerships.value = displayedSubdealerships.value.filter((s) => s.id !== subdealership.id);
            Swal.fire({
                icon: 'success',
                title: '¡Eliminado!',
                text: 'La subconcesionaria ha sido eliminada correctamente.',
                timer: 2000,
                showConfirmButton: false,
            });
        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo eliminar la subconcesionaria.',
            });
        }
    }
};

const getAvatarColor = (name: string) => {
    const colors = [
        'bg-blue-100 text-blue-700 border-blue-200',
        'bg-indigo-100 text-indigo-700 border-indigo-200',
        'bg-emerald-100 text-emerald-700 border-emerald-200',
        'bg-amber-100 text-amber-700 border-amber-200',
        'bg-violet-100 text-violet-700 border-violet-200',
        'bg-teal-100 text-teal-700 border-teal-200',
    ];
    let hash = 0;
    for (let i = 0; i < (name || '').length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
};
</script>

<template>
    <Head title="Subconcesionarias" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 bg-slate-50/50 min-h-screen">
            <!-- ── Page Header ─────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 border border-blue-100 shadow-sm">
                        <Building2 class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Subconcesionarias</h1>
                        <p class="text-sm text-slate-500 mt-0.5">Gestión y control de subconcesionarias asociadas</p>
                    </div>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-500 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4 stroke-[2.5]" />
                    <span>Crear Subconcesionaria</span>
                </button>
            </div>

            <!-- ── Metrics Quick Stats ─────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 border border-blue-100">
                        <Building2 class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Subconcesionarias</p>
                        <p class="text-2xl font-extrabold text-slate-900 mt-0.5">{{ stats.total }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 border border-emerald-100">
                        <FileText class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Con RUC Registrado</p>
                        <p class="text-2xl font-extrabold text-slate-900 mt-0.5">{{ stats.withRuc }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 border border-indigo-100">
                        <Phone class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Con Contacto</p>
                        <p class="text-2xl font-extrabold text-slate-900 mt-0.5">{{ stats.withContact }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Search & Filter Controls ────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <!-- Search Input -->
                <div class="relative w-full md:w-96">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <Search class="h-4 w-4" />
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por nombre, RUC, teléfono, email..."
                        class="w-full pl-10 pr-9 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all outline-none"
                    />
                    <button
                        v-if="searchQuery"
                        @click="clearSearch"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Controls & Count -->
                <div class="flex flex-wrap items-center justify-between md:justify-end gap-3 w-full md:w-auto">
                    <!-- Badge found count -->
                    <span v-if="searchQuery" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-100">
                        {{ filteredSubdealerships.length }} resultado(s)
                    </span>

                    <!-- Items per page selector -->
                    <div class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <SlidersHorizontal class="h-3.5 w-3.5 text-slate-400" />
                        <span>Mostrar</span>
                        <select
                            v-model="itemsPerPage"
                            class="rounded-lg border border-slate-200 bg-slate-50/50 px-2.5 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition"
                        >
                            <option v-for="opt in itemsPerPageOptions" :key="opt" :value="opt">
                                {{ opt }} por pág.
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── Main Data Table Card ───────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col flex-1">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200/80">
                        <thead>
                            <tr class="bg-slate-50/80">
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <Building2 class="h-3.5 w-3.5 text-slate-400" />
                                        <span>Subconcesionaria</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <FileText class="h-3.5 w-3.5 text-slate-400" />
                                        <span>RUC</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <Phone class="h-3.5 w-3.5 text-slate-400" />
                                        <span>Teléfono</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <Mail class="h-3.5 w-3.5 text-slate-400" />
                                        <span>Email</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <MapPin class="h-3.5 w-3.5 text-slate-400" />
                                        <span>Dirección Fiscal</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="subdealership in paginatedSubdealerships"
                                :key="subdealership.id"
                                class="hover:bg-blue-50/30 transition-colors duration-150 group"
                            >
                                <!-- Nombre -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-9 w-9 rounded-xl flex items-center justify-center font-bold text-xs border shadow-2xs flex-shrink-0"
                                            :class="getAvatarColor(subdealership.name)"
                                        >
                                            {{ subdealership.name ? subdealership.name.charAt(0).toUpperCase() : 'S' }}
                                        </div>
                                        <div>
                                            <span class="text-sm font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">
                                                {{ subdealership.name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- RUC -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span
                                        v-if="subdealership.ruc"
                                        class="inline-flex items-center gap-1 font-mono text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-200"
                                    >
                                        {{ subdealership.ruc }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">Sin RUC</span>
                                </td>

                                <!-- Teléfono -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <a
                                        v-if="subdealership.phone"
                                        :href="`tel:${subdealership.phone}`"
                                        class="inline-flex items-center gap-1.5 text-slate-700 hover:text-blue-600 transition"
                                    >
                                        {{ subdealership.phone }}
                                    </a>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Email -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <a
                                        v-if="subdealership.email"
                                        :href="`mailto:${subdealership.email}`"
                                        class="inline-flex items-center gap-1.5 text-blue-600 hover:underline transition"
                                    >
                                        {{ subdealership.email }}
                                    </a>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Dirección Fiscal -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 max-w-xs truncate">
                                    <span v-if="subdealership.fiscal_address" :title="subdealership.fiscal_address">
                                        {{ subdealership.fiscal_address }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openEditModal(subdealership)"
                                            title="Editar Subconcesionaria"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-amber-600 hover:bg-amber-50 hover:text-amber-700 active:scale-95 transition"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="deleteSubdealership(subdealership)"
                                            title="Eliminar Subconcesionaria"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-red-500 hover:bg-red-50 hover:text-red-700 active:scale-95 transition"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── Empty State ────────────────────────────────────── -->
                <div v-if="filteredSubdealerships.length === 0" class="py-16 text-center px-4 flex flex-col items-center justify-center my-auto">
                    <div class="h-16 w-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-4">
                        <SearchX v-if="searchQuery" class="h-8 w-8" />
                        <Building2 v-else class="h-8 w-8" />
                    </div>
                    <h3 class="text-base font-bold text-slate-800">
                        {{ searchQuery ? 'No se encontraron resultados' : 'No hay subconcesionarias registradas' }}
                    </h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm">
                        {{
                            searchQuery
                                ? `No se encontraron coincidencias para "${searchQuery}". Intenta con otros términos.`
                                : 'Comienza creando la primera subconcesionaria para asociarla a la mina.'
                        }}
                    </p>
                    <div class="mt-6 flex items-center gap-3">
                        <button
                            v-if="searchQuery"
                            @click="clearSearch"
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition"
                        >
                            Limpiar búsqueda
                        </button>
                        <button
                            @click="openCreateModal"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-500 transition"
                        >
                            <Plus class="h-4 w-4 stroke-[2.5]" />
                            <span>Crear Subconcesionaria</span>
                        </button>
                    </div>
                </div>

                <!-- ── Pagination Footer ──────────────────────────────── -->
                <div
                    v-if="filteredSubdealerships.length > 0"
                    class="mt-auto border-t border-slate-200/80 bg-slate-50/60 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-medium text-slate-600"
                >
                    <div>
                        Mostrando
                        <span class="font-bold text-slate-900">{{ paginationSummary.from }}</span>
                        a
                        <span class="font-bold text-slate-900">{{ paginationSummary.to }}</span>
                        de
                        <span class="font-bold text-slate-900">{{ paginationSummary.total }}</span>
                        subconcesionaria(s)
                    </div>

                    <!-- Page Navigation -->
                    <div class="flex items-center gap-1">
                        <!-- First Page -->
                        <button
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            title="Primera página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <ChevronsLeft class="h-4 w-4" />
                        </button>

                        <!-- Previous Page -->
                        <button
                            @click="goToPage(currentPage - 1)"
                            :disabled="currentPage === 1"
                            title="Página anterior"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>

                        <!-- Visible Page Numbers -->
                        <template v-for="(p, idx) in visiblePages" :key="idx">
                            <span v-if="p === '...'" class="px-2 text-slate-400">...</span>
                            <button
                                v-else
                                @click="goToPage(p as number)"
                                :class="[
                                    'inline-flex h-8 min-w-8 px-2 items-center justify-center rounded-lg text-xs font-bold transition',
                                    currentPage === p
                                        ? 'bg-blue-600 text-white shadow-xs'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100',
                                ]"
                            >
                                {{ p }}
                            </button>
                        </template>

                        <!-- Next Page -->
                        <button
                            @click="goToPage(currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            title="Página siguiente"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>

                        <!-- Last Page -->
                        <button
                            @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages"
                            title="Última página"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <ChevronsRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <SubdealershipModal
            :showModal="showModal"
            :editingSubdealership="editingSubdealership"
            :existingSubdealerships="props.allSubdealerships"
            @closeModal="closeModal"
            @submitCreate="submitCreate"
            @attachExisting="attachExisting"
        />
    </AppLayout>
</template>

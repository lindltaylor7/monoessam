<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { computed, ref, watch } from 'vue';
import DealershipModal from './DealershipModal.vue';
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
    SearchX,
    SlidersHorizontal,
} from 'lucide-vue-next';

interface DealershipRecord {
    id: number;
    name: string;
    ruc: string | null;
    fiscal_address: string | null;
    legal_address: string | null;
    phone: string | null;
    email: string | null;
    contracts_count?: number;
    mines_count?: number;
}

interface Props {
    dealerships: DealershipRecord[];
}

const props = defineProps<Props>();

const displayedDealerships = ref<DealershipRecord[]>([...(props.dealerships || [])]);

watch(
    () => props.dealerships,
    (val) => {
        displayedDealerships.value = [...(val || [])];
    },
    { immediate: true },
);

// ── Search & Pagination State ──────────────────────────────────────────
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const itemsPerPageOptions = [5, 10, 15, 25, 50, 100];

// Filtered items based on global search
const filteredDealerships = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return displayedDealerships.value;

    return displayedDealerships.value.filter((item) => {
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
    return Math.max(1, Math.ceil(filteredDealerships.value.length / itemsPerPage.value));
});

// Paginated items
const paginatedDealerships = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredDealerships.value.slice(start, end);
});

// Pagination Summary (e.g. 1 to 10 of 45)
const paginationSummary = computed(() => {
    const total = filteredDealerships.value.length;
    if (total === 0) return { from: 0, to: 0, total: 0 };
    const from = (currentPage.value - 1) * itemsPerPage.value + 1;
    const to = Math.min(currentPage.value * itemsPerPage.value, total);
    return { from, to, total };
});

// KPI Quick Stats
const stats = computed(() => {
    const total = displayedDealerships.value.length;
    const withRuc = displayedDealerships.value.filter((d) => !!d.ruc && d.ruc.trim().length > 0).length;
    const withContact = displayedDealerships.value.filter(
        (d) => (!!d.phone && d.phone.trim().length > 0) || (!!d.email && d.email.trim().length > 0),
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
const editingDealership = ref<DealershipRecord | null>(null);

const openCreateModal = () => {
    editingDealership.value = null;
    showModal.value = true;
};

const openEditModal = (dealership: DealershipRecord) => {
    editingDealership.value = dealership;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingDealership.value = null;
};

const submitCreate = (formArg: any) => {
    if (editingDealership.value) {
        formArg.put(route('dealerships.update', editingDealership.value.id), {
            onSuccess: () => {
                closeModal();
                router.reload({ only: ['dealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'La concesionaria se ha actualizado correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        });
    } else {
        formArg.post(route('dealerships.store'), {
            onSuccess: () => {
                closeModal();
                router.reload({ only: ['dealerships'] });
                Swal.fire({
                    icon: 'success',
                    title: '¡Registrado!',
                    text: 'La concesionaria se ha creado correctamente.',
                    timer: 2500,
                    showConfirmButton: false,
                });
            },
        });
    }
};

const deleteDealership = async (dealership: DealershipRecord) => {
    const dependents = (dealership.contracts_count || 0) + (dealership.mines_count || 0);
    const dependentsText =
        dependents > 0
            ? `<br><span class="text-xs text-amber-600 font-medium">Nota: Esta concesionaria tiene ${dependents} registro(s) asociado(s) (contratos o unidades mineras).</span>`
            : '';

    const result = await Swal.fire({
        title: '¿Eliminar concesionaria?',
        html: `¿Estás seguro de eliminar <strong>${dealership.name}</strong>?${dependentsText}<br><span class="text-xs text-slate-500">Esta acción no se puede deshacer.</span>`,
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
        router.delete(route('dealerships.destroy', dealership.id), {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminada!',
                    text: 'La concesionaria ha sido eliminada correctamente.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo eliminar la concesionaria.',
                });
            },
        });
    }
};

const getAvatarColor = (name: string) => {
    const colors = [
        'bg-amber-100 text-amber-700 border-amber-200',
        'bg-emerald-100 text-emerald-700 border-emerald-200',
        'bg-blue-100 text-blue-700 border-blue-200',
        'bg-indigo-100 text-indigo-700 border-indigo-200',
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
    <Head title="Concesionarias" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8 bg-slate-50/50 min-h-screen">
            <!-- ── Page Header ─────────────────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 border border-blue-100 shadow-sm">
                        <Building2 class="h-6 w-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Concesionarias</h1>
                        <p class="text-sm text-slate-500 mt-0.5">Gestión y control de concesionarias asociadas</p>
                    </div>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:bg-blue-500 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4 stroke-[2.5]" />
                    <span>Crear Concesionaria</span>
                </button>
            </div>

            <!-- ── Metrics Quick Stats ─────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0 border border-blue-100">
                        <Building2 class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Concesionarias</p>
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
                        {{ filteredDealerships.length }} resultado(s)
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
                                        <span>Concesionaria</span>
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
                                v-for="dealership in paginatedDealerships"
                                :key="dealership.id"
                                class="hover:bg-blue-50/30 transition-colors duration-150 group"
                            >
                                <!-- Nombre -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="h-9 w-9 rounded-xl flex items-center justify-center font-bold text-xs border shadow-2xs flex-shrink-0"
                                            :class="getAvatarColor(dealership.name)"
                                        >
                                            {{ dealership.name ? dealership.name.charAt(0).toUpperCase() : 'C' }}
                                        </div>
                                        <div>
                                            <span class="text-sm font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">
                                                {{ dealership.name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- RUC -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span
                                        v-if="dealership.ruc"
                                        class="inline-flex items-center gap-1 font-mono text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-200"
                                    >
                                        {{ dealership.ruc }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">Sin RUC</span>
                                </td>

                                <!-- Teléfono -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <a
                                        v-if="dealership.phone"
                                        :href="`tel:${dealership.phone}`"
                                        class="inline-flex items-center gap-1.5 text-slate-700 hover:text-blue-600 transition"
                                    >
                                        {{ dealership.phone }}
                                    </a>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Email -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <a
                                        v-if="dealership.email"
                                        :href="`mailto:${dealership.email}`"
                                        class="inline-flex items-center gap-1.5 text-blue-600 hover:underline transition"
                                    >
                                        {{ dealership.email }}
                                    </a>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Dirección Fiscal -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 max-w-xs truncate">
                                    <span v-if="dealership.fiscal_address" :title="dealership.fiscal_address">
                                        {{ dealership.fiscal_address }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">—</span>
                                </td>

                                <!-- Acciones -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openEditModal(dealership)"
                                            title="Editar Concesionaria"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-amber-600 hover:bg-amber-50 hover:text-amber-700 active:scale-95 transition"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            @click="deleteDealership(dealership)"
                                            title="Eliminar Concesionaria"
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
                <div v-if="filteredDealerships.length === 0" class="py-16 text-center px-4 flex flex-col items-center justify-center my-auto">
                    <div class="h-16 w-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-4">
                        <SearchX v-if="searchQuery" class="h-8 w-8" />
                        <Building2 v-else class="h-8 w-8" />
                    </div>
                    <h3 class="text-base font-bold text-slate-800">
                        {{ searchQuery ? 'No se encontraron resultados' : 'No hay concesionarias registradas' }}
                    </h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm">
                        {{
                            searchQuery
                                ? `No se encontraron coincidencias para "${searchQuery}". Intenta con otros términos.`
                                : 'Comienza creando la primera concesionaria para gestionarla en el sistema.'
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
                            <span>Crear Concesionaria</span>
                        </button>
                    </div>
                </div>

                <!-- ── Pagination Footer ──────────────────────────────── -->
                <div
                    v-if="filteredDealerships.length > 0"
                    class="mt-auto border-t border-slate-200/80 bg-slate-50/60 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-medium text-slate-600"
                >
                    <div>
                        Mostrando
                        <span class="font-bold text-slate-900">{{ paginationSummary.from }}</span>
                        a
                        <span class="font-bold text-slate-900">{{ paginationSummary.to }}</span>
                        de
                        <span class="font-bold text-slate-900">{{ paginationSummary.total }}</span>
                        concesionaria(s)
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

        <DealershipModal
            :showModal="showModal"
            :editingDealership="editingDealership"
            :existingDealerships="props.dealerships"
            @closeModal="closeModal"
            @submitCreate="submitCreate"
        />
    </AppLayout>
</template>

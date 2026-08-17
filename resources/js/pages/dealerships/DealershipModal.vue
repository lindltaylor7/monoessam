<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Dealership {
    id: number;
    name: string;
    ruc?: string | null;
    fiscal_address?: string | null;
    legal_address?: string | null;
    phone?: string | null;
    email?: string | null;
}

const props = defineProps<{
    showModal: boolean;
    editingDealership?: Dealership | null;
    existingDealerships?: Dealership[];
}>();

const emit = defineEmits(['closeModal', 'submitCreate']);

const form = useForm({
    name: '',
    ruc: '',
    phone: '',
    email: '',
    fiscal_address: '',
    legal_address: '',
});

watch(
    () => props.editingDealership,
    (dealership) => {
        if (dealership) {
            form.name = dealership.name ?? '';
            form.ruc = dealership.ruc ?? '';
            form.phone = dealership.phone ?? '';
            form.email = dealership.email ?? '';
            form.fiscal_address = dealership.fiscal_address ?? '';
            form.legal_address = dealership.legal_address ?? '';
        } else {
            form.reset();
        }
    },
    { immediate: true },
);

watch(
    () => props.showModal,
    (isOpen) => {
        if (!isOpen) {
            form.reset();
            form.clearErrors();
        }
    },
);

// ── Levenshtein Distance & Fuzzy Similarity Search ──────────────────────
function levenshteinDistance(a: string, b: string): number {
    const lenA = a.length;
    const lenB = b.length;
    if (lenA === 0) return lenB;
    if (lenB === 0) return lenA;

    const row = new Array(lenB + 1);
    for (let j = 0; j <= lenB; j++) row[j] = j;

    for (let i = 1; i <= lenA; i++) {
        let prev = i;
        for (let j = 1; j <= lenB; j++) {
            const val = a[i - 1] === b[j - 1] ? row[j - 1] : Math.min(row[j - 1] + 1, prev + 1, row[j] + 1);
            row[j - 1] = prev;
            prev = val;
        }
        row[lenB] = prev;
    }

    return row[lenB];
}

function normalizeName(str: string): string {
    return (str || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\b(s\.?a\.?c?|e\.?i\.?r\.?l\.?|s\.?r\.?l\.?|s\.?a\.?)\b/g, '')
        .replace(/[^a-z0-9]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function calculateSimilarity(rawA: string, rawB: string): number {
    const normA = normalizeName(rawA);
    const normB = normalizeName(rawB);

    if (!normA || !normB) return 0;
    if (normA === normB) return 1.0;

    if (normA.length >= 4 && normB.length >= 4) {
        if (normA.includes(normB) || normB.includes(normA)) {
            const ratio = Math.min(normA.length, normB.length) / Math.max(normA.length, normB.length);
            if (ratio >= 0.5) return Math.max(0.85, ratio);
        }
    }

    const wordsA = normA.split(' ').filter((w) => w.length > 1);
    const wordsB = normB.split(' ').filter((w) => w.length > 1);
    if (wordsA.length > 0 && wordsB.length > 0) {
        const intersection = wordsA.filter((w) =>
            wordsB.some((wb) => wb === w || (w.length > 3 && wb.includes(w)) || (wb.length > 3 && w.includes(wb))),
        );
        const union = new Set([...wordsA, ...wordsB]);
        const jaccard = intersection.length / union.size;
        if (jaccard >= 0.6) return Math.max(0.8, jaccard);
    }

    const distance = levenshteinDistance(normA, normB);
    const maxLen = Math.max(normA.length, normB.length);
    return 1 - distance / maxLen;
}

interface SimilarMatch {
    dealership: Dealership;
    similarity: number;
    isExact: boolean;
}

const similarMatches = computed<SimilarMatch[]>(() => {
    if (props.editingDealership) return [];
    const trimmed = (form.name ?? '').trim();
    if (trimmed.length < 3) return [];

    const matches: SimilarMatch[] = [];

    for (const d of props.existingDealerships || []) {
        const name = (d.name ?? '').trim();
        if (!name) continue;

        const sim = calculateSimilarity(trimmed, name);
        const isExact = normalizeName(trimmed) === normalizeName(name);

        if (isExact || sim >= 0.6) {
            matches.push({
                dealership: d,
                similarity: Math.round(sim * 100),
                isExact,
            });
        }
    }

    return matches.sort((a, b) => b.similarity - a.similarity);
});

const hasExactMatch = computed(() => {
    return similarMatches.value.some((m) => m.isExact || m.similarity >= 95);
});

const closeModal = () => {
    emit('closeModal');
    form.reset();
    form.clearErrors();
};

const submitCreate = () => {
    if (hasExactMatch.value) return;
    emit('submitCreate', form);
};
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal" />

            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95 translate-y-2"
                appear
            >
                <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <!-- Header -->
                    <div class="relative bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6 text-white">
                        <div class="pr-8">
                            <h3 class="text-xl font-bold tracking-tight">
                                {{ editingDealership ? 'Editar Concesionaria' : 'Crear Concesionaria' }}
                            </h3>
                            <p class="mt-0.5 text-sm text-blue-100/80">
                                {{ editingDealership ? 'Actualiza la información de la concesionaria' : 'Completa los datos del nuevo registro' }}
                            </p>
                        </div>
                        <button
                            @click="closeModal"
                            class="absolute top-4 right-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white transition hover:bg-white/30"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="absolute right-6 -bottom-5 flex h-10 w-10 items-center justify-center rounded-full border-4 border-white bg-blue-600 shadow-md">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                            </svg>
                        </div>
                    </div>

                    <!-- Form body -->
                    <div class="mt-2 px-6 pt-7 pb-6">
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <!-- Nombre (full width) -->
                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">
                                    Nombre <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Ej. Concesionaria Minera del Centro S.A."
                                    class="w-full rounded-lg border px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:ring-2 focus:outline-none"
                                    :class="similarMatches.length > 0
                                        ? 'border-amber-400 bg-amber-50 focus:border-amber-400 focus:ring-amber-100'
                                        : form.errors.name
                                            ? 'border-red-400 bg-red-50 focus:border-red-400 focus:ring-red-100'
                                            : 'border-slate-200 bg-white focus:border-blue-400 focus:ring-blue-100'"
                                />

                                <!-- Similar / Duplicate warning -->
                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 -translate-y-1"
                                >
                                    <div v-if="similarMatches.length > 0" class="mt-2.5 rounded-xl border border-amber-200 bg-amber-50/90 p-3.5 shadow-xs">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 flex-shrink-0 text-amber-500">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-bold text-amber-900">
                                                    {{ hasExactMatch ? 'Ya existe una concesionaria con nombre similar o idéntico:' : 'Se encontraron registros parecidos:' }}
                                                </p>
                                                <div class="mt-2 space-y-2">
                                                    <div
                                                        v-for="match in similarMatches.slice(0, 3)"
                                                        :key="match.dealership.id"
                                                        class="flex items-center justify-between gap-3 rounded-lg bg-white/90 border border-amber-200/80 px-3 py-2 text-xs shadow-2xs"
                                                    >
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-semibold text-slate-900 truncate">{{ match.dealership.name }}</span>
                                                                <span
                                                                    class="flex-shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                                    :class="match.similarity >= 85 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                                                                >
                                                                    {{ match.similarity }}% similitud
                                                                </span>
                                                            </div>
                                                            <p v-if="match.dealership.ruc" class="text-[11px] text-slate-500 mt-0.5 font-mono">
                                                                RUC: {{ match.dealership.ruc }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>

                                <p v-if="form.errors.name && similarMatches.length === 0" class="mt-1.5 flex items-center gap-1 text-xs font-semibold text-red-500">
                                    <svg class="h-3.5 w-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- RUC -->
                            <div>
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">RUC</label>
                                <input
                                    v-model="form.ruc"
                                    type="text"
                                    placeholder="20123456789"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                    :class="{ 'border-red-400 bg-red-50': form.errors.ruc }"
                                />
                                <p v-if="form.errors.ruc" class="mt-1.5 text-xs font-semibold text-red-500">{{ form.errors.ruc }}</p>
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">Teléfono</label>
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="+51 999 999 999"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                />
                            </div>

                            <!-- Email (full width) -->
                            <div class="md:col-span-2">
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="contacto@concesionaria.com"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                    :class="{ 'border-red-400 bg-red-50': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="mt-1.5 text-xs font-semibold text-red-500">{{ form.errors.email }}</p>
                            </div>

                            <!-- Dirección Fiscal -->
                            <div>
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">Dirección Fiscal</label>
                                <textarea
                                    v-model="form.fiscal_address"
                                    rows="3"
                                    placeholder="Av. Industrial 123, Lima"
                                    class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                />
                            </div>

                            <!-- Dirección Legal -->
                            <div>
                                <label class="mb-1.5 block text-xs font-bold tracking-wider text-slate-500 uppercase">Dirección Legal</label>
                                <textarea
                                    v-model="form.legal_address"
                                    rows="3"
                                    placeholder="Av. Comercial 456, Lima"
                                    class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm transition placeholder:text-slate-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="mt-7 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-95"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                :disabled="form.processing"
                                @click="submitCreate"
                                class="flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ editingDealership ? 'Guardar Cambios' : 'Crear Concesionaria' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

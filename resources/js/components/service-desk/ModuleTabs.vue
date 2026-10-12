<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, ClipboardCheck, Gauge, Settings2, Ticket, UserRoundCheck } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Navegación entre las cinco pantallas del módulo. El sidebar solo enlaza la raíz
 * (`/service-desk`), porque se arma con una fila de `permissions` por módulo.
 */
const page = usePage();

const tabs = [
    { label: 'Incidencias', href: '/service-desk', name: 'service-desk.index', icon: Ticket, exact: true },
    { label: 'Indicadores', href: '/service-desk/indicators', name: 'service-desk.indicators.index', icon: Gauge, exact: false },
    { label: 'KEDB', href: '/service-desk/kedb', name: 'service-desk.kedb.index', icon: BookOpenCheck, exact: false },
    { label: 'Auditoría', href: '/service-desk/audits', name: 'service-desk.audits.index', icon: ClipboardCheck, exact: false },
    { label: 'Cuestionarios', href: '/service-desk/surveys', name: 'service-desk.surveys.index', icon: UserRoundCheck, exact: false },
    { label: 'Configuración', href: '/service-desk/config', name: 'service-desk.config.index', icon: Settings2, exact: false },
];

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (tab: (typeof tabs)[number]) => (tab.exact ? currentPath.value === tab.href : currentPath.value.startsWith(tab.href));
</script>

<template>
    <nav class="bg-card flex flex-wrap items-center gap-1 rounded-xl border p-1.5 shadow-sm">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="isActive(tab) ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'"
        >
            <component :is="tab.icon" class="h-4 w-4" />
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link as InertiaLink } from '@inertiajs/vue3';
import {
    Building2,
    Calendar,
    Clock,
    Coffee,
    Eye,
    Moon,
    Printer,
    Sunrise,
    Trash2,
    Utensils,
    UserCheck,
    UserRound,
} from 'lucide-vue-next';

interface TicketDetail {
    id: number;
    service_name: string;
    service_type: number;
    unit_price: number;
    total: number;
    amount: number;
}

interface Ticket {
    id: number;
    dinner_name: string;
    dni: string;
    subdealership_name: string;
    ticket_details: TicketDetail[];
}

interface Cafe {
    id: number;
    name: string;
    unit?: { id: number; name: string };
}

interface Sale {
    id: number;
    date: string;
    created_at: string;
    total: number;
    is_visitor: boolean;
    cafe: Cafe;
    tickets: Ticket[];
}

const props = defineProps<{
    sales: Sale[];
    paginateData: any;
}>();

const emit = defineEmits<{
    deleteSale:   [saleId: number];
    deleteDetail: [detailId: number];
}>();

const sendToPrint = (saleId: number) => {
    window.open('/print-ticket/' + saleId + '/1', '_blank');
};

const translatePaginationLabel = (label: string) => {
    if (label.includes('Previous')) return 'Anterior';
    if (label.includes('Next')) return 'Siguiente';
    return label;
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString.replace(/-/g, '/'));
    return date.toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatTime = (datetimeString: string): string => {
    if (!datetimeString) return '—';
    const date = new Date(datetimeString);
    return date.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });
};

type ServiceConfig = { label: string; bgClass: string; textClass: string; borderClass: string; dotClass: string; icon: any };

const SERVICE_CONFIG: Record<number, ServiceConfig> = {
    1: { label: 'Desayuno',  bgClass: 'bg-amber-50',   textClass: 'text-amber-700',  borderClass: 'border-amber-200', dotClass: 'bg-amber-400',   icon: Sunrise },
    2: { label: 'Almuerzo',  bgClass: 'bg-blue-50',    textClass: 'text-blue-700',   borderClass: 'border-blue-200',  dotClass: 'bg-blue-400',    icon: Utensils },
    3: { label: 'Cena',      bgClass: 'bg-indigo-50',  textClass: 'text-indigo-700', borderClass: 'border-indigo-200',dotClass: 'bg-indigo-400',  icon: Moon },
    4: { label: 'Refrigerio',bgClass: 'bg-emerald-50', textClass: 'text-emerald-700',borderClass: 'border-emerald-200',dotClass: 'bg-emerald-400', icon: Coffee },
};

const getServiceCfg = (type: number): ServiceConfig =>
    SERVICE_CONFIG[type] ?? { label: 'Servicio', bgClass: 'bg-slate-50', textClass: 'text-slate-700', borderClass: 'border-slate-200', dotClass: 'bg-slate-400', icon: Utensils };

const AVATAR_GRADIENTS = [
    'from-blue-500 to-indigo-600',
    'from-emerald-500 to-teal-600',
    'from-violet-500 to-purple-600',
    'from-amber-500 to-orange-600',
    'from-rose-500 to-pink-600',
];

const getGradient = (id: number) => AVATAR_GRADIENTS[id % AVATAR_GRADIENTS.length];

const getInitials = (name: string) =>
    (name || '?').split(' ').slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase();
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow class="border-b border-slate-200 bg-slate-50 hover:bg-slate-50">
                    <TableHead class="w-36 py-3.5 pl-5 text-[10px] font-bold tracking-widest text-slate-500 uppercase">Fecha</TableHead>
                    <TableHead class="w-36 py-3.5 text-[10px] font-bold tracking-widest text-slate-500 uppercase">Cafetería</TableHead>
                    <TableHead class="py-3.5 text-[10px] font-bold tracking-widest text-slate-500 uppercase">Comensal</TableHead>
                    <TableHead class="py-3.5 text-[10px] font-bold tracking-widest text-slate-500 uppercase">Servicios consumidos</TableHead>
                    <TableHead class="w-28 py-3.5 text-right text-[10px] font-bold tracking-widest text-slate-500 uppercase">Total</TableHead>
                    <TableHead class="w-28 py-3.5 pr-4 text-right text-[10px] font-bold tracking-widest text-slate-500 uppercase">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow
                    v-for="sale in props.sales"
                    :key="sale.id"
                    class="group border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70"
                >
                    <!-- ── Fecha ── -->
                    <TableCell class="py-4 pl-5 align-top">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-1.5">
                                <Calendar class="size-3.5 shrink-0 text-slate-400" />
                                <span class="text-[13px] font-bold text-slate-800">{{ formatDate(sale.date) }}</span>
                            </div>
                            <span class="mt-0.5 text-[9px] font-semibold tracking-wider text-slate-300 uppercase">#{{ sale.id }}</span>
                        </div>
                    </TableCell>

                    <!-- ── Cafetería ── -->
                    <TableCell class="py-4 align-top">
                        <div class="flex flex-col gap-0.5">
                            <div class="flex items-center gap-1.5">
                                <Building2 class="size-3.5 shrink-0 text-slate-400" />
                                <span class="text-[13px] font-bold text-slate-700">{{ sale.cafe.name }}</span>
                            </div>
                            <span v-if="sale.cafe.unit" class="pl-5 text-[10px] font-medium text-slate-400">
                                {{ sale.cafe.unit.name }}
                            </span>
                        </div>
                    </TableCell>

                    <!-- ── Comensal ── -->
                    <TableCell class="py-4 align-top">
                        <div class="flex items-start gap-2.5">
                            <!-- Avatar -->
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-[11px] font-black text-white shadow-sm"
                                :class="getGradient(sale.id)"
                            >
                                {{ getInitials(sale?.tickets[0]?.dinner_name) }}
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <span class="block max-w-[160px] truncate text-[13px] font-bold text-slate-800"
                                    :title="sale?.tickets[0]?.dinner_name">
                                    {{ sale?.tickets[0]?.dinner_name || 'Sin nombre' }}
                                </span>
                                <!-- DNI -->
                                <div class="flex items-center gap-1">
                                    <UserRound class="size-3 text-slate-300" />
                                    <span class="text-[11px] font-mono font-semibold text-slate-400">
                                        {{ sale?.tickets[0]?.dni || '—' }}
                                    </span>
                                </div>
                                <!-- Tags -->
                                <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                    <span v-if="sale.is_visitor"
                                        class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-bold tracking-wide text-violet-600">
                                        <UserCheck class="size-2.5" /> VISITANTE
                                    </span>
                                    <span v-if="sale?.tickets[0]?.subdealership_name"
                                        class="max-w-[130px] truncate rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold text-slate-500"
                                        :title="sale?.tickets[0]?.subdealership_name">
                                        {{ sale?.tickets[0]?.subdealership_name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </TableCell>

                    <!-- ── Servicios ── -->
                    <TableCell class="py-4 align-top">
                        <div class="space-y-1.5">
                            <template v-for="detail in sale?.tickets[0]?.ticket_details" :key="detail.id">
                                <div class="group/detail flex items-stretch gap-1">
                                    <div
                                        class="flex flex-1 items-stretch overflow-hidden rounded-lg border"
                                        :class="getServiceCfg(detail.service_type).borderClass"
                                    >
                                        <!-- Hora -->
                                        <div
                                            class="flex items-center gap-1 border-r px-2 py-1.5"
                                            :class="[getServiceCfg(detail.service_type).borderClass, getServiceCfg(detail.service_type).bgClass]"
                                        >
                                            <Clock class="size-3 shrink-0 text-slate-400" />
                                            <span class="text-[10px] font-semibold tabular-nums text-slate-500">
                                                {{ formatTime(sale.created_at) }}
                                            </span>
                                        </div>
                                        <!-- Servicio -->
                                        <div
                                            class="flex flex-1 items-center gap-2 px-2.5 py-1.5"
                                            :class="getServiceCfg(detail.service_type).bgClass"
                                        >
                                            <component
                                                :is="getServiceCfg(detail.service_type).icon"
                                                class="size-3.5 shrink-0"
                                                :class="getServiceCfg(detail.service_type).textClass"
                                            />
                                            <span
                                                class="flex-1 truncate text-[12px] font-semibold"
                                                :class="getServiceCfg(detail.service_type).textClass"
                                            >
                                                {{ detail.service_name }}
                                            </span>
                                            <span
                                                class="shrink-0 text-[12px] font-black"
                                                :class="getServiceCfg(detail.service_type).textClass"
                                            >
                                                S/{{ Number(detail.unit_price).toFixed(2) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Botón eliminar ítem (solo si hay más de 1) -->
                                    <button
                                        v-if="(sale?.tickets[0]?.ticket_details?.length ?? 0) > 1"
                                        class="flex shrink-0 items-center justify-center rounded-lg border border-transparent px-1.5 text-slate-300 opacity-0 transition-all group-hover/detail:border-red-100 group-hover/detail:bg-red-50 group-hover/detail:text-red-400 group-hover/detail:opacity-100"
                                        title="Eliminar ítem"
                                        @click="emit('deleteDetail', detail.id)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                            <span
                                v-if="!sale?.tickets[0]?.ticket_details?.length"
                                class="text-[11px] italic text-slate-300"
                            >Sin detalle</span>
                        </div>
                    </TableCell>

                    <!-- ── Total ── -->
                    <TableCell class="py-4 align-top text-right">
                        <div class="inline-flex flex-col items-end gap-0.5">
                            <span class="text-[15px] font-black text-emerald-600">
                                S/{{ Number(sale.total).toFixed(2) }}
                            </span>
                            <span class="text-[9px] font-semibold tracking-wider text-slate-300 uppercase">
                                {{ sale?.tickets[0]?.ticket_details?.length ?? 0 }} ítem(s)
                            </span>
                        </div>
                    </TableCell>

                    <!-- ── Acciones ── -->
                    <TableCell class="py-4 pr-4 align-top text-right">
                        <div class="flex items-center justify-end gap-1">
                            <!-- Imprimir -->
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="sendToPrint(sale.id)"
                                title="Imprimir Ticket"
                                class="h-8 w-8 rounded-lg text-slate-400 transition-colors hover:bg-blue-50 hover:text-blue-600"
                            >
                                <Printer class="size-3.5" />
                            </Button>

                            <!-- Detalles -->
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Ver Detalles"
                                        class="h-8 w-8 rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-80 p-0" align="end">
                                    <div class="rounded-xl overflow-hidden">
                                        <!-- Header del popover -->
                                        <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-4 py-3">
                                            <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Venta #{{ sale.id }}</p>
                                            <h4 class="mt-0.5 text-sm font-bold text-white">{{ sale?.tickets[0]?.dinner_name || 'Visitante' }}</h4>
                                        </div>
                                        <!-- Body del popover -->
                                        <div class="space-y-3 p-4">
                                            <div class="grid grid-cols-2 gap-2 text-[12px]">
                                                <div>
                                                    <p class="text-slate-400">Fecha</p>
                                                    <p class="font-bold text-slate-700">{{ formatDate(sale.date) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-400">Hora</p>
                                                    <p class="font-bold text-slate-700">{{ formatTime(sale.created_at) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-400">Cafetería</p>
                                                    <p class="font-bold text-slate-700">{{ sale.cafe.name }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-400">DNI</p>
                                                    <p class="font-bold font-mono text-slate-700">{{ sale?.tickets[0]?.dni || '—' }}</p>
                                                </div>
                                                <div v-if="sale?.tickets[0]?.subdealership_name" class="col-span-2">
                                                    <p class="text-slate-400">Empresa</p>
                                                    <p class="font-bold text-slate-700">{{ sale?.tickets[0]?.subdealership_name }}</p>
                                                </div>
                                            </div>

                                            <!-- Detalle de servicios -->
                                            <div v-if="sale?.tickets[0]?.ticket_details?.length" class="space-y-1.5 border-t pt-3">
                                                <p class="text-[10px] font-bold tracking-wider text-slate-400 uppercase">Servicios</p>
                                                <div
                                                    v-for="d in sale?.tickets[0]?.ticket_details"
                                                    :key="d.id"
                                                    class="flex items-center justify-between rounded-lg border px-2.5 py-1.5"
                                                    :class="[getServiceCfg(d.service_type).bgClass, getServiceCfg(d.service_type).borderClass]"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <component :is="getServiceCfg(d.service_type).icon" class="size-3.5" :class="getServiceCfg(d.service_type).textClass" />
                                                        <span class="text-[12px] font-semibold" :class="getServiceCfg(d.service_type).textClass">{{ d.service_name }}</span>
                                                    </div>
                                                    <span class="text-[12px] font-black" :class="getServiceCfg(d.service_type).textClass">S/{{ Number(d.unit_price).toFixed(2) }}</span>
                                                </div>
                                            </div>

                                            <!-- Total -->
                                            <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-3 py-2.5 border border-emerald-200">
                                                <span class="text-[12px] font-bold text-emerald-700">Total</span>
                                                <span class="text-[16px] font-black text-emerald-600">S/{{ Number(sale.total).toFixed(2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>

                            <!-- Eliminar -->
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="emit('deleteSale', sale.id)"
                                title="Eliminar Venta"
                                class="h-8 w-8 rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>

                <!-- Empty state -->
                <TableRow v-if="props.sales.length === 0">
                    <TableCell colspan="6" class="h-36 text-center">
                        <div class="flex flex-col items-center gap-2 text-slate-400">
                            <Utensils class="size-8 opacity-30" />
                            <p class="text-sm font-medium">No hay ventas registradas</p>
                            <p class="text-xs">Ajusta los filtros para ver resultados</p>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
        <div
            v-if="paginateData.links && paginateData.links.length > 3"
            class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-5 py-3"
        >
            <span class="text-[11px] font-medium text-slate-400">
                Mostrando {{ paginateData.from }}–{{ paginateData.to }} de {{ paginateData.total }}
            </span>
            <div class="flex items-center gap-1">
                <template v-for="link in paginateData.links" :key="link.label">
                    <InertiaLink
                        v-if="link.url"
                        :href="link.url"
                        class="flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2.5 text-[11px] font-bold shadow-sm transition-all"
                        :class="{
                            'border-blue-500 bg-blue-500 text-white shadow-blue-200': link.active,
                            'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50': !link.active,
                        }"
                        preserve-scroll
                    >{{ translatePaginationLabel(link.label) }}</InertiaLink>
                    <span
                        v-else
                        class="pointer-events-none flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2.5 text-[11px] font-bold text-slate-300"
                    >{{ translatePaginationLabel(link.label) }}</span>
                </template>
            </div>
        </div>
    </div>
</template>

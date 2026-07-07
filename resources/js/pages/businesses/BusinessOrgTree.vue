<script setup lang="ts">
import AreaModal from '../headcount/AreaModal.vue';
import CoordEditor from '../management/CoordEditor.vue';
import InsertHeadquarterModal from './InsertHeadquarterModal.vue';
import ServicePopover from './ServicePopover.vue';
import axios from 'axios';
import { Building2, ImageUp, LayoutGrid, MapPin, Trash } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface BusinessNode {
    id: number;
    name: string;
    description?: string;
    logo?: string | null;
    services?: { id: number }[];
}

interface Area {
    id: number;
    name: string;
}

interface HQNode {
    id: number;
    name: string;
    business_id?: number;
    business?: { id: number; name: string };
    latitude?: number | null;
    longitude?: number | null;
    address?: string | null;
    areas: Area[];
}

const props = defineProps<{
    business: BusinessNode;
    headquarters: HQNode[];
    allHeadquarters: HQNode[];
    services: unknown[];
}>();

const sedes = computed(() => props.headquarters.filter((h) => h.business?.id === props.business.id));

function onHQCoordUpdated(hq: HQNode, lat: number | null, lng: number | null, address: string | null) {
    hq.latitude = lat;
    hq.longitude = lng;
    hq.address = address;
}

const uploading = ref(false);

const triggerLogoUpload = () => {
    const input = document.getElementById(`logo-input-${props.business.id}`) as HTMLInputElement;
    input?.click();
};

const handleLogoUpload = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('logo', file);

    uploading.value = true;
    try {
        const response = await axios.post(`/businesses/${props.business.id}/logo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (response.data?.logo) props.business.logo = response.data.logo;
        (event.target as HTMLInputElement).value = '';
    } catch {
        // silently ignore
    } finally {
        uploading.value = false;
    }
};
</script>

<template>
    <div class="org-tree">
        <ul>
            <li>
                <!-- ── BUSINESS (root) ─────────────────────────────────────── -->
                <div class="node-card node-business group">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-indigo-50">
                            <img v-if="business.logo" :src="`/storage/${business.logo}`" class="h-full w-full object-contain" :alt="business.name" />
                            <Building2 v-else class="h-5 w-5 text-indigo-600" />
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="truncate text-sm font-bold text-zinc-900">{{ business.name }}</p>
                            <p v-if="business.description" class="truncate text-xs text-zinc-500">{{ business.description }}</p>
                            <p v-else class="text-xs text-zinc-400 italic">Empresa</p>
                        </div>
                    </div>

                    <div class="node-actions">
                        <input :id="`logo-input-${business.id}`" type="file" accept="image/*" class="hidden" @change="handleLogoUpload" />
                        <button class="node-action-btn" :title="business.logo ? 'Cambiar logo' : 'Subir logo'" :disabled="uploading" @click="triggerLogoUpload">
                            <ImageUp class="h-3.5 w-3.5" :class="{ 'animate-pulse': uploading }" />
                        </button>
                        <ServicePopover :services="services" :business="business" />
                        <InsertHeadquarterModal :business-id="business.id" />
                    </div>
                </div>

                <!-- ── SEDES (children) ────────────────────────────────────── -->
                <ul v-if="sedes.length > 0">
                    <li v-for="hq in sedes" :key="hq.id">
                        <div class="node-card node-sede group">
                            <div class="flex items-start gap-2.5">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50">
                                    <MapPin class="h-4 w-4 text-violet-600" />
                                </div>
                                <div class="min-w-0 flex-1 text-left">
                                    <p class="truncate text-sm font-bold text-zinc-900">{{ hq.name }}</p>
                                    <CoordEditor
                                        :latitude="hq.latitude ?? null"
                                        :longitude="hq.longitude ?? null"
                                        :address="hq.address ?? null"
                                        :patch-url="`/headquarters/${hq.id}`"
                                        @updated="(lat, lng, addr) => onHQCoordUpdated(hq, lat, lng, addr)"
                                    />
                                </div>
                            </div>

                            <div class="node-actions">
                                <AreaModal :headquarters="allHeadquarters as any" :headquarterId="hq.id" />
                                <button class="node-action-btn node-action-danger" title="Eliminar sede">
                                    <Trash class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- ── AREAS (grandchildren) ───────────────────────── -->
                        <ul v-if="hq.areas && hq.areas.length > 0">
                            <li v-for="area in hq.areas" :key="area.id">
                                <div class="node-card node-area">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50">
                                            <LayoutGrid class="h-3.5 w-3.5 text-emerald-600" />
                                        </div>
                                        <div class="min-w-0 text-left">
                                            <p class="truncate text-xs font-bold text-zinc-900">{{ area.name }}</p>
                                            <p class="text-[10px] text-zinc-400">ID: {{ area.id }}</p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <ul v-else>
                            <li>
                                <div class="node-card node-area node-area-empty">
                                    <p class="text-[11px] text-zinc-400 italic">Sin áreas</p>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul v-else>
                    <li>
                        <div class="node-card node-sede node-sede-empty">
                            <p class="text-xs text-zinc-400 italic">Sin sedes registradas</p>
                        </div>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>

<style scoped>
/* Pure-CSS org-chart tree: classic ::before/::after connector trick. */
.org-tree,
.org-tree ul,
.org-tree li {
    margin: 0;
    padding: 0;
    list-style: none;
    position: relative;
}

.org-tree {
    display: inline-flex;
    justify-content: center;
    min-width: 100%;
    padding: 8px 24px 24px;
}

.org-tree ul {
    display: flex;
    padding-top: 36px;
}

.org-tree li {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 36px 14px 0 14px;
    position: relative;
}

.org-tree li::before,
.org-tree li::after {
    content: '';
    position: absolute;
    top: 0;
    right: 50%;
    width: 50%;
    height: 36px;
    border-top: 2px solid #d4d4d8;
}

.org-tree li::after {
    right: auto;
    left: 50%;
    border-left: 2px solid #d4d4d8;
}

.org-tree li:only-child::before,
.org-tree li:only-child::after {
    display: none;
}

.org-tree li:only-child {
    padding-top: 0;
}

.org-tree li:first-child::before,
.org-tree li:last-child::after {
    border: 0 none;
}

.org-tree li:last-child::before {
    border-right: 2px solid #d4d4d8;
    border-radius: 0 6px 0 0;
}

.org-tree li:first-child::after {
    border-radius: 6px 0 0 0;
}

.org-tree ul ul::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 0;
    height: 36px;
    border-left: 2px solid #d4d4d8;
}

/* ── Node cards ─────────────────────────────────────────────────────────── */
.node-card {
    position: relative;
    width: 210px;
    background: white;
    border: 1px solid #e4e4e7;
    border-radius: 0.875rem;
    padding: 0.75rem 0.85rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.node-card:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.node-business {
    width: 230px;
    border-top: 3px solid #4f46e5;
}

.node-sede {
    border-top: 3px solid #7c3aed;
}

.node-area {
    width: 170px;
    border-top: 3px solid #059669;
    padding: 0.5rem 0.65rem;
}

.node-sede-empty,
.node-area-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    border-style: dashed;
    border-top-style: dashed;
    background: #fafafa;
    box-shadow: none;
    min-height: 44px;
}

.node-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    margin-top: 0.5rem;
    opacity: 0;
    transform: translateY(-2px);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.node-card:hover .node-actions {
    opacity: 1;
    transform: translateY(0);
}

.node-action-btn {
    display: inline-flex;
    height: 1.75rem;
    width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    color: #52525b;
    background: #f4f4f5;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.node-action-btn:hover {
    background: #e0e7ff;
    color: #4338ca;
}

.node-action-danger:hover {
    background: #fee2e2;
    color: #b91c1c;
}
</style>

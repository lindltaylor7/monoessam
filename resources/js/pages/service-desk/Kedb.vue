<script setup lang="ts">
import ModuleTabs from '@/components/service-desk/ModuleTabs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/service-desk';
import type { SdKedbArticle, SdNamed } from '@/types/service-desk';
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookOpenCheck, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    articles: SdKedbArticle[];
    categories: SdNamed[];
    filters: { search: string };
    statuses: string[];
}>();

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('service-desk.kedb.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
});

const STATUS_LABELS: Record<string, string> = { borrador: 'Borrador', publicado: 'Publicado', retirado: 'Retirado' };

const statusClass = (status: string) =>
    ({
        publicado: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        borrador: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        retirado: 'bg-zinc-100 text-zinc-500 ring-zinc-400/20',
    })[status] ?? 'bg-zinc-100 text-zinc-500 ring-zinc-400/20';

const showModal = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    symptoms: '',
    root_cause: '',
    workaround: '',
    solution: '',
    category_id: '' as number | string,
    status: 'publicado',
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEdit = (article: SdKedbArticle) => {
    editingId.value = article.id;
    form.title = article.title;
    form.symptoms = article.symptoms ?? '';
    form.root_cause = article.root_cause ?? '';
    form.workaround = article.workaround ?? '';
    form.solution = article.solution ?? '';
    form.category_id = article.category_id ?? '';
    form.status = article.status;
    showModal.value = true;
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (showModal.value = false) };
    if (editingId.value) {
        form.put(route('service-desk.kedb.update', editingId.value), options);
    } else {
        form.post(route('service-desk.kedb.store'), options);
    }
};

const destroy = (article: SdKedbArticle) => {
    if (!confirm(`¿Eliminar el artículo ${article.code}? Si tiene tickets asociados solo se retirará.`)) return;
    router.delete(route('service-desk.kedb.destroy', article.id), { preserveScroll: true });
};

const published = computed(() => props.articles.filter((a) => a.status === 'publicado').length);
const totalUses = computed(() => props.articles.reduce((sum, a) => sum + (a.times_used ?? 0), 0));
</script>

<template>
    <Head title="KEDB · Service Desk" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 pb-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Base de errores conocidos (KEDB)</h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Soluciones documentadas y soluciones temporales. Cada vez que un ticket se resuelve con un artículo, suma al indicador de uso
                        de la base de conocimientos.
                    </p>
                </div>
                <Button class="gap-2 bg-indigo-600 text-white hover:bg-indigo-700" @click="openCreate">
                    <Plus class="h-4 w-4" /> Nuevo artículo
                </Button>
            </div>

            <ModuleTabs />

            <div class="bg-card flex flex-wrap items-center gap-3 rounded-xl border p-4 shadow-sm">
                <div class="relative max-w-sm min-w-[220px] flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                    <Input v-model="search" placeholder="Buscar por código, título o síntoma…" class="pl-9" />
                </div>
                <div class="text-muted-foreground ml-auto flex items-center gap-4 text-sm">
                    <span>{{ articles.length }} artículo(s)</span>
                    <span>{{ published }} publicado(s)</span>
                    <span>{{ totalUses }} uso(s) acumulado(s)</span>
                </div>
            </div>

            <div class="bg-card overflow-hidden rounded-xl border shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Artículo</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Categoría</th>
                                <th class="p-4 text-left text-xs font-bold tracking-wider text-zinc-500 uppercase">Solución / Workaround</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Estado</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Usos</th>
                                <th class="p-4 text-center text-xs font-bold tracking-wider text-zinc-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="article in articles" :key="article.id" class="hover:bg-muted/30 border-t align-top transition-colors">
                                <td class="p-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                            <BookOpenCheck class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs text-zinc-500">{{ article.code }}</p>
                                            <p class="text-sm font-semibold text-zinc-900">{{ article.title }}</p>
                                            <p v-if="article.symptoms" class="mt-0.5 line-clamp-2 text-xs text-zinc-500">{{ article.symptoms }}</p>
                                            <p class="mt-0.5 text-[11px] text-zinc-400">
                                                {{ article.author?.name ?? 'Sin autor' }} · {{ formatDate(article.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-zinc-700">{{ article.category?.name ?? '—' }}</td>
                                <td class="max-w-sm p-4 text-sm text-zinc-600">
                                    <p class="line-clamp-2">{{ article.solution ?? '—' }}</p>
                                    <p v-if="article.workaround" class="mt-1 line-clamp-1 text-xs text-amber-700">
                                        Temporal: {{ article.workaround }}
                                    </p>
                                </td>
                                <td class="p-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                        :class="statusClass(article.status)"
                                    >
                                        {{ STATUS_LABELS[article.status] ?? article.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-sm font-bold text-emerald-700">
                                        {{ article.times_used }}
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-indigo-50 hover:text-indigo-600"
                                            title="Editar"
                                            @click="openEdit(article)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-zinc-500 hover:bg-red-50 hover:text-red-600"
                                            title="Eliminar"
                                            @click="destroy(article)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="articles.length === 0">
                                <td colspan="6" class="p-12 text-center">
                                    <div class="flex flex-col items-center gap-3 text-zinc-400">
                                        <BookOpenCheck class="h-10 w-10 opacity-30" />
                                        <p class="font-medium">KEDB vacía</p>
                                        <p class="text-sm">Documente el primer error conocido para empezar a medir su uso.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showModal">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[640px]">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Editar artículo' : 'Nuevo artículo de la KEDB' }}</DialogTitle>
                </DialogHeader>

                <form class="mt-2 space-y-4" @submit.prevent="save">
                    <div class="space-y-1.5">
                        <Label>Título <span class="text-red-500">*</span></Label>
                        <Input v-model="form.title" placeholder="Ej. Impresora de guías no responde tras reinicio" />
                        <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Categoría</Label>
                            <select v-model="form.category_id" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option value="">Sin categoría</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Estado</Label>
                            <select v-model="form.status" class="h-9 w-full rounded-md border px-3 text-sm">
                                <option v-for="status in statuses" :key="status" :value="status">{{ STATUS_LABELS[status] ?? status }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Síntomas</Label>
                        <textarea
                            v-model="form.symptoms"
                            rows="2"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Cómo se manifiesta"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Causa raíz</Label>
                        <textarea
                            v-model="form.root_cause"
                            rows="2"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Error conocido identificado"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Solución temporal (workaround)</Label>
                        <textarea
                            v-model="form.workaround"
                            rows="2"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Qué hacer mientras tanto"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Solución definitiva <span class="text-red-500">*</span></Label>
                        <textarea
                            v-model="form.solution"
                            rows="3"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Pasos para resolver"
                        />
                        <p v-if="form.errors.solution" class="text-xs text-red-500">{{ form.errors.solution }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showModal = false">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ form.processing ? 'Guardando…' : editingId ? 'Guardar cambios' : 'Crear artículo' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

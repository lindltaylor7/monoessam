<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import * as LucideIcons from 'lucide-vue-next';
import { Search, X, Smile } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props  = defineProps<{ modelValue: string }>();
const emit   = defineEmits<{ 'update:modelValue': [value: string] }>();

const open        = ref(false);
const search      = ref('');
const searchInput = ref<InstanceType<typeof Input> | null>(null);

// Build sorted list of all icon names once (excludes *Icon aliases and non-component exports)
const ALL_ICONS: string[] = Object.keys(LucideIcons)
    .filter(k => /^[A-Z]/.test(k) && !k.endsWith('Icon') && k !== 'default')
    .sort();

const VISIBLE_LIMIT = 120;

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    const list = q
        ? ALL_ICONS.filter(n => n.toLowerCase().includes(q))
        : ALL_ICONS;
    return list.slice(0, VISIBLE_LIMIT);
});

const resultLabel = computed(() => {
    const q = search.value.trim();
    if (!q) return `${ALL_ICONS.length.toLocaleString()} iconos disponibles`;
    const total = ALL_ICONS.filter(n => n.toLowerCase().includes(q.toLowerCase())).length;
    if (total === 0) return 'Sin resultados';
    if (total > VISIBLE_LIMIT) return `${total.toLocaleString()} coincidencias — mostrando ${VISIBLE_LIMIT}`;
    return `${total} coincidencia${total === 1 ? '' : 's'}`;
});

const selectedComponent = computed(() =>
    props.modelValue ? (LucideIcons as Record<string, any>)[props.modelValue] ?? null : null,
);

function select(name: string) {
    emit('update:modelValue', name);
    open.value = false;
    search.value = '';
}

function clear(e: MouseEvent) {
    e.stopPropagation();
    emit('update:modelValue', '');
}

// Focus the search input when the popover opens
watch(open, (val) => {
    if (val) {
        nextTick(() => {
            const el = searchInput.value?.$el as HTMLInputElement | undefined;
            el?.focus();
        });
    } else {
        search.value = '';
    }
});
</script>

<template>
    <Popover v-model:open="open">

        <!-- ── Trigger ──────────────────────────────────────────────────── -->
        <PopoverTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <!-- Selected icon preview -->
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-gray-100 dark:bg-gray-700">
                    <component
                        :is="selectedComponent"
                        v-if="selectedComponent"
                        class="h-4 w-4 text-gray-700 dark:text-gray-200"
                    />
                    <Smile v-else class="h-4 w-4 text-gray-400" />
                </span>

                <span class="flex-1 truncate text-left" :class="modelValue ? 'text-foreground' : 'text-muted-foreground'">
                    {{ modelValue || 'Selecciona un icono…' }}
                </span>

                <X
                    v-if="modelValue"
                    class="h-3.5 w-3.5 flex-shrink-0 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    @click="clear"
                />
            </button>
        </PopoverTrigger>

        <!-- ── Popover content ──────────────────────────────────────────── -->
        <PopoverContent class="w-[340px] p-0" align="start" :side-offset="4">

            <!-- Search bar -->
            <div class="border-b border-gray-100 p-3 dark:border-gray-700">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <Input
                        ref="searchInput"
                        v-model="search"
                        placeholder="Buscar icono… ej: User, Arrow, Check"
                        class="pl-8"
                    />
                </div>
                <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">{{ resultLabel }}</p>
            </div>

            <!-- Icon grid -->
            <div class="h-60 overflow-y-auto p-2">

                <!-- No results -->
                <div
                    v-if="filtered.length === 0"
                    class="flex h-full flex-col items-center justify-center gap-2 text-gray-400"
                >
                    <Search class="h-8 w-8 opacity-30" />
                    <p class="text-sm">Sin resultados para "{{ search }}"</p>
                </div>

                <!-- Grid -->
                <div v-else class="grid grid-cols-7 gap-0.5">
                    <button
                        v-for="name in filtered"
                        :key="name"
                        type="button"
                        :title="name"
                        :class="[
                            'group relative flex h-10 w-full items-center justify-center rounded-md transition-colors',
                            modelValue === name
                                ? 'bg-red-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700',
                        ]"
                        @click="select(name)"
                    >
                        <component
                            :is="(LucideIcons as Record<string, any>)[name]"
                            class="h-[18px] w-[18px]"
                        />
                    </button>
                </div>
            </div>

            <!-- Selected preview footer -->
            <div
                v-if="modelValue"
                class="flex items-center gap-2 border-t border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
            >
                <component
                    :is="selectedComponent"
                    v-if="selectedComponent"
                    class="h-4 w-4 flex-shrink-0 text-red-600"
                />
                <span class="flex-1 truncate text-xs font-medium text-gray-700 dark:text-gray-300">{{ modelValue }}</span>
                <button
                    type="button"
                    class="text-[11px] text-gray-400 hover:text-red-600 transition-colors"
                    @click="() => emit('update:modelValue', '')"
                >
                    Limpiar
                </button>
            </div>

        </PopoverContent>
    </Popover>
</template>

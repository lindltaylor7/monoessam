<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';
import { computed, ref, watch, type HTMLAttributes } from 'vue';

interface Factor {
    id: number;
    group: string;
    name: string;
}

interface FactorGroup {
    title: string;
    items: Factor[];
}

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    groupedFactors: FactorGroup[];
    modelValue: string;
    disabled?: boolean;
    id?: string;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const search = ref('');

const DIACRITICS_REGEX = new RegExp('[̀-ͯ]', 'g');

const normalize = (value: string) => value.normalize('NFD').replace(DIACRITICS_REGEX, '').toLowerCase();

const selectedFactor = computed(() => {
    for (const group of props.groupedFactors) {
        const found = group.items.find((f) => String(f.id) === props.modelValue);
        if (found) return found;
    }
    return null;
});

const filteredGroups = computed(() => {
    const query = normalize(search.value.trim());
    if (!query) return props.groupedFactors;

    return props.groupedFactors
        .map((group) => ({
            title: group.title,
            items: group.items.filter((f) => normalize(f.name).includes(query) || normalize(group.title).includes(query)),
        }))
        .filter((group) => group.items.length > 0);
});

const select = (value: string) => {
    emit('update:modelValue', value);
    open.value = false;
};

watch(open, (isOpen) => {
    if (!isOpen) search.value = '';
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger
            :id="id"
            :disabled="disabled"
            :class="
                cn(
                    'border-input data-[placeholder]:text-muted-foreground flex h-8 w-64 shrink-0 items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-xs whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30 dark:hover:bg-input/50',
                    props.class,
                )
            "
        >
            <span class="line-clamp-1 text-left">{{ selectedFactor ? selectedFactor.name : 'Sin factor Atwater' }}</span>
            <ChevronsUpDown class="text-muted-foreground size-4 shrink-0 opacity-50" />
        </PopoverTrigger>
        <PopoverContent class="w-64 p-0" align="start">
            <div class="relative border-b border-zinc-100 p-2 dark:border-zinc-800">
                <Search class="absolute top-1/2 left-4 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                <Input v-model="search" placeholder="Buscar factor..." class="h-8 pl-8 text-xs" autofocus />
            </div>
            <div class="max-h-64 overflow-y-auto p-1">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    @click="select('none')"
                >
                    <Check :class="cn('h-3.5 w-3.5 shrink-0', modelValue === 'none' ? 'opacity-100' : 'opacity-0')" />
                    Sin factor Atwater
                </button>

                <div v-for="group in filteredGroups" :key="group.title" class="mt-1">
                    <p class="px-2 py-1 text-[10px] font-bold tracking-wide text-zinc-400 uppercase">{{ group.title }}</p>
                    <button
                        v-for="f in group.items"
                        :key="f.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="select(String(f.id))"
                    >
                        <Check :class="cn('h-3.5 w-3.5 shrink-0', modelValue === String(f.id) ? 'opacity-100' : 'opacity-0')" />
                        <span class="line-clamp-1">{{ f.name }}</span>
                    </button>
                </div>

                <div v-if="filteredGroups.length === 0" class="p-4 text-center text-xs text-zinc-400">No se encontraron factores.</div>
            </div>
        </PopoverContent>
    </Popover>
</template>

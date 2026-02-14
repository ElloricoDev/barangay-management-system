<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="mt-4 flex flex-wrap gap-2">
        <Link
            v-for="link in links"
            :key="`${link.label}-${link.url ?? 'none'}`"
            :href="link.url || '#'"
            class="rounded-md border px-3 py-1 text-sm"
            :class="[
                link.active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-300 bg-white text-slate-700',
                !link.url ? 'pointer-events-none opacity-50' : '',
            ]"
            v-html="link.label"
        />
    </div>
</template>

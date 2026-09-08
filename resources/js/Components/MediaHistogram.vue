<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    eventId: { type: [Number, String], required: true },
});

const emit = defineEmits(['pick-hour']);

const bins = ref([]);

onMounted(async () => {
    try {
        const res = await fetch(`/api/events/${props.eventId}/media/histogram`);
        const json = await res.json();
        bins.value = json.data ?? [];
    } catch (e) {
        bins.value = [];
    }
});

const maxCount = computed(() => Math.max(1, ...bins.value.map((b) => b.count)));

function hh(hour) {
    return String(hour).padStart(2, '0');
}
</script>

<template>
    <div v-if="bins.length > 1" class="mt-4">
        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">Felvételek időpont szerint</p>
        <div class="flex items-end gap-1">
            <button
                v-for="bin in bins"
                :key="bin.hour"
                type="button"
                class="group flex flex-1 flex-col items-center gap-1"
                :title="`${hh(bin.hour)}:00 — ${bin.count} felvétel`"
                @click="emit('pick-hour', bin.hour)"
            >
                <span
                    class="w-full rounded-sm bg-accent/40 transition-colors group-hover:bg-accent"
                    :style="{ height: `${Math.max(4, (bin.count / maxCount) * 48)}px` }"
                />
                <span class="text-[9px] text-muted">{{ hh(bin.hour) }}</span>
            </button>
        </div>
    </div>
</template>

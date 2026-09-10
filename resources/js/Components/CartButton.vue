<script setup>
import { computed } from 'vue';
import { useI18n } from '@/Composables/useI18n';

/**
 * Kosárba / Kosárban / Kivesz gomb — a három állapot MINDIG azonos szélességű
 * (a leghosszabb feliratra méretezve, egymásra rakott grid-cellában), így sem a
 * hover (Kosárban → Kivesz), sem a kosárba tétel nem okoz elrendezés-ugrást.
 */
const props = defineProps({
    inCart: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    longInCartLabel: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle']);

const { t } = useI18n();

const inCartLabel = computed(() => (props.longInCartLabel ? t('common.in_cart_long') : t('common.in_cart')));
</script>

<template>
    <button
        type="button"
        class="btn-sheen group/cart grid justify-items-center rounded-lg border text-xs font-semibold uppercase tracking-wide transition-colors"
        :class="[
            block ? 'w-full py-2.5' : 'px-3 py-1.5',
            inCart
                ? 'border-accent text-accent hover:border-accent/60 hover:text-accent/60'
                : (block ? 'border-transparent bg-accent text-white hover:bg-accent-hover' : 'border-border text-content hover:border-accent'),
        ]"
        :title="inCart ? t('common.remove_from_cart') : t('common.add_to_cart')"
        :aria-label="inCart ? t('common.remove_from_cart') : t('common.add_to_cart')"
        @click="emit('toggle')"
    >
        <span class="col-start-1 row-start-1 whitespace-nowrap px-1" :class="{ invisible: inCart }">{{ t('common.add_to_cart') }}</span>
        <span class="col-start-1 row-start-1 whitespace-nowrap px-1" :class="inCart ? 'group-hover/cart:invisible' : 'invisible'">{{ inCartLabel }}</span>
        <span class="col-start-1 row-start-1 whitespace-nowrap px-1 invisible" :class="{ 'group-hover/cart:visible': inCart }">{{ t('common.remove_from_cart') }}</span>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Kétszínű márkajel — a szöveg a /admin/settings/branding-ból jön (Inertia
 * shared `branding` prop). `logo_tail` üresen hagyva egyszínű logót ad.
 */
const page = usePage();
const branding = computed(() => page.props.branding ?? { logo_lead: 'KANYAR', logo_tail: 'FOTÓS' });
</script>

<template>
    <span>{{ branding.logo_lead }}<span v-if="branding.logo_tail" class="text-accent">{{ branding.logo_tail }}</span></span>
</template>

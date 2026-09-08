<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

// A `seo` shared propból (App\Services\Seo) tartja szinkronban a canonical /
// description / OG / Twitter tageket SPA-navigációnál. Az első lefestést a
// blade `$seo` composer intézi — a `head-key`-k miatt itt nem duplázódnak.
const seo = computed(() => usePage().props.seo ?? null);
</script>

<template>
    <Head v-if="seo">
        <meta head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link head-key="canonical" rel="canonical" :href="seo.canonical" />
        <meta head-key="og:type" property="og:type" :content="seo.type" />
        <meta head-key="og:site_name" property="og:site_name" :content="seo.site_name" />
        <meta head-key="og:title" property="og:title" :content="seo.title" />
        <meta head-key="og:description" property="og:description" :content="seo.description" />
        <meta head-key="og:url" property="og:url" :content="seo.canonical" />
        <meta head-key="og:image" property="og:image" :content="seo.image" />
        <meta head-key="og:locale" property="og:locale" :content="seo.locale" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="seo.title" />
        <meta head-key="twitter:description" name="twitter:description" :content="seo.description" />
        <meta head-key="twitter:image" name="twitter:image" :content="seo.image" />
    </Head>
</template>

<script setup>
defineProps({
    name: { type: String, default: '' },
    size: { type: [Number, String], default: 16 },
});
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <template v-if="name === 'grid'"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></template>
        <template v-else-if="name === 'users'"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></template>
        <template v-else-if="name === 'stats'"><path d="M3 3v18h18" /><path d="M7 14l4-4 3 3 5-6" /></template>
        <template v-else-if="name === 'storage'"><ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5" /><path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3" /></template>
        <template v-else-if="name === 'watermark'"><path d="M4 19h16M7 15l3-10 3 10M8.5 11h3" /></template>
        <template v-else-if="name === 'theme'"><circle cx="12" cy="12" r="9" /><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none" /></template>
        <template v-else-if="name === 'plate'"><rect x="2" y="6" width="20" height="12" rx="2" /><path d="M6 10v4M10 10v4M14 10v4M18 10v4" /></template>
        <template v-else-if="name === 'images'"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-4.5-4.5L7 20" /></template>
        <template v-else-if="name === 'mail'"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></template>
        <template v-else-if="name === 'branding'"><path d="M4 7V4h16v3M9 20h6M12 4v16" /></template>
        <template v-else-if="name === 'shield'"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" /><path d="m9 12 2 2 4-4" /></template>
        <template v-else-if="name === 'cart'"><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" /><path d="M2 3h2l2.5 12.5A2 2 0 0 0 8.5 17h9a2 2 0 0 0 2-1.6L21 7H5" /></template>
        <template v-else-if="name === 'lock'"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></template>
        <template v-else-if="name === 'bug'"><rect x="8" y="6" width="8" height="14" rx="4" /><path d="M19 7l-2 2M5 7l2 2M3 13h3M18 13h3M4 19l3-2M20 19l-3-2M9 3l1.5 2M15 3l-1.5 2" /></template>
        <template v-else-if="name === 'search'"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></template>
        <template v-else-if="name === 'wallet'"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M16 12h4M3 9h13" /></template>
        <template v-else-if="name === 'fingerprint'"><path d="M12 11c0 4-1 6-2 8M8 7a5 5 0 0 1 8 4c0 4 0 5 1 7M5 11a7 7 0 0 1 12-5M12 15c0 3 .5 4 1 5.5M19 13c0 4-.5 5-1 6.5" /></template>
        <template v-else-if="name === 'robot'"><rect x="4" y="8" width="16" height="12" rx="2" /><path d="M12 4v4M9 14h.01M15 14h.01M2 13h2M20 13h2" /></template>
        <template v-else-if="name === 'pin'"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></template>
        <template v-else-if="name === 'privacy'"><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></template>
        <template v-else-if="name === 'doc'"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2" /></template>
        <template v-else-if="name === 'share2'"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4" /></template>
        <template v-else-if="name === 'sync'"><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5M3 21v-5h5" /></template>
        <template v-else-if="name === 'idcard'"><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="12" r="2" /><path d="M14 10h4M14 14h4M6.5 16.5a3 3 0 0 1 5 0" /></template>
        <template v-else-if="name === 'help'"><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 0 1 5 .3c0 1.7-2.5 2-2.5 3.7M12 17h.01" /></template>
        <template v-else><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></template>
    </svg>
</template>

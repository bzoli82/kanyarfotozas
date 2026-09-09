<script setup>
/**
 * A támogatott fizetési szolgáltatók logó-sora (Stripe / SimplePay / Barion).
 * Fehér chipeken, hogy világos és sötét témán is olvasható maradjon.
 * Ha később hivatalos vektoros logók kellenek, ezeket cseréld SVG assetre.
 */
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span
            class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-[13px] font-bold lowercase tracking-tight text-[#635BFF] shadow-sm"
            aria-label="Stripe"
        >stripe</span>
        <span
            class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-[13px] font-bold tracking-tight shadow-sm"
            aria-label="SimplePay"
        ><span class="text-[#0a2540]">Simple</span><span class="text-[#8dc63f]">Pay</span></span>
        <span
            class="inline-flex items-center rounded-md bg-white px-2.5 py-1 text-[13px] font-bold lowercase tracking-tight text-[#0c9c6e] shadow-sm"
            aria-label="Barion"
        >barion</span>
    </div>
</template>

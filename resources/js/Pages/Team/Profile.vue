<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useMediaUrl } from '@/Composables/useMediaUrl';

const props = defineProps({
    profile: { type: Object, required: true },
    publicUrl: { type: String, default: '/photographers' },
});

const { mediaUrl } = useMediaUrl();

const form = useForm({
    bio: props.profile.bio ?? '',
    is_public: props.profile.is_public ?? false,
    public_email: props.profile.public_email ?? '',
    website: props.profile.website ?? '',
    social_facebook: props.profile.social_facebook ?? '',
    social_instagram: props.profile.social_instagram ?? '',
    social_youtube: props.profile.social_youtube ?? '',
    social_tiktok: props.profile.social_tiktok ?? '',
    avatar: null,
    remove_avatar: false,
});

const avatarInput = ref(null);
const avatarPreview = ref(null);

function pickAvatar(e) {
    const file = e.target.files?.[0] ?? null;
    form.avatar = file;
    form.remove_avatar = false;
    avatarPreview.value = file ? URL.createObjectURL(file) : null;
}

function clearAvatar() {
    form.avatar = null;
    form.remove_avatar = true;
    avatarPreview.value = null;
    if (avatarInput.value) avatarInput.value.value = '';
}

function submit() {
    form.post('/profil', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.avatar = null;
            form.remove_avatar = false;
            avatarPreview.value = null;
            if (avatarInput.value) avatarInput.value.value = '';
        },
    });
}
</script>

<template>
    <Head title="Nyilvános profilom" />

    <AdminLayout>
        <h1 class="font-display text-xl font-bold uppercase tracking-tight text-content">Nyilvános profilom</h1>
        <p class="mt-2 max-w-xl text-sm text-muted">
            Ezek az adatok jelennek meg a nyilvános
            <a :href="publicUrl" target="_blank" class="text-accent hover:underline">Fotósaink</a> oldalon.
            A neved, a bejelentkezési e-mailed, a szerepköröd és a jutalékod nem itt módosítható — azt a superadmin állítja.
        </p>

        <form class="mt-6 max-w-xl space-y-6" @submit.prevent="submit">
            <!-- Alap -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5 space-y-4">
                <div class="flex items-center gap-2 text-sm text-content">
                    <span class="font-semibold">{{ profile.name }}</span>
                    <span class="text-muted">·</span>
                    <span class="text-muted">{{ profile.email }}</span>
                </div>

                <label class="flex items-start gap-2.5 text-sm text-content">
                    <input v-model="form.is_public" type="checkbox" class="mt-0.5 accent-[var(--color-accent)]" />
                    <span>
                        <strong>Látszódjak a fotósok között.</strong>
                        <span class="mt-0.5 block text-xs text-muted">
                            Ha kiveszed a pipát, nem jelensz meg a nyilvános „Fotósaink" oldalon és az esemény-kereső fotós-szűrőjében sem —
                            de a rendszer tagja maradsz, feltölthetsz és eladhatsz képeket.
                        </span>
                    </span>
                </label>

                <!-- Profilkép -->
                <div class="flex items-center gap-3 pt-1">
                    <img
                        v-if="avatarPreview || (profile.avatar && !form.remove_avatar)"
                        :src="avatarPreview || mediaUrl(profile.avatar)"
                        alt=""
                        class="h-16 w-16 shrink-0 rounded-full border border-border object-cover"
                    />
                    <span v-else class="grid h-16 w-16 shrink-0 place-items-center rounded-full border border-dashed border-border text-[10px] text-muted">nincs kép</span>
                    <div class="text-xs">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-content"
                            @change="pickAvatar"
                        />
                        <button
                            v-if="avatarPreview || (profile.avatar && !form.remove_avatar)"
                            type="button"
                            class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-accent hover:text-accent-hover"
                            @click="clearAvatar"
                        >
                            Profilkép törlése
                        </button>
                    </div>
                </div>
                <p v-if="form.errors.avatar" class="text-xs text-accent">{{ form.errors.avatar }}</p>

                <label class="block">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-muted">Bemutatkozás (pár mondat)</span>
                    <textarea v-model="form.bio" rows="3" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content focus:border-accent focus:outline-none"></textarea>
                    <span v-if="form.errors.bio" class="mt-1 block text-xs text-accent">{{ form.errors.bio }}</span>
                </label>
            </div>

            <!-- Elérhetőségek -->
            <div class="rounded-[var(--radius-base)] border border-border bg-surface-1 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-content">Elérhetőségek</h2>
                <p class="mt-1 text-xs text-muted">Csak a kitöltöttek jelennek meg. A linkeknél elég a cím, a <code>https://</code>-t hozzátesszük.</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Céges e-mail</span>
                        <input v-model="form.public_email" type="email" placeholder="pl. peter@kanyarfotozas.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                        <span v-if="form.errors.public_email" class="mt-1 block text-xs text-accent">{{ form.errors.public_email }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Weboldal</span>
                        <input v-model="form.website" type="text" placeholder="pl. peterfoto.hu" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Facebook oldal</span>
                        <input v-model="form.social_facebook" type="text" placeholder="facebook.com/..." class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">Instagram</span>
                        <input v-model="form.social_instagram" type="text" placeholder="instagram.com/..." class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">YouTube csatorna</span>
                        <input v-model="form.social_youtube" type="text" placeholder="youtube.com/@..." class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-muted">TikTok oldal</span>
                        <input v-model="form.social_tiktok" type="text" placeholder="tiktok.com/@..." class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-content placeholder:text-muted focus:border-accent focus:outline-none" />
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-accent px-6 py-2.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-accent-hover disabled:opacity-60">
                    {{ form.processing ? 'Mentés…' : 'Mentés' }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-xs font-semibold uppercase tracking-wide text-accent">Elmentve ✓</span>
            </div>
        </form>
    </AdminLayout>
</template>

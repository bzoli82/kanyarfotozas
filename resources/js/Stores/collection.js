import { defineStore } from 'pinia';

const STORAGE_KEY = 'kanyarfotozas.collection';

function trackAdd(mediaId) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/api/collection/track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ?? '' },
            body: JSON.stringify({ media_id: mediaId }),
            keepalive: true,
        }).catch(() => {});
    } catch (e) {
        // nem kritikus
    }
}

function loadFromStorage() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

function saveToStorage(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch (e) {
        // localStorage nem elerheto
    }
}

/**
 * Kollekció / wishlist (EPIC-17). Külön a kosártól: kosár = vásárolni akarok,
 * kollekció = még gondolkodom. Csak localStorage-ban él (nincs regisztráció).
 * Hozzáadáskor egy tűz-és-felejtsd `POST /api/collection/track` növeli a
 * media.wishlist_count-ot (admin statisztikához).
 */
export const useCollectionStore = defineStore('collection', {
    state: () => ({
        items: loadFromStorage(), // [{ id, type, price_cents, thumbnail_s3_key, event_name }]
    }),

    getters: {
        count: (state) => state.items.length,
        has: (state) => (mediaId) => state.items.some((i) => i.id === mediaId),
        ids: (state) => state.items.map((i) => i.id),
    },

    actions: {
        add(media) {
            if (this.has(media.id)) return;
            this.items.push(media);
            this.persist();
            trackAdd(media.id);
        },

        remove(mediaId) {
            this.items = this.items.filter((i) => i.id !== mediaId);
            this.persist();
        },

        toggle(media) {
            this.has(media.id) ? this.remove(media.id) : this.add(media);
        },

        clear() {
            this.items = [];
            this.persist();
        },

        persist() {
            saveToStorage(this.items);
        },
    },
});

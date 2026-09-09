import { defineStore } from 'pinia';

const STORAGE_KEY = 'roadsidephoto.cart';

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
        // localStorage nem elerheto (privat mod stb.) — a kosar csak a session alatt el.
    }
}

/**
 * Kosar allapot (EPIC-06). Csak media ID-kat + egy pillanatnyi "snapshot"-ot
 * (nev, ar, thumbnail) tarolunk localStorage-ban — a tenyleges ar/allapot
 * mindig frissul a szerverrol a /cart oldal betoltesekor (lasd Cart/Index.vue),
 * hogy a felhasznalo sose lasson elavult arat.
 */
export const useCartStore = defineStore('cart', {
    state: () => ({
        items: loadFromStorage(), // [{ id, type, name, event_name, price_cents, thumbnail_s3_key }]
    }),

    getters: {
        count: (state) => state.items.length,
        totalCents: (state) => state.items.reduce((sum, item) => sum + (item.price_cents ?? 0), 0),
        hasItem: (state) => (mediaId) => state.items.some((item) => item.id === mediaId),
    },

    actions: {
        add(media) {
            if (this.hasItem(media.id)) return;
            this.items.push(media);
            this.persist();
        },

        remove(mediaId) {
            this.items = this.items.filter((item) => item.id !== mediaId);
            this.persist();
        },

        toggle(media) {
            if (this.hasItem(media.id)) {
                this.remove(media.id);
            } else {
                this.add(media);
            }
        },

        clear() {
            this.items = [];
            this.persist();
        },

        /** A /cart oldal a szerverrol frissitett elemekkel (ar, allapot) hivja meg. */
        sync(freshItems) {
            this.items = freshItems;
            this.persist();
        },

        persist() {
            saveToStorage(this.items);
        },
    },
});

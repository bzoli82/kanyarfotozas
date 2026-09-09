import { defineStore } from 'pinia';

const STORAGE_KEY = 'roadsidephoto.cookie_consent';

/**
 * Cookie-hozzájárulás állapota (localStorage-perzisztált). Jelenleg az oldal
 * csak működéshez szükséges cookie-t/localStorage-t használ (munkamenet, kosár,
 * téma) — nincs analitikai/marketing süti. A `analytics` getter előre bekötve,
 * hogy ha később bejön külső analitika, csak akkor süljön el, ha a látogató
 * mindenre rábólintott.
 */
function readStored() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function writeStored(value) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch (e) {
        /* privát ablak / letiltott storage — a döntés csak az aktuális betöltésre él */
    }
}

export const useConsentStore = defineStore('consent', {
    state: () => ({
        // null = még nem döntött; 'all' = minden; 'essential' = csak a szükségesek
        status: readStored()?.status ?? null,
    }),
    getters: {
        decided: (state) => state.status !== null,
        analytics: (state) => state.status === 'all',
    },
    actions: {
        accept(status) {
            this.status = status === 'all' ? 'all' : 'essential';
            writeStored({ status: this.status, at: new Date().toISOString() });
        },
        reset() {
            this.status = null;
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {
                /* noop */
            }
        },
    },
});

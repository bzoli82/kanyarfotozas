import { defineStore } from 'pinia';

const STORAGE_KEY = 'kanyarfotozas.theme';

function readStoredMode() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        return null;
    }
}

function writeStoredMode(mode) {
    try {
        localStorage.setItem(STORAGE_KEY, mode);
    } catch (e) {
        // localStorage nem elerheto (privat ablak stb.) — a valasztas csak az adott betoltesre ervenyes
    }
}

function resolveMode(mode) {
    if (mode !== 'system') return mode;

    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

// A serverMode-ot a HandleInertiaRequests megosztott `themeMode` propja adja (admin altal
// beallitott alapertelmezett), a bootstrap <script> az app.blade.php-ben mar be is allitotta
// a data-theme attributumot lefestes elott — itt csak a Pinia state-et szinkronizaljuk hozza.
export const useThemeStore = defineStore('theme', {
    state: () => ({
        mode: readStoredMode() || 'dark',
    }),
    getters: {
        resolved: (state) => resolveMode(state.mode),
    },
    actions: {
        init(serverMode) {
            if (!readStoredMode()) {
                this.mode = serverMode || 'dark';
            }
            this.apply();

            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
                    if (this.mode === 'system') this.apply();
                });
            }
        },
        setMode(mode) {
            this.mode = mode;
            writeStoredMode(mode);
            this.apply();
        },
        apply() {
            document.documentElement.setAttribute('data-theme', this.resolved);
        },
    },
});

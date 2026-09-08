import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';

/**
 * Fotós mobil PWA (EPIC-15) feltöltés: fájlonkénti XHR folyamatjelzéssel +
 * IndexedDB-alapú offline sorral. Ami offline / hiba miatt nem megy át, a sorba
 * kerül, és a rendszer automatikusan újrapróbálja, amint van net.
 */

const DB_NAME = 'kf-uploads';
const STORE = 'queue';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            if (!req.result.objectStoreNames.contains(STORE)) {
                req.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function dbAll() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
        tx.onsuccess = () => resolve(tx.result || []);
        tx.onerror = () => reject(tx.error);
    });
}

async function dbAdd(record) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite').objectStore(STORE).add(record);
        tx.onsuccess = () => resolve(tx.result);
        tx.onerror = () => reject(tx.error);
    });
}

async function dbRemove(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite').objectStore(STORE).delete(id);
        tx.onsuccess = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** Egy fájl feltöltése XHR-rel, folyamatjelzéssel. */
function xhrUpload({ eventId, photographerId, file, name }, onProgress) {
    return new Promise((resolve, reject) => {
        const form = new FormData();
        form.append('files[]', file, name);
        if (photographerId) {
            form.append('photographer_id', photographerId);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/admin/events/${eventId}/media`);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                onProgress(Math.round((e.loaded / e.total) * 100));
            }
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve();
            } else if (xhr.status === 422) {
                reject(new Error('A fájl nem felel meg (formátum vagy méret).'));
            } else {
                reject(new Error(`Feltöltési hiba (${xhr.status}).`));
            }
        };
        xhr.onerror = () => reject(new Error('offline'));
        xhr.ontimeout = () => reject(new Error('offline'));
        xhr.send(form);
    });
}

export function useMobileUpload() {
    // jobs: [{ id, name, size, progress, status }]  status: uploading|done|error|queued
    const jobs = reactive([]);
    const online = ref(navigator.onLine);
    let jobSeq = 0;
    let draining = false;

    function jobFor(name, size) {
        const job = reactive({ id: ++jobSeq, name, size, progress: 0, status: 'uploading' });
        jobs.push(job);
        return job;
    }

    async function addFiles(fileList, { eventId, photographerId }) {
        for (const file of Array.from(fileList)) {
            const job = jobFor(file.name, file.size);
            const record = { eventId, photographerId: photographerId || null, file, name: file.name };

            if (!navigator.onLine) {
                job.status = 'queued';
                await dbAdd(record);
                continue;
            }

            try {
                await xhrUpload(record, (p) => { job.progress = p; });
                job.progress = 100;
                job.status = 'done';
            } catch (err) {
                if (err.message === 'offline') {
                    job.status = 'queued';
                    await dbAdd(record);
                } else {
                    job.status = 'error';
                    job.error = err.message;
                }
            }
        }
    }

    async function drainQueue() {
        if (draining || !navigator.onLine) {
            return;
        }
        draining = true;
        try {
            const pending = await dbAll();
            for (const record of pending) {
                const job = jobFor(record.name, record.file?.size ?? 0);
                job.status = 'uploading';
                try {
                    await xhrUpload(record, (p) => { job.progress = p; });
                    job.progress = 100;
                    job.status = 'done';
                    await dbRemove(record.id);
                } catch (err) {
                    if (err.message === 'offline') {
                        job.status = 'queued';
                        break; // net elment — állj le, majd az online event újraindít
                    }
                    job.status = 'error';
                    job.error = err.message;
                    await dbRemove(record.id); // végleges hiba — ne ragadjon be a sorba
                }
            }
        } finally {
            draining = false;
        }
    }

    async function refreshQueuedCount() {
        queuedCount.value = (await dbAll()).length;
    }

    const queuedCount = ref(0);

    function handleOnline() {
        online.value = true;
        drainQueue().then(refreshQueuedCount);
    }
    function handleOffline() {
        online.value = false;
    }

    onMounted(async () => {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        await refreshQueuedCount();
        if (navigator.onLine) {
            await drainQueue();
            await refreshQueuedCount();
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return { jobs, online, queuedCount, addFiles, drainQueue, refreshQueuedCount };
}

/** Videó hossz kiolvasása (max hossz ellenőrzéshez). */
export function readVideoDuration(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const v = document.createElement('video');
        v.preload = 'metadata';
        v.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(v.duration || 0);
        };
        v.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(0);
        };
        v.src = url;
    });
}

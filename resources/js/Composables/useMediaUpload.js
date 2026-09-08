import { reactive, ref } from 'vue';

/**
 * Egységes média-feltöltés az esemény oldalára.
 *
 *  - **Közvetlen R2** (ha az import-disk S3): a böngésző aláírt URL-ekkel egyből
 *    az R2 „import" bucketbe tölt (párhuzamosan, folyamatjelzővel, újrapróbálással),
 *    majd az onnan-import lefut. Nincs 200-as korlát, a szervert nem terheli.
 *  - **Appon át** (fallback / kis köteg): fájlonkénti XHR a `/events/{id}/media`
 *    végpontra.
 */

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];
const VIDEO_EXT = ['mp4', 'mov', 'avi'];

function extOf(name) {
    return (name.split('.').pop() || '').toLowerCase();
}

/** PUT egy fájl egy aláírt URL-re, XHR-rel (feltöltési progress). */
function putToR2(url, headers, file, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);
        Object.entries(headers || {}).forEach(([k, v]) => xhr.setRequestHeader(k, v));
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
        xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`R2 ${xhr.status}`)));
        xhr.onerror = () => reject(new Error('network'));
        xhr.ontimeout = () => reject(new Error('network'));
        xhr.send(file);
    });
}

/** POST egy fájl az appnak (feldolgozás szerver-oldalon). */
function postToApp(eventId, photographerId, file, name, onProgress) {
    return new Promise((resolve, reject) => {
        const form = new FormData();
        form.append('files[]', file, name);
        if (photographerId) form.append('photographer_id', photographerId);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/admin/events/${eventId}/media`);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
        xhr.onload = () => (xhr.status >= 200 && xhr.status < 300
            ? resolve()
            : reject(new Error(xhr.status === 422 ? 'A fájl nem felel meg (formátum/méret).' : `Hiba (${xhr.status}).`)));
        xhr.onerror = () => reject(new Error('network'));
        xhr.send(form);
    });
}

/** Párhuzamos futtató, `limit` egyidejű feladattal. */
async function pool(items, limit, worker) {
    const queue = [...items.entries()];
    const runners = Array.from({ length: Math.min(limit, queue.length) }, async () => {
        while (queue.length) {
            const [index, item] = queue.shift();
            await worker(item, index);
        }
    });
    await Promise.all(runners);
}

export function useMediaUpload() {
    // jobs: [{ name, size, progress (0-1), status: 'pending'|'uploading'|'done'|'error', error? }]
    const jobs = reactive([]);
    const phase = ref('idle'); // idle | uploading | triggering | done | error
    const importPath = ref(null);

    function reset() {
        jobs.splice(0, jobs.length);
        phase.value = 'idle';
        importPath.value = null;
    }

    function accept(fileList) {
        reset();
        for (const file of Array.from(fileList)) {
            const ext = extOf(file.name);
            if (![...IMAGE_EXT, ...VIDEO_EXT].includes(ext)) continue;
            jobs.push(reactive({
                file,
                name: file.name,
                path: file.webkitRelativePath || file.name,
                size: file.size,
                progress: 0,
                status: 'pending',
            }));
        }
        return jobs.length;
    }

    async function retry(fn, attempts = 3) {
        let lastErr;
        for (let i = 0; i < attempts; i++) {
            try {
                return await fn();
            } catch (e) {
                lastErr = e;
                if (e.message !== 'network' && !/R2 5\d\d/.test(e.message)) break;
                await new Promise((r) => setTimeout(r, 1000 * (i + 1)));
            }
        }
        throw lastErr;
    }

    /**
     * Elindítja a feltöltést. Először közvetlen R2-t próbál; ha a szerver nem
     * támogatja (`supported:false`), fájlonkénti app-feltöltésre vált.
     *
     * @returns {Promise<{mode:'direct'|'app', importPath:string|null, uploaded:number, failed:number}>}
     */
    async function start({ eventId, photographerId }) {
        phase.value = 'uploading';
        const pending = jobs.filter((j) => j.status === 'pending' || j.status === 'error');

        // 1. aláírt URL-ek kérése kötegenként (max 200)
        let session = null;
        const signed = new Map(); // path -> {url, headers}
        let supported = true;

        for (let i = 0; i < pending.length; i += 200) {
            const chunk = pending.slice(i, i + 200);
            const res = await fetch(`/admin/events/${eventId}/upload/sign`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ session, files: chunk.map((j) => ({ path: j.path, size: j.size })) }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.supported === false) {
                supported = data.supported !== false ? true : false;
                if (data.supported === false) { supported = false; break; }
                throw new Error('Az aláírt URL-ek kérése nem sikerült.');
            }
            supported = true;
            session = data.session;
            importPath.value = data.import_path;
            for (const f of data.files || []) signed.set(f.path, { url: f.url, headers: f.headers });
        }

        // 2a. közvetlen R2
        if (supported) {
            let uploaded = 0;
            let failed = 0;
            await pool(pending, 6, async (job) => {
                const s = signed.get(job.path);
                if (!s) { job.status = 'error'; job.error = 'Nincs URL'; failed++; return; }
                job.status = 'uploading';
                try {
                    await retry(() => putToR2(s.url, s.headers, job.file, (p) => { job.progress = p; }));
                    job.progress = 1;
                    job.status = 'done';
                    uploaded++;
                } catch (e) {
                    job.status = 'error';
                    job.error = e.message === 'network' ? 'Hálózati hiba' : e.message;
                    failed++;
                }
            });
            phase.value = failed && !uploaded ? 'error' : 'triggering';
            return { mode: 'direct', importPath: importPath.value, uploaded, failed };
        }

        // 2b. fallback: appon át, fájlonként
        let uploaded = 0;
        let failed = 0;
        await pool(pending, 3, async (job) => {
            job.status = 'uploading';
            try {
                await retry(() => postToApp(eventId, photographerId, job.file, job.name, (p) => { job.progress = p; }), 2);
                job.progress = 1;
                job.status = 'done';
                uploaded++;
            } catch (e) {
                job.status = 'error';
                job.error = e.message === 'network' ? 'Hálózati hiba' : e.message;
                failed++;
            }
        });
        phase.value = failed && !uploaded ? 'error' : 'done';
        return { mode: 'app', importPath: null, uploaded, failed };
    }

    return { jobs, phase, importPath, accept, start, reset };
}

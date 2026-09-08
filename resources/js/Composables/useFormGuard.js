import { ref, watch } from 'vue';
import { sha256 } from '@/Support/sha256';

const enc = new TextEncoder();

function leadingZeroBits(bytes) {
    let count = 0;
    for (const byte of bytes) {
        if (byte === 0) {
            count += 8;
            continue;
        }
        return count + (Math.clz32(byte) - 24);
    }
    return count;
}

/**
 * Az App\Support\FormGuard challenge kliens-oldali kezelése:
 *  - a látogató a `question`-re kézzel válaszol (ez a form `guard_answer` mezője),
 *  - a proof-of-work puzzle-t itt oldjuk meg a háttérben (láthatatlan, ~1 s),
 *  - a `fields()` a beküldéshez kész `guard_token` + `guard_pow` párost adja.
 *
 * @param {() => ({token: string, question: string, pow: {salt: string, bits: number}}|null)} getGuard
 */
export function useFormGuard(getGuard) {
    const solving = ref(false);
    const question = ref(getGuard()?.question ?? '');
    let solvePromise = null;

    async function run() {
        const g = getGuard();
        question.value = g?.question ?? '';
        if (!g?.token || !g?.pow) return null;

        solving.value = true;
        try {
            const { salt, bits } = g.pow;
            let n = 0;
            for (;; n++) {
                if (leadingZeroBits(sha256(enc.encode(salt + n))) >= bits) break;
                if ((n & 0x3fff) === 0) await new Promise((r) => setTimeout(r)); // yield az UI-nak
            }
            return { token: g.token, pow: n };
        } finally {
            solving.value = false;
        }
    }

    // A puzzle-t azonnal (a háttérben) elkezdjük oldani; a challenge cseréjekor újra.
    function start() {
        solvePromise = run();
    }

    watch(getGuard, start, { immediate: true });

    /**
     * A form beküldéséhez: megvárja a puzzle megoldását, és visszaadja a
     * `guard_token` + `guard_pow` mezőket (a `guard_answer` a látható input).
     */
    async function fields() {
        const solved = await (solvePromise ?? run());
        return solved ? { guard_token: solved.token, guard_pow: solved.pow } : {};
    }

    return { solving, question, fields, start };
}

/**
 * v-tilt — a kártya a kurzor irányába billen (3D). Csak akkor él, ha a
 * /admin/settings/theme „Animációk" → kártya-hover = „3D-dőlés" (`data-anim-cards="tilt"`)
 * ÉS nincs prefers-reduced-motion. A CSS a `--tx` / `--ty` (rotateY/rotateX deg)
 * változókból számol (lásd app.css `[data-anim-cards='tilt']`).
 */

const MAX_DEG = 6;

function tiltActive() {
    return (
        document.documentElement.dataset.animCards === 'tilt' &&
        !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    );
}

export default {
    mounted(el) {
        if (!tiltActive()) {
            return;
        }

        const onMove = (e) => {
            const r = el.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width - 0.5;
            const py = (e.clientY - r.top) / r.height - 0.5;
            el.style.setProperty('--tx', `${(px * MAX_DEG).toFixed(2)}deg`);
            el.style.setProperty('--ty', `${(-py * MAX_DEG).toFixed(2)}deg`);
        };
        const onLeave = () => {
            el.style.setProperty('--tx', '0deg');
            el.style.setProperty('--ty', '0deg');
        };

        el.addEventListener('mousemove', onMove);
        el.addEventListener('mouseleave', onLeave);
        el._tilt = { onMove, onLeave };
    },
    unmounted(el) {
        if (el._tilt) {
            el.removeEventListener('mousemove', el._tilt.onMove);
            el.removeEventListener('mouseleave', el._tilt.onLeave);
            delete el._tilt;
        }
    },
};

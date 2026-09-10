import { computed, reactive } from 'vue';

/**
 * Nagykép-nézegető: görgő / koppintás / csippentés → nagyítás, húzás → pásztázás.
 * Nagyítás nélkül a vízszintes húzás lapoz (`onSwipe(dir)`), a koppintás nagyít.
 * Ez használati funkció (nem dísz), ezért a `prefers-reduced-motion` nem tiltja —
 * csak a sima átmenetet kapcsolja le.
 *
 * @param {{ getElement: () => (HTMLElement|null), onSwipe?: (dir: 1|-1) => void }} opts
 */
export function useImageZoom({ getElement, onSwipe } = {}) {
    const MAX = 4;
    const state = reactive({ scale: 1, x: 0, y: 0, dragging: false });

    /** @type {Map<number, PointerEvent>} */
    const pointers = new Map();
    let down = null; // { x, y, t, tx, ty }
    let pinch = null; // { dist, scale }

    const zoomed = computed(() => state.scale > 1.01);
    const reduce = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    function reset() {
        state.scale = 1;
        state.x = 0;
        state.y = 0;
        state.dragging = false;
        pointers.clear();
        down = null;
        pinch = null;
    }

    function clampPan() {
        const el = getElement?.();
        if (!el) return;
        const limX = (el.clientWidth * (state.scale - 1)) / 2;
        const limY = (el.clientHeight * (state.scale - 1)) / 2;
        state.x = Math.min(limX, Math.max(-limX, state.x));
        state.y = Math.min(limY, Math.max(-limY, state.y));
    }

    /** A megadott képernyő-pont helyben tartásával nagyít a kívánt léptékre. */
    function zoomTo(nextScale, clientX, clientY) {
        const el = getElement?.();
        if (!el) return;
        const s2 = Math.min(MAX, Math.max(1, nextScale));
        const rect = el.getBoundingClientRect();
        const cx = clientX - (rect.left + rect.width / 2);
        const cy = clientY - (rect.top + rect.height / 2);
        state.x -= (cx / state.scale) * (s2 - state.scale);
        state.y -= (cy / state.scale) * (s2 - state.scale);
        state.scale = s2;
        if (s2 === 1) {
            state.x = 0;
            state.y = 0;
        }
        clampPan();
    }

    const distance = (a, b) => Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
    const midpoint = (a, b) => ({ x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2 });

    function onWheel(e) {
        e.preventDefault();
        zoomTo(state.scale * (e.deltaY < 0 ? 1.18 : 1 / 1.18), e.clientX, e.clientY);
    }

    function onPointerdown(e) {
        pointers.set(e.pointerId, e);
        const pts = [...pointers.values()];
        if (pts.length === 2) {
            pinch = { dist: distance(pts[0], pts[1]) || 1, scale: state.scale };
            down = null;
        } else if (pts.length === 1) {
            down = { x: e.clientX, y: e.clientY, t: Date.now(), tx: state.x, ty: state.y };
        }
        e.currentTarget.setPointerCapture?.(e.pointerId);
    }

    function onPointermove(e) {
        if (!pointers.has(e.pointerId)) return;
        pointers.set(e.pointerId, e);
        const pts = [...pointers.values()];

        if (pts.length === 2 && pinch) {
            const m = midpoint(pts[0], pts[1]);
            zoomTo(pinch.scale * (distance(pts[0], pts[1]) / pinch.dist), m.x, m.y);
            return;
        }
        if (pts.length === 1 && down && zoomed.value) {
            state.dragging = true;
            state.x = down.tx + (e.clientX - down.x);
            state.y = down.ty + (e.clientY - down.y);
            clampPan();
        }
    }

    function onPointerup(e) {
        if (down && pointers.size === 1) {
            const dx = e.clientX - down.x;
            const dy = e.clientY - down.y;
            const moved = Math.hypot(dx, dy);
            if (moved < 6 && Date.now() - down.t < 300) {
                if (zoomed.value) reset();
                else zoomTo(2.2, e.clientX, e.clientY);
            } else if (!zoomed.value && Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.4) {
                onSwipe?.(dx < 0 ? 1 : -1);
            }
        }
        pointers.delete(e.pointerId);
        if (pointers.size < 2) pinch = null;
        if (pointers.size === 0) {
            state.dragging = false;
            down = null;
        }
    }

    function onPointercancel(e) {
        pointers.delete(e.pointerId);
        if (pointers.size < 2) pinch = null;
        if (pointers.size === 0) {
            state.dragging = false;
            down = null;
        }
    }

    const style = computed(() => ({
        transform: `translate3d(${state.x}px, ${state.y}px, 0) scale(${state.scale})`,
        transition: state.dragging || reduce() ? 'none' : 'transform 180ms ease',
        cursor: zoomed.value ? (state.dragging ? 'grabbing' : 'grab') : 'zoom-in',
        touchAction: 'none',
        userSelect: 'none',
        willChange: 'transform',
    }));

    const handlers = {
        wheel: onWheel,
        pointerdown: onPointerdown,
        pointermove: onPointermove,
        pointerup: onPointerup,
        pointercancel: onPointercancel,
    };

    return { zoom: state, zoomed, reset, style, handlers };
}

/**
 * v-reveal — a görgetéssel a képernyőre kerülő elemet finoman megjeleníti
 * (opacity + felcsúszás). A stílust a CSS `--anim-*` változói adják
 * (app.blade.php → AnimationSettings), a lépcsőzést a direktíva argumentuma:
 *
 *   <div v-reveal>...</div>          egyszerű
 *   <div v-reveal="i">...</div>      i * --anim-stagger késleltetés (rács)
 *
 * Kikapcsolt animációnál (`<html data-anim="off">`) vagy `prefers-reduced-motion`
 * esetén nem csinál semmit — az elem azonnal látható.
 */

function motionOff() {
    const ds = document.documentElement.dataset;
    return (
        ds.anim === 'off' ||
        ds.animReveal === 'off' ||
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    );
}

function staggerMs() {
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--anim-stagger');
    return parseInt(raw, 10) || 70;
}

const observers = new WeakMap();

export default {
    mounted(el, binding) {
        if (motionOff()) {
            return;
        }

        const index = Number(binding.value) || 0;
        if (index > 0) {
            el.style.setProperty('--reveal-delay', `${index * staggerMs()}ms`);
        }
        el.setAttribute('data-reveal', '');

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        el.classList.add('is-revealed');
                        observer.unobserve(el);
                    }
                });
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
        );

        observer.observe(el);
        observers.set(el, observer);
    },
    unmounted(el) {
        observers.get(el)?.disconnect();
        observers.delete(el);
    },
};

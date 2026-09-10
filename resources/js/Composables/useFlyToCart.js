/**
 * „Kosárba repülő kép" animáció — a /admin/settings/theme „Animációk" →
 * „Kép a kosárba repül" kapcsolja (`<html data-anim-flycart>`). A kosár-ikon
 * a fejlécben `data-cart-target` attribútummal jelölt.
 *
 * Kikapcsolt animációnál / reduced-motion esetén / hiányzó forrás vagy célpont
 * esetén csendben nem csinál semmit — a kosárba tétel a hívó felelőssége marad.
 */
export function useFlyToCart() {
    function flyToCart(sourceEl, imageUrl = null) {
        if (typeof document === 'undefined') return;
        if (document.documentElement.dataset.animFlycart === 'off') return;
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

        const target = document.querySelector('[data-cart-target]');
        if (!sourceEl || !target) return;

        const from = sourceEl.getBoundingClientRect();
        const to = target.getBoundingClientRect();
        if (!from.width || !to.width) return;

        const src = imageUrl
            || (sourceEl.tagName === 'IMG' ? sourceEl.currentSrc || sourceEl.src : sourceEl.querySelector('img')?.src);
        if (!src) return;

        const size = Math.min(120, Math.max(56, from.width * 0.5));
        const clone = document.createElement('img');
        clone.src = src;
        clone.className = 'fly-to-cart';
        clone.style.width = `${size}px`;
        clone.style.height = `${size * 0.72}px`;
        clone.style.left = `${from.left + from.width / 2 - size / 2}px`;
        clone.style.top = `${from.top + from.height / 2 - size * 0.36}px`;
        document.body.appendChild(clone);

        const dx = to.left + to.width / 2 - (from.left + from.width / 2);
        const dy = to.top + to.height / 2 - (from.top + from.height / 2);

        const anim = clone.animate(
            [
                { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                { transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 40}px) scale(0.7)`, opacity: 0.9, offset: 0.6 },
                { transform: `translate(${dx}px, ${dy}px) scale(0.15)`, opacity: 0.2 },
            ],
            { duration: 620, easing: 'cubic-bezier(0.55, 0, 0.4, 1)' },
        );

        anim.onfinish = () => {
            clone.remove();
            target.classList.remove('cart-target-pop');
            void target.offsetWidth;
            target.classList.add('cart-target-pop');
            target.addEventListener('animationend', () => target.classList.remove('cart-target-pop'), { once: true });
        };
        anim.oncancel = () => clone.remove();
    }

    return { flyToCart };
}

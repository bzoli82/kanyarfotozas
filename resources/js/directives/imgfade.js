/**
 * v-imgfade — a kép nem „bevillan", hanem betöltéskor előúszik.
 * A /admin/settings/theme „Animációk" → „Képek előúsznak" kapcsolja
 * (`<html data-anim-imgfade>`). Cache-elt (már betöltött) képnél nem csinál semmit.
 */
export default {
    mounted(el) {
        if (document.documentElement.dataset.animImgfade === 'off') {
            return;
        }
        if (el.complete && el.naturalWidth > 0) {
            return;
        }
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.45s ease';
        const reveal = () => {
            el.style.opacity = '1';
        };
        el.addEventListener('load', reveal, { once: true });
        el.addEventListener('error', reveal, { once: true });
    },
};

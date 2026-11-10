import { ref, watch, onBeforeUnmount } from 'vue';

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// A number that glides to its new value instead of jumping (readouts, totals).
export function useTween(source, duration = 280) {
    const shown = ref(Number(source()) || 0);
    let frame;

    watch(source, (to) => {
        cancelAnimationFrame(frame);
        to = Number(to) || 0;
        const from = shown.value;
        if (reduced() || from === to) return (shown.value = to);
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            shown.value = from + (to - from) * (1 - Math.pow(1 - t, 3));
            if (t < 1) frame = requestAnimationFrame(tick);
            else shown.value = to;
        };
        frame = requestAnimationFrame(tick);
    });

    onBeforeUnmount(() => cancelAnimationFrame(frame));
    return shown;
}

// A short tap on devices that support it (Android); silently nothing elsewhere.
export function tap(ms = 12) {
    try {
        navigator.vibrate?.(ms);
    } catch {
        /* not available */
    }
}

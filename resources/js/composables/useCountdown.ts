import { onBeforeUnmount, ref } from 'vue';

/**
 * A seconds countdown that calls `onFinish` when it reaches zero.
 */
export function useCountdown(seconds: number, onFinish: () => void) {
    const remaining = ref(0);
    let timer: ReturnType<typeof setInterval> | undefined;

    function stop() {
        clearInterval(timer);
        timer = undefined;
    }

    function start() {
        stop();
        remaining.value = seconds;

        timer = setInterval(() => {
            remaining.value -= 1;

            if (remaining.value <= 0) {
                stop();
                onFinish();
            }
        }, 1000);
    }

    onBeforeUnmount(stop);

    return { remaining, start, stop };
}

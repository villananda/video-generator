import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';
import { status as statusRoute } from '@/routes/videos';
import type { VideoStatusPayload } from '@/types';

const POLL_INTERVAL_MS = 4000;

/**
 * Polls the status endpoint until the video reaches a final state.
 * Swap the internals for SSE/WebSocket later without touching components.
 */
export function useVideoStatus(initial: VideoStatusPayload): {
    video: Ref<VideoStatusPayload>;
    isFinal: () => boolean;
} {
    const video = ref(initial) as Ref<VideoStatusPayload>;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let isStopped = false;

    const isFinal = (): boolean =>
        video.value.status === 'done' || video.value.status === 'failed';

    async function poll(): Promise<void> {
        try {
            const response = await fetch(statusRoute.url(video.value.id), {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                video.value = { ...video.value, ...(await response.json()) };
            }
        } finally {
            schedule();
        }
    }

    function schedule(): void {
        if (!isStopped && !isFinal()) {
            timer = setTimeout(poll, POLL_INTERVAL_MS);
        }
    }

    onMounted(schedule);
    onBeforeUnmount(() => {
        isStopped = true;
        clearTimeout(timer);
    });

    return { video, isFinal };
}

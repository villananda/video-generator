<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleAlert, Download, Plus, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useVideoStatus } from '@/composables/useVideoStatus';
import { home } from '@/routes';
import { retry } from '@/routes/videos';
import type { Video } from '@/types';

const props = defineProps<{ video: Video }>();

const { video: status } = useVideoStatus(props.video);
const isRetrying = ref(false);

const statusLabel = computed(
    () =>
        ({
            queued: 'Antre',
            processing: 'Diproses',
            done: 'Selesai',
            failed: 'Gagal',
        })[status.value.status],
);

function retryVideo(): void {
    router.post(
        retry.url(props.video.id),
        {},
        {
            onStart: () => (isRetrying.value = true),
            onFinish: () => (isRetrying.value = false),
        },
    );
}
</script>

<template>
    <Head title="Hasil Video" />

    <div class="mx-auto grid max-w-2xl gap-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">Hasil</h1>
            <Badge
                :variant="
                    status.status === 'failed' ? 'destructive' : 'secondary'
                "
                aria-live="polite"
            >
                {{ statusLabel }}
            </Badge>
        </div>

        <div
            :class="[
                'mx-auto flex w-full items-center justify-center overflow-hidden rounded-xl border bg-muted',
                video.aspect_ratio === '9:16'
                    ? 'aspect-[9/16] max-w-sm'
                    : 'aspect-video',
            ]"
        >
            <video
                v-if="status.status === 'done' && status.video_url"
                :src="status.video_url"
                class="size-full"
                controls
                autoplay
                playsinline
            />
            <div v-else-if="status.status === 'failed'" class="p-6 text-center">
                <CircleAlert class="mx-auto size-10 text-destructive" />
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-3 p-6 text-center text-sm text-muted-foreground"
            >
                <Spinner class="size-8" />
                <p v-if="status.status === 'queued'">
                    Dalam antrean
                    <template v-if="status.queue_position">
                        (posisi {{ status.queue_position }})
                    </template>
                </p>
                <p v-else>Sedang membuat video, biasanya 1–3 menit...</p>
                <p class="text-xs">
                    Anda boleh menutup halaman ini dan kembali lewat URL yang
                    sama.
                </p>
            </div>
        </div>

        <Alert v-if="status.status === 'failed'" variant="destructive">
            <CircleAlert />
            <AlertTitle>Video gagal dibuat</AlertTitle>
            <AlertDescription>{{ status.error_message }}</AlertDescription>
        </Alert>

        <div class="flex flex-wrap gap-2">
            <Button
                v-if="status.status === 'done' && status.video_url"
                as-child
            >
                <a :href="status.video_url" :download="`video-${video.id}.mp4`">
                    <Download />
                    Unduh
                </a>
            </Button>
            <Button
                v-if="status.can_retry"
                :disabled="isRetrying"
                @click="retryVideo"
            >
                <RotateCcw />
                Coba lagi
            </Button>
            <Button variant="outline" as-child>
                <Link :href="home()">
                    <Plus />
                    Buat video baru
                </Link>
            </Button>
        </div>

        <dl class="grid gap-3 rounded-lg border p-4 text-sm">
            <div>
                <dt class="text-muted-foreground">Deskripsi</dt>
                <dd class="mt-1 whitespace-pre-line">{{ video.prompt }}</dd>
            </div>
            <div class="flex flex-wrap gap-6">
                <div>
                    <dt class="text-muted-foreground">Mode</dt>
                    <dd>
                        {{
                            video.mode === 'image'
                                ? 'Gambar → Video'
                                : 'Teks → Video'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Rasio</dt>
                    <dd>{{ video.aspect_ratio }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Resolusi</dt>
                    <dd>{{ video.resolution }}</dd>
                </div>
            </div>
        </dl>
    </div>
</template>

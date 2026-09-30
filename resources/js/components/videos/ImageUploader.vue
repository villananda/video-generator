<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, watch } from 'vue';

const model = defineModel<File | null>({ required: true });

const previewUrl = computed(() =>
    model.value ? URL.createObjectURL(model.value) : null,
);

watch(previewUrl, (_, oldUrl) => oldUrl && URL.revokeObjectURL(oldUrl));
onBeforeUnmount(
    () => previewUrl.value && URL.revokeObjectURL(previewUrl.value),
);

function onChange(event: Event): void {
    model.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>

<template>
    <div
        class="relative flex min-h-48 items-center justify-center overflow-hidden rounded-lg border border-dashed"
    >
        <template v-if="previewUrl">
            <img
                :src="previewUrl"
                alt="Pratinjau gambar"
                class="max-h-72 w-full object-contain"
            />
            <button
                type="button"
                class="absolute top-2 right-2 rounded-full bg-background/80 p-1.5 shadow"
                aria-label="Hapus gambar"
                @click="model = null"
            >
                <X class="size-4" />
            </button>
        </template>
        <label
            v-else
            class="flex w-full cursor-pointer flex-col items-center gap-2 p-8 text-center text-sm text-muted-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
        >
            <ImagePlus class="size-8" />
            <span>Klik untuk unggah gambar (JPG, PNG, WebP, maks 10 MB)</span>
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="onChange"
            />
        </label>
    </div>
</template>

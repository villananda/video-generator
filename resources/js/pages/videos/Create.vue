<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import ImageUploader from '@/components/videos/ImageUploader.vue';
import OptionPicker from '@/components/videos/OptionPicker.vue';
import { store } from '@/routes/videos';
import type { Video } from '@/types';

const MAX_PROMPT_LENGTH = 1000;

const presets = [
    {
        label: 'Pantai senja',
        prompt: 'A slow drone shot over a calm tropical beach at sunset, golden light reflecting on gentle waves, palm trees swaying in the breeze.',
    },
    {
        label: 'Kucing lucu',
        prompt: 'A fluffy orange kitten playfully chasing a ball of yarn on a cozy wooden floor, soft morning light, close-up, playful mood.',
    },
    {
        label: 'Produk',
        prompt: 'A premium product shot of a coffee cup rotating slowly on a marble table, steam rising, soft studio lighting, shallow depth of field.',
    },
    {
        label: 'Kota neon',
        prompt: 'A cinematic night street in a futuristic city with glowing neon signs, rain-soaked pavement reflecting lights, camera slowly dollying forward.',
    },
];

const form = useForm<{
    mode: Video['mode'];
    prompt: string;
    image: File | null;
    aspect_ratio: Video['aspect_ratio'];
    resolution: Video['resolution'];
    single_scene: boolean;
    no_dialogue: boolean;
    background_music: boolean;
}>({
    mode: 'text',
    prompt: '',
    image: null,
    aspect_ratio: '16:9',
    resolution: '720p',
    single_scene: false,
    no_dialogue: false,
    background_music: false,
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        image: data.mode === 'image' ? data.image : null,
    })).submit(store(), { forceFormData: true });
}
</script>

<template>
    <Head title="Buat Video" />

    <form class="mx-auto grid max-w-2xl gap-6" @submit.prevent="submit">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Buat Video AI</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Ubah deskripsi teks atau gambar menjadi video pendek dalam
                hitungan menit.
            </p>
        </div>

        <OptionPicker
            v-model="form.mode"
            label="Mode"
            :options="[
                { value: 'text', label: 'Teks → Video' },
                { value: 'image', label: 'Gambar → Video' },
            ]"
        />

        <div v-if="form.mode === 'image'" class="grid gap-2">
            <Label>Gambar (bingkai pertama)</Label>
            <ImageUploader v-model="form.image" />
            <p class="text-xs text-muted-foreground">
                Gambar orang terkenal/dikenali tidak didukung. Pastikan Anda
                memiliki hak atas gambar ini.
            </p>
            <InputError :message="form.errors.image" />
        </div>

        <div class="grid gap-2">
            <Label for="prompt">Deskripsi</Label>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="5"
                :maxlength="MAX_PROMPT_LENGTH"
                :aria-invalid="!!form.errors.prompt"
                :placeholder="
                    form.mode === 'image'
                        ? 'Jelaskan gerakan: kamera, gerak subjek, efek lingkungan...'
                        : 'Jelaskan video yang ingin Anda buat...'
                "
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
            />
            <div
                class="flex items-start justify-between gap-4 text-xs text-muted-foreground"
            >
                <span>Hasil terbaik dengan prompt berbahasa Inggris.</span>
                <span>{{ form.prompt.length }}/{{ MAX_PROMPT_LENGTH }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="rounded-full"
                    @click="form.prompt = preset.prompt"
                >
                    {{ preset.label }}
                </Button>
            </div>
            <InputError :message="form.errors.prompt" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <OptionPicker
                v-model="form.aspect_ratio"
                label="Rasio"
                :options="[
                    { value: '9:16', label: '9:16 Vertikal' },
                    { value: '16:9', label: '16:9 Lanskap' },
                ]"
            />
            <OptionPicker
                v-model="form.resolution"
                label="Resolusi"
                :options="[
                    { value: '360p', label: '360p', badge: 'Hemat' },
                    { value: '720p', label: '720p' },
                    { value: '1080p', label: '1080p', badge: 'Upscaled' },
                ]"
            />
        </div>

        <div class="grid gap-3">
            <span class="text-sm font-medium">Opsi</span>
            <Label class="font-normal">
                <Checkbox v-model="form.single_scene" />
                Satu adegan tanpa potongan
            </Label>
            <Label class="font-normal">
                <Checkbox v-model="form.no_dialogue" />
                Tanpa dialog
            </Label>
            <Label class="font-normal">
                <Checkbox v-model="form.background_music" />
                Musik latar
            </Label>
        </div>

        <Button type="submit" size="lg" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            <Sparkles v-else />
            Buat Video
        </Button>
    </form>
</template>

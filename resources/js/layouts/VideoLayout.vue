<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, Sun } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { home } from '@/routes';

const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4"
            >
                <Link :href="home()" class="flex items-center">
                    <AppLogo />
                </Link>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Ganti tema"
                    @click="
                        updateAppearance(
                            resolvedAppearance === 'dark' ? 'light' : 'dark',
                        )
                    "
                >
                    <Sun v-if="resolvedAppearance === 'dark'" />
                    <Moon v-else />
                </Button>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-6 md:py-10">
            <slot />
        </main>
        <footer class="pb-8 text-center text-xs text-muted-foreground">
            Video dibuat dengan AI (Gemini Omni Flash) dan memuat watermark
            SynthID.
        </footer>
    </div>
</template>

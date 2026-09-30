<script setup lang="ts" generic="T extends string">
defineProps<{
    label: string;
    options: { value: T; label: string; badge?: string }[];
}>();

const model = defineModel<T>({ required: true });
</script>

<template>
    <fieldset class="grid gap-2">
        <legend class="mb-2 text-sm font-medium">{{ label }}</legend>
        <div class="flex flex-wrap gap-2">
            <label
                v-for="option in options"
                :key="option.value"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50',
                    model === option.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'hover:bg-accent',
                ]"
            >
                <input
                    v-model="model"
                    type="radio"
                    class="sr-only"
                    :value="option.value"
                />
                {{ option.label }}
                <span
                    v-if="option.badge"
                    class="rounded-full bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-secondary-foreground"
                >
                    {{ option.badge }}
                </span>
            </label>
        </div>
    </fieldset>
</template>

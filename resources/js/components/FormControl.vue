<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { computed, inject, provide, ref, Ref, useId } from 'vue';

interface FormFieldProps {
    as?: string;
    name?: string;
    label?: string;
    description?: string;
    help?: string;
    error?: string | boolean;
    hint?: string;
    size?: 'sm' | 'md' | 'lg';
    required?: boolean;
    class?: string;
    eagerValidation?: boolean;
    validateOnInputDelay?: number;
}

const props = defineProps<FormFieldProps>();
const slots = defineSlots();

const id = useId();
const ariaId = id;

provide('form-field-id', id);

const formErrors = inject<Ref<{ name: string; message: string }[]>>(
    'form-errors',
    ref([]),
);

const error = computed(() => {
    if (typeof props.error === 'string' || typeof props.error === 'boolean')
        return props.error;
    const found = formErrors.value.find((err) => err.name === props.name);
    return found?.message;
});

provide('form-field-context', {
    error: error.value,
    id,
    name: props.name,
});
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <!-- Label + Hint -->
        <div v-if="props.label || slots.label">
            <div class="flex items-center justify-between">
                <Label
                    :for="id"
                    class="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                >
                    <slot name="label" :label="props.label">
                        {{ props.label }}
                    </slot>
                    <span v-if="props.required" class="text-red-500">*</span>
                </Label>
                <slot name="actions" />
            </div>

            <p
                v-if="props.hint || slots.hint"
                :id="`${ariaId}-hint`"
                class="text-xs text-muted-foreground"
            >
                <slot name="hint" :hint="props.hint">
                    {{ props.hint }}
                </slot>
            </p>
        </div>

        <!-- Description -->
        <p
            v-if="props.description || slots.description"
            :id="`${ariaId}-description`"
            class="text-sm text-muted-foreground"
        >
            <slot name="description" :description="props.description">
                {{ props.description }}
            </slot>
        </p>

        <!-- Main Input -->
        <div>
            <slot :id="id" :error="error" />
        </div>

        <!-- Error or Help -->
        <p
            v-if="error || slots.error"
            :id="`${ariaId}-error`"
            class="text-sm text-red-500"
        >
            <slot name="error" :error="error">
                {{ error }}
            </slot>
        </p>
        <p
            v-else-if="props.help || slots.help"
            class="text-sm text-muted-foreground"
        >
            <slot name="help" :help="props.help">
                {{ props.help }}
            </slot>
        </p>
    </div>
</template>

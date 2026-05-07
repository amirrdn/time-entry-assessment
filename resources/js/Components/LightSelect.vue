<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select option',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const search = ref('');
const rootRef = ref(null);
const triggerRef = ref(null);
const searchInputRef = ref(null);

const normalizedOptions = computed(() =>
    props.options.map((option) => ({
        value: option.value,
        label: option.label ?? String(option.value),
    }))
);

const selectedOption = computed(() =>
    normalizedOptions.value.find((option) => option.value === props.modelValue) ?? null
);

const filteredOptions = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) {
        return normalizedOptions.value;
    }

    return normalizedOptions.value.filter((option) =>
        option.label.toLowerCase().includes(keyword)
    );
});

const displayLabel = computed(() => selectedOption.value?.label ?? props.placeholder);

const open = async () => {
    if (props.disabled) {
        return;
    }

    isOpen.value = true;
    search.value = '';
    await nextTick();
    searchInputRef.value?.focus();
};

const close = () => {
    isOpen.value = false;
};

const toggle = () => {
    if (isOpen.value) {
        close();
        return;
    }

    open();
};

const selectOption = (option) => {
    emit('update:modelValue', option.value);
    emit('change', option.value);
    close();
    nextTick(() => {
        triggerRef.value?.focus();
    });
};

const handleClickOutside = (event) => {
    if (!rootRef.value || rootRef.value.contains(event.target)) {
        return;
    }

    close();
};

const focus = () => {
    triggerRef.value?.focus();
};

defineExpose({ focus });

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            close();
        }
    }
);

document.addEventListener('click', handleClickOutside);

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            ref="triggerRef"
            type="button"
            class="flex w-full items-center justify-between rounded-md bg-white px-3 py-2 text-left text-sm focus:outline-none focus:ring-1 disabled:cursor-not-allowed disabled:bg-gray-50"
            :class="
                invalid
                    ? 'border border-red-300 focus:border-red-500 focus:ring-red-500'
                    : 'border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
            "
            :disabled="disabled"
            @click="toggle"
        >
            <span class="truncate" :class="selectedOption ? 'text-gray-800' : 'text-gray-400'">
                {{ displayLabel }}
            </span>
            <span class="ml-2 text-xs text-gray-500">▼</span>
        </button>

        <div
            v-if="isOpen"
            class="absolute z-[70] mt-1 w-full rounded-md border border-gray-200 bg-white p-2 shadow-lg"
        >
            <input
                ref="searchInputRef"
                v-model="search"
                type="text"
                placeholder="Search..."
                class="mb-2 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @keydown.esc.prevent="close"
            />

            <ul class="max-h-48 overflow-y-auto">
                <li v-if="!filteredOptions.length" class="px-2 py-2 text-sm text-gray-500">
                    No options found.
                </li>
                <li
                    v-for="option in filteredOptions"
                    :key="`${option.value}`"
                    class="cursor-pointer rounded px-2 py-2 text-sm text-gray-700 hover:bg-indigo-50"
                    @click="selectOption(option)"
                >
                    {{ option.label }}
                </li>
            </ul>
        </div>
    </div>
</template>

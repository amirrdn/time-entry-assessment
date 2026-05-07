<script setup>
import Modal from '@/Components/Modal.vue';
import LightSelect from '@/Components/LightSelect.vue';
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    editForm: {
        type: Object,
        required: true,
    },
    editMeta: {
        type: Object,
        required: true,
    },
    companyOptions: {
        type: Array,
        default: () => [],
    },
    editErrors: {
        type: Array,
        default: () => [],
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save', 'company-change']);

const toOptions = (items) =>
    items.map((item) => ({
        value: item.id,
        label: item.name,
    }));

const companySelectOptions = computed(() => toOptions(props.companyOptions));
const employeeSelectOptions = computed(() => toOptions(props.editMeta.employees ?? []));
const projectSelectOptions = computed(() => toOptions(props.editMeta.projects ?? []));
const taskSelectOptions = computed(() => toOptions(props.editMeta.tasks ?? []));
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <h4 class="text-base font-semibold text-gray-900">Edit Time Entry</h4>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <LightSelect
                    v-model="editForm.company_id"
                    :options="companySelectOptions"
                    placeholder="Select company"
                    @change="emit('company-change')"
                />

                <input
                    v-model="editForm.date"
                    type="date"
                    class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <input
                    v-model="editForm.hours"
                    type="number"
                    min="0.25"
                    step="0.25"
                    class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <LightSelect
                    v-model="editForm.employee_id"
                    :options="employeeSelectOptions"
                    placeholder="Select employee"
                />

                <LightSelect
                    v-model="editForm.project_id"
                    :options="projectSelectOptions"
                    placeholder="Select project"
                />

                <LightSelect
                    v-model="editForm.task_id"
                    :options="taskSelectOptions"
                    placeholder="Select task"
                />
            </div>

            <div
                v-if="editErrors.length"
                class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
                <ul class="list-disc pl-5">
                    <li v-for="(message, index) in editErrors" :key="index">{{ message }}</li>
                </ul>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isSaving"
                    @click="emit('save')"
                >
                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>
    </Modal>
</template>

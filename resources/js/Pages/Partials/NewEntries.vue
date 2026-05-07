<script setup>
import axios from 'axios';
import LightSelect from '@/Components/LightSelect.vue';
import { computed, nextTick, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';

const props = defineProps({
    companyId: {
        type: [Number, null],
        default: null,
    },
    companies: {
        type: Array,
        default: () => [],
    },
});

const rows = ref([]);
const isSaving = ref(false);
const isLoadingMeta = ref(false);
const isBootstrapping = ref(false);
const generalErrors = ref([]);
const rowErrors = reactive({});
const rowFieldErrors = reactive({});
const companySelectRefs = ref([]);

const metaDataCache = reactive({});

const globalEmployees = computed(() => metaDataCache.all?.employees ?? []);
const globalProjects = computed(() => metaDataCache.all?.projects ?? []);
const globalTasks = computed(() => metaDataCache.all?.tasks ?? []);
const defaultCompanyId = computed(() => props.companyId ?? props.companies[0]?.id ?? null);
const hasIncompleteRows = computed(() =>
    rows.value.some((row) =>
        !row.company_id || !row.date || !row.employee_id || !row.project_id || !row.task_id || !row.hours
    )
);

const createEmptyRow = () => ({
    company_id: defaultCompanyId.value,
    date: '',
    employee_id: null,
    project_id: null,
    task_id: null,
    hours: '',
});

const cloneRow = (sourceRow) => ({
    company_id: sourceRow?.company_id ?? defaultCompanyId.value,
    date: sourceRow?.date ?? '',
    employee_id: sourceRow?.employee_id ?? null,
    project_id: sourceRow?.project_id ?? null,
    task_id: sourceRow?.task_id ?? null,
    hours: sourceRow?.hours ?? '',
});

const toOptions = (items) =>
    items.map((item) => ({
        value: item.id,
        label: item.name,
    }));

const getRowMeta = (companyId) => {
    if (!companyId) {
        return {
            employees: globalEmployees.value,
            projects: globalProjects.value,
            tasks: globalTasks.value,
        };
    }

    return metaDataCache[companyId] ?? {
        employees: [],
        projects: [],
        tasks: [],
    };
};

const fetchMetaData = async (companyId = null) => {
    const cacheKey = companyId ? String(companyId) : 'all';

    if (metaDataCache[cacheKey]) {
        return metaDataCache[cacheKey];
    }

    isLoadingMeta.value = true;

    try {
        const params = companyId ? { company_id: companyId } : {};
        const { data } = await axios.get('/api/time-entries/meta-data', { params });
        const payload = {
            employees: data.data?.employees ?? [],
            projects: data.data?.projects ?? [],
            tasks: data.data?.tasks ?? [],
        };

        metaDataCache[cacheKey] = payload;
        return payload;
    } finally {
        isLoadingMeta.value = false;
    }
};

const ensureRowMeta = async (row) => {
    await fetchMetaData(row.company_id ?? null);
};

const resetDependentFields = (row) => {
    row.employee_id = null;
    row.project_id = null;
    row.task_id = null;
};

const addRow = async (focus = false, sourceRow = null) => {
    rows.value.push(sourceRow ? cloneRow(sourceRow) : createEmptyRow());

    if (focus) {
        await nextTick();
        const index = rows.value.length - 1;
        companySelectRefs.value[index]?.focus();
    }
};

const duplicateRow = async (index) => {
    const sourceRow = rows.value[index];
    if (!sourceRow) {
        return;
    }

    rows.value.splice(index + 1, 0, cloneRow(sourceRow));
    await nextTick();
    companySelectRefs.value[index + 1]?.focus();
};

const removeRow = (index) => {
    rows.value.splice(index, 1);
    delete rowErrors[index];

    if (!rows.value.length) {
        rows.value.push(createEmptyRow());
    }
};

const clearErrors = () => {
    generalErrors.value = [];
    Object.keys(rowErrors).forEach((key) => {
        delete rowErrors[key];
    });
    Object.keys(rowFieldErrors).forEach((key) => {
        delete rowFieldErrors[key];
    });
};

const getFieldErrors = (rowIndex, fieldName) =>
    rowFieldErrors[rowIndex]?.[fieldName] ?? [];

const hasFieldError = (rowIndex, fieldName) =>
    getFieldErrors(rowIndex, fieldName).length > 0;

const firstFieldError = (rowIndex, fieldName) =>
    getFieldErrors(rowIndex, fieldName)[0] ?? '';

const applyValidationErrors = (errors) => {
    clearErrors();

    Object.entries(errors).forEach(([field, messages]) => {
        if (!Array.isArray(messages)) {
            return;
        }

        const match = field.match(/^entries\.(\d+)\./);
        if (match) {
            const rowIndex = Number(match[1]);
            const fieldName = field.replace(/^entries\.\d+\./, '');
            rowErrors[rowIndex] = [...(rowErrors[rowIndex] ?? []), ...messages];
            rowFieldErrors[rowIndex] = rowFieldErrors[rowIndex] ?? {};
            rowFieldErrors[rowIndex][fieldName] = [
                ...(rowFieldErrors[rowIndex][fieldName] ?? []),
                ...messages,
            ];
            return;
        }

        if (field === 'entries') {
            generalErrors.value.push(...messages);
        }
    });
};

const handleCompanyChange = async (row) => {
    resetDependentFields(row);
    await ensureRowMeta(row);
};

const handleHoursTab = async (event, rowIndex) => {
    if (rowIndex !== rows.value.length - 1) {
        return;
    }

    event.preventDefault();
    await addRow(true);
};

const saveAll = async () => {
    clearErrors();

    if (hasIncompleteRows.value) {
        generalErrors.value = ['Please complete all required fields before saving.'];
        return;
    }

    isSaving.value = true;

    try {
        await axios.post('/api/time-entries', {
            entries: rows.value.map((row) => ({
                company_id: Number(row.company_id),
                employee_id: Number(row.employee_id),
                project_id: Number(row.project_id),
                task_id: Number(row.task_id),
                date: row.date,
                hours: Number(row.hours),
            })),
        });

        rows.value = [createEmptyRow()];
    } catch (error) {
        if (error.response?.status === 422) {
            applyValidationErrors(error.response.data?.errors ?? {});
        } else {
            generalErrors.value = ['Unexpected error occurred while saving entries.'];
        }
    } finally {
        isSaving.value = false;
    }
};

const handleGlobalShortcut = (event) => {
    const isCtrlOrMeta = event.ctrlKey || event.metaKey;
    const key = event.key.toLowerCase();

    if (isCtrlOrMeta && key === 'enter') {
        event.preventDefault();
        if (!isSaving.value && !hasIncompleteRows.value && !isBootstrapping.value && !isLoadingMeta.value) {
            saveAll();
        }
    }

    if (event.altKey && key === 'n') {
        event.preventDefault();
        const lastRow = rows.value[rows.value.length - 1] ?? null;
        addRow(true, lastRow);
    }
};

const companyOptions = computed(() => toOptions(props.companies));
const employeeOptionsForRow = (row) => toOptions(getRowMeta(row.company_id).employees);
const projectOptionsForRow = (row) => toOptions(getRowMeta(row.company_id).projects);
const taskOptionsForRow = (row) => toOptions(getRowMeta(row.company_id).tasks);

watch(
    () => props.companyId,
    async (companyId) => {
        await fetchMetaData(companyId ?? null);

        rows.value.forEach((row) => {
            row.company_id = companyId ?? null;
            resetDependentFields(row);
        });
    }
);

watch(
    () => props.companies,
    async (companies) => {
        if (!companies.length) {
            return;
        }

        const companiesToLoad = new Set();

        rows.value.forEach((row) => {
            if (row.company_id === null) {
                row.company_id = defaultCompanyId.value;
            }

            if (row.company_id) {
                companiesToLoad.add(row.company_id);
            }
        });

        await Promise.all(
            Array.from(companiesToLoad).map((companyId) => fetchMetaData(companyId))
        );
    },
    { deep: true }
);

const init = async () => {
    isBootstrapping.value = true;

    try {
        await fetchMetaData(null);
        if (defaultCompanyId.value) {
            await fetchMetaData(defaultCompanyId.value);
        }
    } catch (error) {
        generalErrors.value = ['Failed to load metadata. Please refresh the page.'];
    } finally {
        if (!rows.value.length) {
            rows.value = [createEmptyRow()];
        }
        isBootstrapping.value = false;
    }
};

onMounted(() => {
    init();
    window.addEventListener('keydown', handleGlobalShortcut);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalShortcut);
});
</script>

<template>
    <section class="space-y-4">
        <div
            v-if="isBootstrapping"
            class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700"
        >
            Loading form data...
        </div>

        <div class="space-y-3">
            <div
                v-for="(row, index) in rows"
                :key="index"
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-800">Entry {{ index + 1 }}</p>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                            @click="duplicateRow(index)"
                        >
                            Duplicate
                        </button>
                        <button
                            type="button"
                            class="text-sm font-medium text-red-600 hover:text-red-700"
                            @click="removeRow(index)"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Company</label>
                        <LightSelect
                            :ref="(el) => (companySelectRefs[index] = el)"
                            v-model="row.company_id"
                            :options="companyOptions"
                            placeholder="Select company"
                            :disabled="isLoadingMeta"
                            :invalid="hasFieldError(index, 'company_id')"
                            @change="() => handleCompanyChange(row)"
                        />
                        <p v-if="hasFieldError(index, 'company_id')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'company_id') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Date</label>
                        <input
                            v-model="row.date"
                            type="date"
                            class="w-full rounded-md text-sm"
                            :class="
                                hasFieldError(index, 'date')
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
                            "
                        />
                        <p v-if="hasFieldError(index, 'date')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'date') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Hours</label>
                        <input
                            v-model="row.hours"
                            type="number"
                            min="0.25"
                            step="0.25"
                            class="w-full rounded-md text-sm"
                            :class="
                                hasFieldError(index, 'hours')
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                                    : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
                            "
                            @keydown.tab.prevent="handleHoursTab($event, index)"
                        />
                        <p v-if="hasFieldError(index, 'hours')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'hours') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Employee</label>
                        <LightSelect
                            v-model="row.employee_id"
                            :options="employeeOptionsForRow(row)"
                            placeholder="Select employee"
                            :invalid="hasFieldError(index, 'employee_id')"
                        />
                        <p v-if="hasFieldError(index, 'employee_id')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'employee_id') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Project</label>
                        <LightSelect
                            v-model="row.project_id"
                            :options="projectOptionsForRow(row)"
                            placeholder="Select project"
                            :invalid="hasFieldError(index, 'project_id')"
                        />
                        <p v-if="hasFieldError(index, 'project_id')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'project_id') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Task</label>
                        <LightSelect
                            v-model="row.task_id"
                            :options="taskOptionsForRow(row)"
                            placeholder="Select task"
                            :invalid="hasFieldError(index, 'task_id')"
                        />
                        <p v-if="hasFieldError(index, 'task_id')" class="mt-1 text-xs text-red-600">
                            {{ firstFieldError(index, 'task_id') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-for="(messages, rowIndex) in rowErrors"
            :key="rowIndex"
            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            <p class="font-semibold">Row {{ Number(rowIndex) + 1 }}</p>
            <ul class="list-disc pl-5">
                <li v-for="(message, messageIndex) in messages" :key="messageIndex">{{ message }}</li>
            </ul>
        </div>

        <div
            v-if="generalErrors.length"
            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            <ul class="list-disc pl-5">
                <li v-for="(message, index) in generalErrors" :key="index">{{ message }}</li>
            </ul>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-gray-500">
                    Shortcuts: <span class="font-medium text-gray-700">Alt+N</span> add row,
                    <span class="font-medium text-gray-700">Ctrl/Cmd+Enter</span> save all.
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        @click="addRow(false, rows[rows.length - 1] ?? null)"
                    >
                        + Add Row (Reuse Last)
                    </button>

                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isSaving || isBootstrapping || isLoadingMeta || hasIncompleteRows"
                        @click="saveAll"
                    >
                        {{ isSaving ? 'Saving...' : 'Save All' }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

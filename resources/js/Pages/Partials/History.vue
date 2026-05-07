<script setup>
import axios from 'axios';
import HistoryEditModal from '@/Pages/Partials/HistoryEditModal.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    companyId: {
        type: [Number, null],
        default: null,
    },
});

const isLoading = ref(false);
const isSavingEdit = ref(false);
const search = ref('');
const entries = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const sortBy = ref('date_desc');
const editErrors = ref([]);
const editEntryId = ref(null);
const editForm = ref({
    company_id: null,
    employee_id: null,
    project_id: null,
    task_id: null,
    date: '',
    hours: '',
});
const editMeta = ref({
    employees: [],
    projects: [],
    tasks: [],
});

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const fetchHistory = async () => {
    isLoading.value = true;

    try {
        const params = props.companyId ? { company_id: props.companyId } : {};
        const { data } = await axios.get('/api/time-entries', { params });
        entries.value = data.data ?? [];
    } finally {
        isLoading.value = false;
    }
};

const fetchMetaData = async (companyId = null) => {
    const params = companyId ? { company_id: companyId } : {};
    const { data } = await axios.get('/api/time-entries/meta-data', { params });

    return {
        employees: data.data?.employees ?? [],
        projects: data.data?.projects ?? [],
        tasks: data.data?.tasks ?? [],
    };
};

const filteredEntries = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return entries.value;
    }

    return entries.value.filter((entry) => {
        const employeeName = entry.employee?.name?.toLowerCase() ?? '';
        const projectName = entry.project?.name?.toLowerCase() ?? '';

        return employeeName.includes(keyword) || projectName.includes(keyword);
    });
});

const sortedEntries = computed(() => {
    const list = [...filteredEntries.value];

    list.sort((a, b) => {
        if (sortBy.value === 'date_asc') {
            return new Date(a.date).getTime() - new Date(b.date).getTime();
        }

        if (sortBy.value === 'date_desc') {
            return new Date(b.date).getTime() - new Date(a.date).getTime();
        }

        if (sortBy.value === 'hours_desc') {
            return Number(b.hours) - Number(a.hours);
        }

        if (sortBy.value === 'employee_asc') {
            return (a.employee?.name ?? '').localeCompare(b.employee?.name ?? '');
        }

        if (sortBy.value === 'project_asc') {
            return (a.project?.name ?? '').localeCompare(b.project?.name ?? '');
        }

        return 0;
    });

    return list;
});

const companyOptions = computed(() => {
    const map = new Map();
    entries.value.forEach((entry) => {
        if (entry.company?.id) {
            map.set(entry.company.id, entry.company.name);
        }
    });

    return Array.from(map.entries()).map(([id, name]) => ({ id, name }));
});

const summaryTotals = computed(() => {
    const totalHours = sortedEntries.value.reduce((sum, entry) => sum + Number(entry.hours), 0);
    const uniqueEmployees = new Set(sortedEntries.value.map((entry) => entry.employee?.id).filter(Boolean)).size;
    const uniqueProjects = new Set(sortedEntries.value.map((entry) => entry.project?.id).filter(Boolean)).size;
    const uniqueTasks = new Set(sortedEntries.value.map((entry) => entry.task?.id).filter(Boolean)).size;
    const uniqueDates = new Set(sortedEntries.value.map((entry) => entry.date).filter(Boolean)).size;

    return {
        totalHours: totalHours.toFixed(2),
        uniqueEmployees,
        uniqueProjects,
        uniqueTasks,
        uniqueDates,
    };
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(sortedEntries.value.length / perPage.value))
);

const paginatedEntries = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return sortedEntries.value.slice(start, end);
});

const pageInfo = computed(() => {
    if (!sortedEntries.value.length) {
        return '0-0 of 0';
    }

    const start = (currentPage.value - 1) * perPage.value + 1;
    const end = Math.min(currentPage.value * perPage.value, sortedEntries.value.length);
    return `${start}-${end} of ${sortedEntries.value.length}`;
});

const goToPrevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value -= 1;
    }
};

const goToNextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value += 1;
    }
};

const closeEdit = () => {
    editEntryId.value = null;
    editErrors.value = [];
    editForm.value = {
        company_id: null,
        employee_id: null,
        project_id: null,
        task_id: null,
        date: '',
        hours: '',
    };
    editMeta.value = {
        employees: [],
        projects: [],
        tasks: [],
    };
};

const openEdit = async (entry) => {
    editEntryId.value = entry.id;
    editErrors.value = [];
    editForm.value = {
        company_id: entry.company?.id ?? null,
        employee_id: entry.employee?.id ?? null,
        project_id: entry.project?.id ?? null,
        task_id: entry.task?.id ?? null,
        date: entry.date ?? '',
        hours: entry.hours ?? '',
    };
    editMeta.value = await fetchMetaData(editForm.value.company_id);
};

const onEditCompanyChange = async () => {
    editForm.value.employee_id = null;
    editForm.value.project_id = null;
    editForm.value.task_id = null;
    editMeta.value = await fetchMetaData(editForm.value.company_id);
};

const saveEdit = async () => {
    if (!editEntryId.value) {
        return;
    }

    editErrors.value = [];
    isSavingEdit.value = true;

    try {
        await axios.put(`/api/time-entries/${editEntryId.value}`, {
            company_id: Number(editForm.value.company_id),
            employee_id: Number(editForm.value.employee_id),
            project_id: Number(editForm.value.project_id),
            task_id: Number(editForm.value.task_id),
            date: editForm.value.date,
            hours: Number(editForm.value.hours),
        });

        await fetchHistory();
        closeEdit();
    } catch (error) {
        if (error.response?.status === 422) {
            const errors = error.response.data?.errors ?? {};
            editErrors.value = Object.values(errors).flat();
        } else {
            editErrors.value = ['Failed to update time entry.'];
        }
    } finally {
        isSavingEdit.value = false;
    }
};

watch(
    () => props.companyId,
    () => {
        currentPage.value = 1;
        fetchHistory();
    },
    { immediate: true }
);

watch(search, () => {
    currentPage.value = 1;
});

watch(sortBy, () => {
    currentPage.value = 1;
});

watch(totalPages, (value) => {
    if (currentPage.value > value) {
        currentPage.value = value;
    }
});
</script>

<template>
    <section class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="text-xs font-medium text-gray-500">Total Hours</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ summaryTotals.totalHours }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="text-xs font-medium text-gray-500">Employees</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ summaryTotals.uniqueEmployees }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="text-xs font-medium text-gray-500">Projects</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ summaryTotals.uniqueProjects }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="text-xs font-medium text-gray-500">Tasks</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ summaryTotals.uniqueTasks }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <p class="text-xs font-medium text-gray-500">Dates</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ summaryTotals.uniqueDates }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-900">History</h3>

            <div class="flex items-center gap-2">
                <select
                    v-model="sortBy"
                    class="w-48 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="date_desc">Sort by: Date (Newest First)</option>
                    <option value="date_asc">Sort by: Date (Oldest First)</option>
                    <option value="hours_desc">Sort by: Hours (Highest First)</option>
                    <option value="employee_asc">Sort by: Employee (A to Z)</option>
                    <option value="project_asc">Sort by: Project (A to Z)</option>
                </select>

                <input
                    v-model="search"
                    type="text"
                    placeholder="Search employee or project..."
                    class="w-64 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>

        <div
            v-if="isLoading"
            class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700"
        >
            Loading...
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Company</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Date</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Employee</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Project</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Task</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Hours</th>
                        <th class="px-3 py-3 text-left font-semibold text-gray-700">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-if="!filteredEntries.length">
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">
                            No entries found.
                        </td>
                    </tr>
                    <tr v-for="entry in paginatedEntries" :key="entry.id">
                        <td class="px-3 py-3 text-gray-700">{{ entry.company?.name ?? '-' }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ formatDate(entry.date) }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ entry.employee?.name ?? '-' }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ entry.project?.name ?? '-' }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ entry.task?.name ?? '-' }}</td>
                        <td class="px-3 py-3 text-gray-700">{{ entry.hours }}</td>
                        <td class="px-3 py-3">
                            <button
                                type="button"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                @click="openEdit(entry)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-600">{{ pageInfo }}</div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="currentPage === 1"
                    @click="goToPrevPage"
                >
                    Prev
                </button>
                <span class="text-sm text-gray-700">Page {{ currentPage }} / {{ totalPages }}</span>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="currentPage >= totalPages"
                    @click="goToNextPage"
                >
                    Next
                </button>
            </div>
        </div>

        <HistoryEditModal
            :show="Boolean(editEntryId)"
            :edit-form="editForm"
            :edit-meta="editMeta"
            :company-options="companyOptions"
            :edit-errors="editErrors"
            :is-saving="isSavingEdit"
            @close="closeEdit"
            @save="saveEdit"
            @company-change="onEditCompanyChange"
        />
    </section>
</template>

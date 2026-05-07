<script setup>
import History from '@/Pages/Partials/History.vue';
import NewEntries from '@/Pages/Partials/NewEntries.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LightSelect from '@/Components/LightSelect.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const companies = ref([]);
const selectedCompanyId = ref(null);
const activeTab = ref('new-entries');
const refreshKey = ref(0);
const isLoadingCompanies = ref(false);

const tabOptions = [
    { key: 'new-entries', label: 'New Entries' },
    { key: 'history', label: 'History' },
];

const activeTabComponent = computed(() =>
    activeTab.value === 'history' ? History : NewEntries
);

const activeTabProps = computed(() => {
    if (activeTab.value === 'history') {
        return {
            companyId: selectedCompanyId.value,
        };
    }

    return {
        companyId: selectedCompanyId.value,
        companies: companies.value,
    };
});

const selectedCompanyValue = computed({
    get: () => (selectedCompanyId.value === null ? 'all' : selectedCompanyId.value),
    set: (value) => {
        selectedCompanyId.value = value === 'all' ? null : Number(value);
        refreshKey.value += 1;
    },
});

const companyFilterOptions = computed(() => [
    { value: 'all', label: 'All Companies' },
    ...companies.value.map((company) => ({ value: company.id, label: company.name })),
]);

const loadCompanies = async () => {
    isLoadingCompanies.value = true;

    try {
        const { data } = await axios.get('/api/companies');
        companies.value = data.data ?? [];
    } finally {
        isLoadingCompanies.value = false;
    }
};

onMounted(() => {
    loadCompanies();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Time Entry Dashboard" />

        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Time Entry Dashboard</h2>

                <div class="flex items-center gap-3">
                    <label for="global-company-filter" class="text-sm font-medium text-gray-700">
                        Company Filter
                    </label>
                    <div id="global-company-filter" class="w-64">
                        <LightSelect
                        v-model="selectedCompanyValue"
                        :options="companyFilterOptions"
                        placeholder="All Companies"
                        :disabled="isLoadingCompanies"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex items-center gap-6">
                            <button
                                v-for="tab in tabOptions"
                                :key="tab.key"
                                type="button"
                                class="border-b-2 pb-3 text-sm font-medium transition-colors"
                                :class="
                                    activeTab === tab.key
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                "
                                @click="activeTab = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <component
                        :is="activeTabComponent"
                        :key="`${activeTab}-${refreshKey}`"
                        v-bind="activeTabProps"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

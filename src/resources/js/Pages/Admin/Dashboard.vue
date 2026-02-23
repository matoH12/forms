<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, onMounted, computed, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';

// Monthly report
const showReportModal = ref(false);
const reportMonth = ref(new Date().getMonth() + 1);
const reportYear = ref(new Date().getFullYear());
const generatingReport = ref(false);

const generateMonthlyReport = () => {
    generatingReport.value = true;
    window.location.href = `/admin/monthly-report?month=${reportMonth.value}&year=${reportYear.value}`;
    setTimeout(() => {
        generatingReport.value = false;
        showReportModal.value = false;
    }, 2000);
};

const months = [
    { value: 1, label: 'Január' },
    { value: 2, label: 'Február' },
    { value: 3, label: 'Marec' },
    { value: 4, label: 'Apríl' },
    { value: 5, label: 'Máj' },
    { value: 6, label: 'Jún' },
    { value: 7, label: 'Júl' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Október' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const years = computed(() => {
    const currentYear = new Date().getFullYear();
    return [currentYear, currentYear - 1, currentYear - 2];
});
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Line, Bar, Doughnut } from 'vue-chartjs';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    stats: Object,
    submissionsChart: Array,
    chartDays: {
        type: Number,
        default: 30,
    },
    submissionsByForm: Array,
    submissionsByCategory: Array,
    hourlyChart: Array,
    weeklyComparison: Array,
    recentSubmissions: Array,
    recentExecutions: Array,
    topUsers: Array,
    auth: Object,
});

// Chart days options
const chartDaysOptions = [
    { value: 7, label: '7 dní' },
    { value: 14, label: '14 dní' },
    { value: 30, label: '30 dní' },
    { value: 60, label: '60 dní' },
    { value: 90, label: '90 dní' },
    { value: 180, label: '180 dní' },
    { value: 365, label: '365 dní' },
];

const selectedDays = ref(props.chartDays);

const changeChartDays = (days) => {
    router.get('/admin', { days }, { preserveState: true, preserveScroll: true });
};

const { isDark } = useTheme();

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Chart color configuration based on theme
const chartColors = computed(() => ({
    text: isDark.value ? '#e5e7eb' : '#374151',
    grid: isDark.value ? '#374151' : '#e5e7eb',
    legendText: isDark.value ? '#d1d5db' : '#6b7280',
}));

// Chart configurations
const submissionsChartData = computed(() => ({
    labels: props.submissionsChart?.map(item => item.date) || [],
    datasets: [
        {
            label: 'Odpovede',
            data: props.submissionsChart?.map(item => item.count) || [],
            borderColor: '#3b82f6',
            backgroundColor: isDark.value ? 'rgba(59, 130, 246, 0.2)' : 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true,
        },
    ],
}));

const submissionsChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            mode: 'index',
            intersect: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
        x: {
            ticks: {
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
    },
}));

// Helper to get localized name from item
const getLocalizedName = (item) => {
    if (!item?.name) return '';
    if (typeof item.name === 'object' && item.name !== null) {
        return item.name.sk || item.name.en || '';
    }
    return item.name || '';
};

const byFormChartData = computed(() => ({
    labels: props.submissionsByForm?.map(item => {
        const name = getLocalizedName(item);
        return name.substring(0, 20) + (name.length > 20 ? '...' : '');
    }) || [],
    datasets: [
        {
            label: 'Odpovede',
            data: props.submissionsByForm?.map(item => item.count) || [],
            backgroundColor: [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
            ],
        },
    ],
}));

const byFormChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: {
            display: false,
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
        y: {
            ticks: {
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
    },
}));

const byCategoryChartData = computed(() => ({
    labels: props.submissionsByCategory?.map(item => item.name) || [],
    datasets: [
        {
            data: props.submissionsByCategory?.map(item => item.count) || [],
            backgroundColor: props.submissionsByCategory?.map(item => item.color || '#6b7280') || [],
        },
    ],
}));

const byCategoryChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right',
            labels: {
                padding: 15,
                usePointStyle: true,
                color: chartColors.value.legendText,
            },
        },
    },
}));

const hourlyChartData = computed(() => ({
    labels: props.hourlyChart?.map(item => item.hour) || [],
    datasets: [
        {
            label: 'Odpovede',
            data: props.hourlyChart?.map(item => item.count) || [],
            backgroundColor: '#10b981',
        },
    ],
}));

const hourlyChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
        x: {
            ticks: {
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
    },
}));

const weeklyComparisonData = computed(() => ({
    labels: props.weeklyComparison?.map(item => item.day) || [],
    datasets: [
        {
            label: 'Tento tyzden',
            data: props.weeklyComparison?.map(item => item.thisWeek) || [],
            backgroundColor: '#3b82f6',
        },
        {
            label: 'Minuly tyzden',
            data: props.weeklyComparison?.map(item => item.lastWeek) || [],
            backgroundColor: '#94a3b8',
        },
    ],
}));

const weeklyComparisonOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: {
                color: chartColors.value.legendText,
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
        x: {
            ticks: {
                color: chartColors.value.text,
            },
            grid: {
                color: chartColors.value.grid,
            },
        },
    },
}));

const getStatusClass = (status) => {
    const classes = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        waiting_approval: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
        running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        pending: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};

const getStatusLabel = (status) => {
    const labels = {
        completed: 'Dokoncene',
        waiting_approval: 'Caka na schvalenie',
        running: 'Bezi',
        failed: 'Zlyhalo',
        pending: 'Cakajuce',
    };
    return labels[status] || status;
};

// Helper to get form name (handles both string and object)
const getFormName = (form) => {
    if (!form) return '';
    const name = form.name;
    if (typeof name === 'object' && name !== null) {
        return name.sk || name.en || form.slug || '';
    }
    return name || form.slug || '';
};
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout :auth="auth">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
            <button
                @click="showReportModal = true"
                class="btn btn-primary flex items-center gap-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Mesačný report
            </button>
        </div>

        <!-- Main Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            <div class="card text-center">
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ stats.forms_count }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Formularov</p>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ stats.active_forms_count }} aktivnych</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ stats.submissions_count }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Odpovedi celkom</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ stats.submissions_today }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Dnes</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ stats.submissions_this_week }} tento tyzden</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.submissions_pending }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Cakajucich</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ stats.workflows_count }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Workflow</p>
                <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">{{ stats.pending_approvals }} na schvalenie</p>
            </div>
            <div class="card text-center">
                <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.users_count }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Pouzivatelov</p>
            </div>
        </div>

        <!-- Submission Status Summary -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="card bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ stats.submissions_pending }}</p>
                        <p class="text-yellow-600 dark:text-yellow-500 text-sm">Cakajuce na schvalenie</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="card bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ stats.submissions_approved }}</p>
                        <p class="text-green-600 dark:text-green-500 text-sm">Schvalenych</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="card bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ stats.submissions_rejected }}</p>
                        <p class="text-red-600 dark:text-red-500 text-sm">Zamietnutych</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <!-- Submissions over time -->
            <div class="lg:col-span-2 card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Odpovede za poslednych {{ selectedDays }} dní
                    </h2>
                    <select
                        v-model="selectedDays"
                        @change="changeChartDays(selectedDays)"
                        class="input px-3 py-1.5 text-sm w-auto"
                    >
                        <option v-for="opt in chartDaysOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="h-64">
                    <Line :data="submissionsChartData" :options="submissionsChartOptions" />
                </div>
            </div>

            <!-- By Category -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Podla kategorie</h2>
                <div class="h-64">
                    <Doughnut v-if="submissionsByCategory?.length" :data="byCategoryChartData" :options="byCategoryChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                        Ziadne data
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <!-- By Form -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Top 10 formularov</h2>
                <div class="h-64">
                    <Bar v-if="submissionsByForm?.length" :data="byFormChartData" :options="byFormChartOptions" />
                    <div v-else class="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                        Ziadne data
                    </div>
                </div>
            </div>

            <!-- Weekly Comparison -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Porovnanie: tento vs minuly tyzden</h2>
                <div class="h-64">
                    <Bar :data="weeklyComparisonData" :options="weeklyComparisonOptions" />
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <!-- Hourly Activity -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Aktivita dnes podla hodin</h2>
                <div class="h-48">
                    <Bar :data="hourlyChartData" :options="hourlyChartOptions" />
                </div>
            </div>

            <!-- Top Users -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Najaktivnejsi pouzivatelia</h2>
                <div v-if="topUsers?.length" class="space-y-3">
                    <div v-for="(user, index) in topUsers" :key="user.email" class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium"
                                :class="{
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': index === 0,
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': index === 1,
                                    'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300': index === 2,
                                    'bg-blue-50 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300': index > 2,
                                }"
                            >
                                {{ index + 1 }}
                            </span>
                            <div>
                                <p class="font-medium text-sm text-gray-900 dark:text-gray-100">
                                    {{ user.first_name && user.last_name
                                        ? `${user.first_name} ${user.last_name}`
                                        : user.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <span v-if="user.login" class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1">{{ user.login }}</span>
                                    {{ user.email }}
                                </p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ user.count }} odpovedi</span>
                    </div>
                </div>
                <p v-else class="text-gray-500 dark:text-gray-400 text-center py-4">Ziadni pouzivatelia</p>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Recent Submissions -->
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Posledne odpovede</h2>
                    <Link href="/admin/submissions" class="text-blue-600 dark:text-blue-400 text-sm hover:underline">
                        Zobrazit vsetky
                    </Link>
                </div>
                <div v-if="recentSubmissions?.length" class="space-y-3">
                    <div v-for="submission in recentSubmissions" :key="submission.id" class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate text-gray-900 dark:text-gray-100">{{ getFormName(submission.form) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ submission.user?.name || 'Anonymny' }} &middot; {{ formatDate(submission.created_at) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="getStatusClass(submission.status || 'pending')"
                            >
                                {{ submission.status === 'approved' ? 'Schvalena' : submission.status === 'rejected' ? 'Zamietnuta' : 'Nova' }}
                            </span>
                            <Link :href="`/admin/submissions/${submission.id}`" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                Detail
                            </Link>
                        </div>
                    </div>
                </div>
                <p v-else class="text-gray-500 dark:text-gray-400 text-center py-4">Ziadne odpovede</p>
            </div>

            <!-- Recent Workflow Executions -->
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Posledne workflow</h2>
                    <Link href="/admin/workflows" class="text-blue-600 dark:text-blue-400 text-sm hover:underline">
                        Zobrazit vsetky
                    </Link>
                </div>
                <div v-if="recentExecutions?.length" class="space-y-3">
                    <div v-for="execution in recentExecutions" :key="execution.id" class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate text-gray-900 dark:text-gray-100">{{ execution.workflow?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(execution.created_at) }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full ml-4"
                            :class="getStatusClass(execution.status)"
                        >
                            {{ getStatusLabel(execution.status) }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-gray-500 dark:text-gray-400 text-center py-4">Ziadne workflow</p>
            </div>
        </div>

        <!-- Monthly Report Modal -->
        <Teleport to="body">
            <div v-if="showReportModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showReportModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Generovat mesacny report
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Vyberte mesiac a rok pre ktory chcete vygenerovat PDF report s aktivitou schvalovania.
                        </p>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mesiac</label>
                                <select v-model="reportMonth" class="input w-full">
                                    <option v-for="m in months" :key="m.value" :value="m.value">
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rok</label>
                                <select v-model="reportYear" class="input w-full">
                                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                @click="showReportModal = false"
                                class="btn btn-secondary"
                                :disabled="generatingReport"
                            >
                                Zrusit
                            </button>
                            <button
                                @click="generateMonthlyReport"
                                class="btn btn-primary flex items-center gap-2"
                                :disabled="generatingReport"
                            >
                                <svg v-if="generatingReport" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ generatingReport ? 'Generujem...' : 'Stiahnut PDF' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

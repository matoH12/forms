<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get chart days from request (default 30, max 365)
        $chartDays = min(max((int) $request->input('days', 30), 7), 365);

        // Get IDs of forms the user has explicit permission for (for submissions stats)
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        // Dashboard stats - forms count includes created forms, submissions only explicit permissions
        $formsCount = $user->getVisibleFormsQuery(true)->count();
        $activeFormsCount = $user->getVisibleFormsQuery(true)->where('is_active', true)->count();
        $workflowsCount = Workflow::count();
        $usersCount = User::count();

        $submissionStats = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week,
                SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as this_month,
                SUM(CASE WHEN status IS NULL OR status = 'pending' OR status = 'submitted' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        $pendingApprovals = WorkflowExecution::where('status', 'waiting_approval')->count();

        $stats = [
            'forms_count' => $formsCount,
            'active_forms_count' => $activeFormsCount,
            'submissions_count' => (int) ($submissionStats->total ?? 0),
            'submissions_today' => (int) ($submissionStats->today ?? 0),
            'submissions_this_week' => (int) ($submissionStats->this_week ?? 0),
            'submissions_this_month' => (int) ($submissionStats->this_month ?? 0),
            'submissions_pending' => (int) ($submissionStats->pending ?? 0),
            'submissions_approved' => (int) ($submissionStats->approved ?? 0),
            'submissions_rejected' => (int) ($submissionStats->rejected ?? 0),
            'workflows_count' => $workflowsCount,
            'pending_approvals' => $pendingApprovals,
            'users_count' => $usersCount,
        ];

        // Submissions per day (configurable days) - filtered by allowed forms
        $submissionsPerDay = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($chartDays))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill in missing dates
        $submissionsChart = [];
        for ($i = $chartDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $submissionsChart[] = [
                'date' => now()->subDays($i)->format('d.m'),
                'count' => (int) ($submissionsPerDay[$date]->count ?? 0),
            ];
        }

        // Submissions by form (top 10) - filtered by allowed forms
        $submissionsByForm = DB::table('form_submissions')
            ->join('forms', 'form_submissions.form_id', '=', 'forms.id')
            ->whereIn('form_submissions.form_id', $allowedFormIds)
            ->selectRaw('COALESCE(JSON_UNQUOTE(JSON_EXTRACT(forms.name, "$.sk")), JSON_UNQUOTE(JSON_EXTRACT(forms.name, "$.en")), forms.name) as name, forms.id, COUNT(*) as count')
            ->groupBy('forms.id', 'forms.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Submissions by category - filtered by allowed forms
        $submissionsByCategory = DB::table('form_submissions')
            ->join('forms', 'form_submissions.form_id', '=', 'forms.id')
            ->leftJoin('form_categories', 'forms.category_id', '=', 'form_categories.id')
            ->whereIn('form_submissions.form_id', $allowedFormIds)
            ->selectRaw('COALESCE(form_categories.name, "Bez kategórie") as name, form_categories.color, COUNT(*) as count')
            ->groupBy('form_categories.id', 'form_categories.name', 'form_categories.color')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                // Handle multilingual category name (JSON)
                $name = $item->name;
                if (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    if (is_array($decoded)) {
                        $name = $decoded['sk'] ?? $decoded['en'] ?? $item->name;
                    }
                }
                $item->name = $name;
                return $item;
            });

        // Submissions per hour (today) - filtered by allowed forms
        $submissionsPerHour = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->whereDate('created_at', today())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->toArray();

        // Fill in hours
        $hourlyChart = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyChart[] = [
                'hour' => sprintf('%02d:00', $h),
                'count' => (int) ($submissionsPerHour[$h]->count ?? 0),
            ];
        }

        // Weekly comparison (this week vs last week per day) - filtered by allowed forms
        $thisWeek = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw('DAYOFWEEK(created_at) as day, COUNT(*) as count')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->groupBy('day')
            ->get()
            ->keyBy('day')
            ->toArray();

        $lastWeek = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw('DAYOFWEEK(created_at) as day, COUNT(*) as count')
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->groupBy('day')
            ->get()
            ->keyBy('day')
            ->toArray();

        $days = ['Po', 'Ut', 'St', 'Št', 'Pi', 'So', 'Ne'];
        $weeklyComparison = [];
        for ($d = 2; $d <= 8; $d++) {
            $dayIndex = $d > 7 ? 1 : $d;
            $weeklyComparison[] = [
                'day' => $days[$d - 2],
                'thisWeek' => (int) ($thisWeek[$dayIndex]->count ?? 0),
                'lastWeek' => (int) ($lastWeek[$dayIndex]->count ?? 0),
            ];
        }

        // Recent submissions - filtered by allowed forms
        $recentSubmissions = FormSubmission::with(['form:id,name', 'user:id,name'])
            ->whereIn('form_id', $allowedFormIds)
            ->latest()
            ->take(10)
            ->get();

        // Recent workflow executions - filtered by allowed forms
        $recentExecutions = WorkflowExecution::with(['workflow:id,name', 'submission:id,form_id'])
            ->whereHas('submission', function ($query) use ($allowedFormIds) {
                $query->whereIn('form_id', $allowedFormIds);
            })
            ->latest()
            ->take(10)
            ->get();

        // Top users by submissions - filtered by allowed forms
        $topUsers = DB::table('form_submissions')
            ->join('users', 'form_submissions.user_id', '=', 'users.id')
            ->whereIn('form_submissions.form_id', $allowedFormIds)
            ->selectRaw('users.name, users.first_name, users.last_name, users.email, users.login, COUNT(*) as count')
            ->whereNotNull('form_submissions.user_id')
            ->groupBy('users.id', 'users.name', 'users.first_name', 'users.last_name', 'users.email', 'users.login')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'submissionsChart' => $submissionsChart,
            'chartDays' => $chartDays,
            'submissionsByForm' => $submissionsByForm,
            'submissionsByCategory' => $submissionsByCategory,
            'hourlyChart' => $hourlyChart,
            'weeklyComparison' => $weeklyComparison,
            'recentSubmissions' => $recentSubmissions,
            'recentExecutions' => $recentExecutions,
            'topUsers' => $topUsers,
        ]);
    }

    /**
     * Generate monthly activity report PDF
     */
    public function monthlyReport(Request $request)
    {
        $user = auth()->user();

        // Get month/year from request or use current month
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        // Get allowed form IDs
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        // Submissions by form for the month
        $submissionsByForm = DB::table('form_submissions')
            ->join('forms', 'form_submissions.form_id', '=', 'forms.id')
            ->whereIn('form_submissions.form_id', $allowedFormIds)
            ->whereBetween('form_submissions.created_at', [$startDate, $endDate])
            ->selectRaw('
                forms.id,
                forms.name as form_name,
                COUNT(*) as total,
                SUM(CASE WHEN form_submissions.status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN form_submissions.status = "rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN form_submissions.status IS NULL OR form_submissions.status = "submitted" OR form_submissions.status = "pending" THEN 1 ELSE 0 END) as pending
            ')
            ->groupBy('forms.id', 'forms.name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                // Handle multilingual form name
                $name = $item->form_name;
                if (is_string($name) && str_starts_with($name, '{')) {
                    $decoded = json_decode($name, true);
                    if (is_array($decoded)) {
                        $name = $decoded['sk'] ?? $decoded['en'] ?? $item->form_name;
                    }
                }
                $item->form_name = $name;
                return $item;
            });

        // Approvals/Rejections activity - who approved/rejected what
        $approvalActivity = FormSubmission::with(['form:id,name', 'reviewer:id,name,first_name,last_name,email', 'user:id,name,email'])
            ->whereIn('form_id', $allowedFormIds)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereBetween('reviewed_at', [$startDate, $endDate])
            ->orderBy('reviewed_at', 'desc')
            ->get()
            ->map(function ($submission) {
                // Get localized form name
                $formName = $submission->form?->name;
                if (is_array($formName)) {
                    $formName = $formName['sk'] ?? $formName['en'] ?? 'N/A';
                }
                $submission->localized_form_name = $formName;
                return $submission;
            });

        // Reviewers summary - who approved/rejected how many
        $reviewersSummary = DB::table('form_submissions')
            ->join('users', 'form_submissions.reviewed_by', '=', 'users.id')
            ->whereIn('form_submissions.form_id', $allowedFormIds)
            ->whereIn('form_submissions.status', ['approved', 'rejected'])
            ->whereBetween('form_submissions.reviewed_at', [$startDate, $endDate])
            ->selectRaw('
                users.id,
                users.name,
                users.first_name,
                users.last_name,
                users.email,
                SUM(CASE WHEN form_submissions.status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN form_submissions.status = "rejected" THEN 1 ELSE 0 END) as rejected_count,
                COUNT(*) as total_reviewed
            ')
            ->groupBy('users.id', 'users.name', 'users.first_name', 'users.last_name', 'users.email')
            ->orderByDesc('total_reviewed')
            ->get();

        // Total stats for the month
        $monthlyStats = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status IS NULL OR status = "submitted" OR status = "pending" THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        $data = [
            'month' => $startDate->translatedFormat('F Y'),
            'generatedAt' => now()->format('d.m.Y H:i'),
            'generatedBy' => $user->name ?? $user->email,
            'monthlyStats' => $monthlyStats,
            'submissionsByForm' => $submissionsByForm,
            'reviewersSummary' => $reviewersSummary,
            'approvalActivity' => $approvalActivity,
        ];

        $pdf = Pdf::loadView('pdf.monthly-report', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'mesacny-report-' . $startDate->format('Y-m') . '.pdf';

        return $pdf->download($filename);
    }
}

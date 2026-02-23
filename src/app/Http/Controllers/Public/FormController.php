<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmissionConfirmation;
use App\Mail\NewSubmissionNotification;
use App\Models\Announcement;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine
    ) {}

    public function index(Request $request)
    {
        $search = strip_tags($request->input('search', '')) ?: null;
        $categorySlug = strip_tags($request->input('category', '')) ?: null;

        // Get categories for filter
        $categories = Cache::remember('form_categories', 3600, function () {
            return FormCategory::orderBy('order')
                ->select(['id', 'name', 'slug', 'color', 'icon', 'description'])
                ->get();
        });

        // Get featured forms for quick actions (only when not searching)
        $featuredForms = [];
        if (!$search && !$categorySlug) {
            $featuredQuery = Form::where('is_active', true)
                ->where('is_featured', true)
                ->with('category:id,name,slug,color');

            if (!auth()->check()) {
                $featuredQuery->where('is_public', true);
            }

            $featuredForms = $featuredQuery
                ->select(['id', 'name', 'slug', 'description', 'is_public', 'category_id', 'settings', 'allowed_email_domains'])
                ->orderBy('featured_order')
                ->limit(8)
                ->get()
                ->filter(fn(Form $form) => $form->isVisibleForEmail(auth()->user()?->email))
                ->values()
                ->take(4);
        }

        if ($search) {
            // Search forms
            $forms = $this->searchForms($search, $categorySlug);
        } else {
            // Show forms based on auth status
            $query = Form::where('is_active', true)
                ->with('category:id,name,slug,color');

            if (!auth()->check()) {
                $query->where('is_public', true);
            }

            if ($categorySlug) {
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug);
                });
            }

            $forms = $query->select(['id', 'name', 'slug', 'description', 'is_public', 'category_id', 'tags', 'allowed_email_domains'])
                ->orderBy('name')
                ->get();
        }

        // Filter forms by email domain restriction
        $userEmail = auth()->user()?->email;
        $forms = $forms->filter(fn(Form $form) => $form->isVisibleForEmail($userEmail))
            ->values();

        // Get active announcements
        $announcements = Announcement::active()
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        return Inertia::render('Public/Home', [
            'forms' => $forms,
            'featuredForms' => $featuredForms,
            'announcements' => $announcements,
            'search' => $search,
            'categories' => $categories,
            'selectedCategory' => $categorySlug,
            'supportEmail' => Setting::get('branding_support_email', ''),
        ]);
    }

    /**
     * Escape LIKE wildcards to prevent DoS via expensive queries
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    /**
     * Intelligent search - searches by keywords in name, description, tags, keywords, and category
     * Supports natural language queries
     */
    private function searchForms(string $query, ?string $categorySlug = null): \Illuminate\Support\Collection
    {
        // SECURITY: Limit search query length to prevent ReDoS and memory exhaustion
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }

        // Normalize query - remove extra spaces, convert to lowercase
        $normalizedQuery = mb_strtolower(trim(preg_replace('/\s+/', ' ', $query)));

        // Extract keywords (words longer than 2 characters) before escaping
        $keywords = array_filter(
            explode(' ', $normalizedQuery),
            fn($word) => mb_strlen($word) > 2
        );

        if (empty($keywords)) {
            $keywords = [$normalizedQuery];
        }

        // Escape LIKE wildcards to prevent DoS attacks (e.g., "%%%%%" causing slow queries)
        $normalizedQuery = $this->escapeLikeWildcards($normalizedQuery);
        $keywords = array_map(fn($k) => $this->escapeLikeWildcards($k), $keywords);

        // Build query with relevance scoring
        $formsQuery = Form::where('is_active', true)
            ->with('category:id,name,slug,color')
            ->where(function ($q) {
                if (!auth()->check()) {
                    $q->where('is_public', true);
                }
            });

        // Filter by category if provided
        if ($categorySlug) {
            $formsQuery->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        $formsQuery->where(function ($q) use ($keywords, $normalizedQuery) {
            // Exact match in name (highest priority)
            $q->where('name', 'LIKE', "%{$normalizedQuery}%")
                // Match in description
                ->orWhere('description', 'LIKE', "%{$normalizedQuery}%")
                // Match in keywords field
                ->orWhere('keywords', 'LIKE', "%{$normalizedQuery}%")
                // Match in tags (JSON column)
                ->orWhereRaw("LOWER(tags) LIKE ?", ["%{$normalizedQuery}%"])
                // Match in category name
                ->orWhereHas('category', function ($subQ) use ($normalizedQuery) {
                    $subQ->whereRaw('LOWER(name) LIKE ?', ["%{$normalizedQuery}%"]);
                })
                // Or match all keywords
                ->orWhere(function ($subQ) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $subQ->where(function ($innerQ) use ($keyword) {
                            $innerQ->where('name', 'LIKE', "%{$keyword}%")
                                ->orWhere('description', 'LIKE', "%{$keyword}%")
                                ->orWhere('keywords', 'LIKE', "%{$keyword}%")
                                ->orWhereRaw("LOWER(tags) LIKE ?", ["%{$keyword}%"]);
                        });
                    }
                })
                // Or match any keyword
                ->orWhere(function ($subQ) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $subQ->orWhere('name', 'LIKE', "%{$keyword}%")
                            ->orWhere('description', 'LIKE', "%{$keyword}%")
                            ->orWhere('keywords', 'LIKE', "%{$keyword}%")
                            ->orWhereRaw("LOWER(tags) LIKE ?", ["%{$keyword}%"]);
                    }
                });
        })
        ->select(['id', 'name', 'slug', 'description', 'is_public', 'category_id', 'tags', 'keywords', 'allowed_email_domains']);

        $forms = $formsQuery->get();

        // Sort by relevance
        return $forms->sortByDesc(function ($form) use ($keywords, $normalizedQuery) {
            $score = 0;

            // Handle multilingual name and description
            $name = is_array($form->name) ? implode(' ', array_filter($form->name)) : ($form->name ?? '');
            $desc = is_array($form->description) ? implode(' ', array_filter($form->description)) : ($form->description ?? '');

            $nameLower = mb_strtolower($name);
            $descLower = mb_strtolower($desc);
            $keywordsLower = mb_strtolower($form->keywords ?? '');
            $tagsString = mb_strtolower(implode(' ', $form->tags ?? []));

            // Handle multilingual category name
            $catName = $form->category?->name;
            $categoryName = mb_strtolower(
                is_array($catName) ? implode(' ', array_filter($catName)) : ($catName ?? '')
            );

            // Exact match in name = highest score
            if (str_contains($nameLower, $normalizedQuery)) {
                $score += 100;
            }

            // Exact match in category name
            if (str_contains($categoryName, $normalizedQuery)) {
                $score += 50;
            }

            // Each keyword match
            foreach ($keywords as $keyword) {
                if (str_contains($nameLower, $keyword)) {
                    $score += 10;
                }
                if (str_contains($descLower, $keyword)) {
                    $score += 5;
                }
                if (str_contains($keywordsLower, $keyword)) {
                    $score += 8;
                }
                if (str_contains($tagsString, $keyword)) {
                    $score += 7;
                }
                if (str_contains($categoryName, $keyword)) {
                    $score += 6;
                }
            }

            return $score;
        })->values();
    }

    public function show(string $slug)
    {
        // First check if form exists at all
        $form = Form::where('slug', $slug)->first();

        if (!$form) {
            return Inertia::render('Public/FormNotFound', [
                'reason' => 'not_found',
                'message' => 'Formulár nebol nájdený',
            ]);
        }

        if (!$form->is_active) {
            return Inertia::render('Public/FormNotFound', [
                'reason' => 'inactive',
                'message' => 'Formulár nie je aktívny',
            ]);
        }

        if (!$form->is_public && !auth()->check()) {
            return redirect()->route('auth.login');
        }

        // Check email domain restriction
        if (!$form->isVisibleForEmail(auth()->user()?->email)) {
            if (!auth()->check()) {
                return redirect()->route('auth.login');
            }
            return Inertia::render('Public/FormNotFound', [
                'reason' => 'restricted',
                'message' => 'Tento formulár nie je dostupný pre váš typ účtu',
            ]);
        }

        // Check for duplicate submission
        $alreadySubmitted = false;
        $existingSubmission = null;
        if ($form->prevent_duplicates && auth()->check()) {
            $existingSubmission = FormSubmission::where('form_id', $form->id)
                ->where('user_id', auth()->id())
                ->first();
            $alreadySubmitted = $existingSubmission !== null;
        }

        return Inertia::render('Public/Form', [
            'form' => $form,
            'alreadySubmitted' => $alreadySubmitted,
            'existingSubmission' => $existingSubmission,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$form->is_public && !auth()->check()) {
            abort(403);
        }

        // Check email domain restriction
        if (!$form->isVisibleForEmail(auth()->user()?->email)) {
            abort(403, 'Tento formulár nie je dostupný pre váš typ účtu.');
        }

        // Anti-spam: Check honeypot (should be empty)
        if ($request->filled('_honeypot')) {
            // Bot detected - silently reject but pretend success
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Formulár bol úspešne odoslaný',
                ]);
            }
            return redirect()->back()->with('success', 'Formulár bol úspešne odoslaný');
        }

        // Anti-spam: Check timestamp (form must be filled in at least 3 seconds)
        $timestamp = $request->input('_timestamp');
        if ($timestamp) {
            $loadedAt = (int) $timestamp;
            $submittedAt = round(microtime(true) * 1000);
            $timeDiff = ($submittedAt - $loadedAt) / 1000; // seconds

            if ($timeDiff < 3) {
                // Too fast - likely a bot
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Formulár bol odoslaný príliš rýchlo. Skúste to znova.',
                    ], 422);
                }
                return redirect()->back()->withErrors([
                    'spam' => 'Formulár bol odoslaný príliš rýchlo. Skúste to znova.',
                ]);
            }
        }

        // Check for duplicate submission
        if ($form->prevent_duplicates && auth()->check()) {
            $existingSubmission = FormSubmission::where('form_id', $form->id)
                ->where('user_id', auth()->id())
                ->exists();

            if ($existingSubmission) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Tento formulár ste už vyplnili.',
                    ], 422);
                }
                return redirect()->back()->withErrors([
                    'duplicate' => 'Tento formulár ste už vyplnili.',
                ]);
            }
        }

        $validationRules = $this->buildValidationRules($form, $request->all());
        $validated = $request->validate($validationRules);

        // Remove anti-spam fields from validated data
        unset($validated['_honeypot'], $validated['_timestamp']);

        // Process file uploads
        $validated = $this->processFileUploads($request, $form, $validated);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => auth()->id(),
            'user_login' => auth()->user()?->login,
            'data' => $validated,
            'status' => 'submitted',
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->userAgent(),
        ]);

        // Clear caches
        Cache::forget('submission_counts');
        Cache::forget('dashboard_stats');
        Cache::forget('dashboard_recent_submissions');

        // Audit log
        AuditService::formSubmitted($submission);

        // Trigger workflows
        $this->workflowEngine->triggerForSubmission($submission);

        // Send confirmation email if enabled
        $this->sendConfirmationEmail($form, $submission);

        // Send notifications to admins/approvers about new submission
        $this->notifyAboutNewSubmission($submission);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Formulár bol úspešne odoslaný',
                'submission_id' => $submission->id,
            ]);
        }

        return redirect()->back()->with('success', 'Formulár bol úspešne odoslaný');
    }

    public function mySubmissions(Request $request)
    {
        $userId = auth()->id();

        $submissions = FormSubmission::where('user_id', $userId)
            ->with('form:id,name,slug')
            ->latest()
            ->paginate(20);

        // Get submission stats
        $stats = [
            'pending' => FormSubmission::where('user_id', $userId)
                ->whereIn('status', ['pending', 'submitted', null])
                ->count(),
            'approved' => FormSubmission::where('user_id', $userId)
                ->where('status', 'approved')
                ->count(),
            'rejected' => FormSubmission::where('user_id', $userId)
                ->where('status', 'rejected')
                ->count(),
            'total' => FormSubmission::where('user_id', $userId)->count(),
        ];

        return Inertia::render('Public/MySubmissions', [
            'submissions' => $submissions,
            'stats' => $stats,
        ]);
    }

    public function mySubmissionShow(FormSubmission $submission)
    {
        // Ensure the submission belongs to the authenticated user
        if ($submission->user_id !== auth()->id()) {
            abort(403);
        }

        $submission->load([
            'form:id,name,slug,schema',
            'reviewer:id,name',
        ]);

        return Inertia::render('Public/MySubmissionDetail', [
            'submission' => $submission,
        ]);
    }

    /**
     * Notify approver+ users about new submission
     */
    protected function notifyAboutNewSubmission(FormSubmission $submission): void
    {
        $template = EmailTemplate::getBySystemType(EmailTemplate::TYPE_NEW_SUBMISSION_ADMIN);
        if (!$template) {
            return;
        }

        // Global subscribers (approver+ with notify_new_submissions setting - ALL forms)
        $globalSubscribers = User::where('role', '!=', User::ROLE_USER)
            ->whereRaw("JSON_EXTRACT(settings, '$.notify_new_submissions') = true")
            ->get();

        // Per-form subscribers who have enabled notifications for this specific form
        // (users assigned to this form by admin + user enabled notify_enabled for this form)
        $formSubscribers = $submission->form->notificationSubscribers()
            ->wherePivot('notify_enabled', true)
            ->get();

        // Merge and deduplicate (each user receives max 1 email)
        $allSubscribers = $globalSubscribers->merge($formSubscribers)->unique('id');

        foreach ($allSubscribers as $user) {
            // Only notify approver+ users
            if (!$user->hasMinRole(User::ROLE_APPROVER)) {
                continue;
            }

            try {
                Mail::to($user->email)->queue(new NewSubmissionNotification($submission, $template));
            } catch (\Exception $e) {
                \Log::error('Failed to send new submission notification', [
                    'submission_id' => $submission->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send confirmation email to user after submission
     */
    private function sendConfirmationEmail(Form $form, FormSubmission $submission): void
    {
        // Check if confirmation email is enabled for this form
        if (!$form->send_confirmation_email) {
            return;
        }

        // Get user email
        $userEmail = $submission->user?->email;
        if (!$userEmail) {
            return;
        }

        // Get email template (form-specific or default)
        $template = null;
        if ($form->email_template_id) {
            $template = EmailTemplate::where('id', $form->email_template_id)
                ->where('is_active', true)
                ->first();
        }

        if (!$template) {
            $template = EmailTemplate::getDefault();
        }

        // If no template found, skip sending
        if (!$template) {
            return;
        }

        // Send email
        try {
            Mail::to($userEmail)->send(new FormSubmissionConfirmation($submission, $template));
        } catch (\Exception $e) {
            // Log error but don't fail the submission
            \Log::error('Failed to send confirmation email', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process and store uploaded files
     */
    private function processFileUploads(Request $request, Form $form, array $validated): array
    {
        $fields = $form->schema['fields'] ?? [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'file') {
                continue;
            }

            $fieldName = $field['name'];

            // Check if we have files for this field
            if (!$request->hasFile($fieldName)) {
                continue;
            }

            $files = $request->file($fieldName);
            $isMultiple = $field['multiple'] ?? false;

            // Ensure files is an array
            if (!is_array($files)) {
                $files = [$files];
            }

            $storedFiles = [];
            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                // Validate file size for multiple uploads
                $maxSizeKB = ($field['maxSize'] ?? 10) * 1024;
                if ($file->getSize() > $maxSizeKB * 1024) {
                    continue;
                }

                // Generate unique filename with secure extension
                $originalName = $file->getClientOriginalName();

                // Get extension from actual MIME type (not client-provided extension!)
                // This prevents attacks like uploading evil.php with image/jpeg MIME
                $mimeType = $file->getMimeType();
                $extension = $this->getSecureExtensionFromMime($mimeType);

                if ($extension === null) {
                    // Unknown or dangerous MIME type, skip this file
                    continue;
                }

                // Use cryptographically secure random name (not predictable uniqid)
                $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;

                // Store file
                $path = $file->storeAs(
                    'form-submissions/' . $form->id,
                    $uniqueName,
                    'public'
                );

                $storedFiles[] = [
                    'path' => $path,
                    'original_name' => $originalName,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'url' => Storage::url($path),
                ];
            }

            // Store file info in validated data
            if (!empty($storedFiles)) {
                $validated[$fieldName] = $isMultiple ? $storedFiles : $storedFiles[0];
            } else {
                $validated[$fieldName] = null;
            }
        }

        return $validated;
    }

    /**
     * Get safe file extension based on actual MIME type (not client-provided)
     * Returns null for unknown or dangerous MIME types
     */
    private function getSecureExtensionFromMime(?string $mimeType): ?string
    {
        if (!$mimeType) {
            return null;
        }

        // Whitelist of allowed MIME types mapped to safe extensions
        // Only include file types that are safe to serve
        $allowedMimes = [
            // Images (no SVG - can contain JS)
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',

            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
            'application/vnd.oasis.opendocument.presentation' => 'odp',
            'application/rtf' => 'rtf',

            // Text
            'text/plain' => 'txt',
            'text/csv' => 'csv',

            // Archives
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz',

            // Audio
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',

            // Video
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/webm' => 'webm',
        ];

        return $allowedMimes[$mimeType] ?? null;
    }

    /**
     * Get the real client IP from proxy headers.
     * Traffic flows: Client -> Traefik -> WAF -> nginx -> PHP
     * Traefik sets X-Forwarded-For with the real client IP as the first entry.
     */
    private function getClientIp(Request $request): string
    {
        // X-Forwarded-For: first IP is the real client (set by Traefik)
        $xff = $request->header('X-Forwarded-For');
        if ($xff) {
            $firstIp = trim(explode(',', $xff)[0]);
            if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                return $firstIp;
            }
        }

        // X-Real-IP: set by Traefik to real client IP
        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        return $request->ip();
    }

    /**
     * Check if a field should be visible based on its conditions and submitted data.
     * Mirrors the frontend isFieldVisible() logic in Form.vue.
     */
    private function isFieldVisible(array $field, array $data): bool
    {
        if (empty($field['conditions'])) {
            return true;
        }

        // All conditions must be met (AND logic)
        foreach ($field['conditions'] as $condition) {
            $fieldValue = $data[$condition['field']] ?? null;
            $conditionValue = $condition['value'] ?? null;

            $met = match ($condition['operator'] ?? 'equals') {
                'equals' => $fieldValue == $conditionValue,
                'not_equals' => $fieldValue != $conditionValue,
                'contains' => str_contains(
                    strtolower((string) ($fieldValue ?? '')),
                    strtolower((string) $conditionValue)
                ),
                'is_empty' => !$fieldValue || $fieldValue === '' || $fieldValue === false,
                'not_empty' => $fieldValue && $fieldValue !== '' && $fieldValue !== false,
                default => true,
            };

            if (!$met) {
                return false;
            }
        }

        return true;
    }

    private function buildValidationRules(Form $form, array $submittedData = []): array
    {
        $rules = [];
        $fields = $form->schema['fields'] ?? [];

        foreach ($fields as $field) {
            $fieldRules = [];

            $isRequired = $field['required'] ?? false;

            // If field has conditions and they are not met, skip required validation
            if ($isRequired && !empty($field['conditions']) && !empty($submittedData)) {
                if (!$this->isFieldVisible($field, $submittedData)) {
                    $isRequired = false;
                }
            }

            if ($isRequired) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type'] ?? 'text') {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'file':
                    // Check if multiple files allowed
                    if ($field['multiple'] ?? false) {
                        $fieldRules[] = 'array';
                        // We'll validate each file in the array separately
                    } else {
                        $fieldRules[] = 'file';
                    }

                    // Max size in KB (field stores MB)
                    $maxSizeKB = (($field['maxSize'] ?? 10) * 1024);
                    if (!($field['multiple'] ?? false)) {
                        $fieldRules[] = 'max:' . $maxSizeKB;
                    }

                    // Accepted file types
                    if (!empty($field['accept'])) {
                        $accept = $field['accept'];
                        $mimes = [];

                        // Dangerous file types that are NEVER allowed (security blacklist)
                        $dangerousTypes = [
                            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
                            'js', 'mjs', 'jsx', 'ts', 'tsx',
                            'html', 'htm', 'xhtml', 'shtml',
                            'svg', 'svgz',  // Can contain JavaScript
                            'exe', 'dll', 'so', 'dylib',
                            'bat', 'cmd', 'sh', 'bash', 'zsh', 'ps1', 'psm1',
                            'jar', 'war', 'ear',
                            'py', 'pyc', 'pyo', 'pyw',
                            'rb', 'pl', 'cgi',
                            'asp', 'aspx', 'jsp', 'jspx',
                            'htaccess', 'htpasswd',
                        ];

                        // Convert accept attribute to Laravel mimes
                        // Note: SVG excluded due to XSS risk (can contain JavaScript)
                        if ($accept === 'image/*') {
                            $mimes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
                        } elseif (str_contains($accept, ',')) {
                            // Multiple extensions: .pdf,.doc,.docx
                            $extensions = array_map(fn($ext) => ltrim(trim($ext), '.'), explode(',', $accept));
                            $mimes = $extensions;
                        } elseif (str_starts_with($accept, '.')) {
                            // Single extension: .pdf
                            $mimes = [ltrim($accept, '.')];
                        }

                        // Filter out dangerous file types from mimes
                        $mimes = array_filter($mimes, fn($mime) => !in_array(strtolower($mime), $dangerousTypes));

                        if (!empty($mimes) && !($field['multiple'] ?? false)) {
                            $fieldRules[] = 'mimes:' . implode(',', $mimes);
                        }
                    }
                    break;
                case 'checkbox':
                    // For required checkbox, use 'accepted' to ensure it's checked
                    if ($isRequired) {
                        // Remove 'required' and add 'accepted' instead
                        $fieldRules = array_filter($fieldRules, fn($rule) => $rule !== 'required');
                        $fieldRules[] = 'accepted';
                    } else {
                        $fieldRules[] = 'boolean';
                    }
                    break;
                case 'select':
                case 'radio':
                    if (!empty($field['options'])) {
                        $options = array_column($field['options'], 'value');
                        $fieldRules[] = 'in:' . implode(',', $options);
                    }
                    break;
                default:
                    $fieldRules[] = 'string';
                    if (isset($field['maxLength'])) {
                        $fieldRules[] = 'max:' . $field['maxLength'];
                    }
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }
}

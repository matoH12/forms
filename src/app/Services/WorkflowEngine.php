<?php

namespace App\Services;

use App\Jobs\ExecuteWorkflowStep;
use App\Models\ApprovalRequest;
use App\Models\EmailTemplate;
use App\Models\FormSubmission;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WorkflowEngine
{
    /**
     * SECURITY: Maximum JSON nesting depth to prevent stack overflow DoS attacks
     */
    private const JSON_MAX_DEPTH = 32;

    public function triggerForSubmission(FormSubmission $submission): void
    {
        $workflows = Workflow::where('form_id', $submission->form_id)
            ->where('is_active', true)
            ->where('trigger_on', 'submission')
            ->get();

        foreach ($workflows as $workflow) {
            $this->startExecution($workflow, $submission);
        }
    }

    public function startExecution(Workflow $workflow, FormSubmission $submission): WorkflowExecution
    {
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'status' => WorkflowExecution::STATUS_RUNNING,
            'context' => [
                'submission' => $submission->data,
                'form' => [
                    'id' => $submission->form_id,
                    'name' => $submission->form->name,
                ],
                'user' => $submission->user ? [
                    'id' => $submission->user_id,
                    'name' => $submission->user->name,
                    'email' => $submission->user->email,
                    'login' => $submission->user->login,
                ] : null,
            ],
            'logs' => [],
            'started_at' => now(),
        ]);

        $execution->addLog('Workflow spustený');

        // Find start node and first connected node
        $startNode = collect($workflow->nodes)->firstWhere('type', 'start');
        if ($startNode) {
            $firstEdge = collect($workflow->edges)->firstWhere('source', $startNode['id']);
            if ($firstEdge) {
                $execution->update(['current_node_id' => $firstEdge['target']]);
                ExecuteWorkflowStep::dispatch($execution);
            }
        }

        return $execution;
    }

    public function continueExecution(WorkflowExecution $execution): void
    {
        $execution->update(['status' => WorkflowExecution::STATUS_RUNNING]);
        $execution->addLog('Pokračovanie po schválení');

        // Find next node
        $currentNodeId = $execution->current_node_id;
        $workflow = $execution->workflow;
        $nextEdge = collect($workflow->edges)->firstWhere('source', $currentNodeId);

        if ($nextEdge) {
            $execution->update(['current_node_id' => $nextEdge['target']]);
            ExecuteWorkflowStep::dispatch($execution);
        } else {
            $this->completeExecution($execution);
        }
    }

    public function executeStep(WorkflowExecution $execution): void
    {
        $workflow = $execution->workflow;
        $currentNode = collect($workflow->nodes)->firstWhere('id', $execution->current_node_id);

        if (!$currentNode) {
            $this->completeExecution($execution);
            return;
        }

        if ($currentNode['type'] === 'end') {
            $this->completeExecution($execution);
            return;
        }

        $stepLabel = $currentNode['data']['label'] ?? $currentNode['type'];
        $execution->addLog("Vykonávam krok: {$stepLabel}");

        try {
            $result = match ($currentNode['type']) {
                'api_call' => $this->executeApiCall($execution, $currentNode),
                'approval' => $this->executeApproval($execution, $currentNode),
                'condition' => $this->executeCondition($execution, $currentNode),
                'transform' => $this->executeTransform($execution, $currentNode),
                'email' => $this->executeEmail($execution, $currentNode),
                'delay' => $this->executeDelay($execution, $currentNode),
                default => ['success' => true],
            };

            if ($result['wait'] ?? false) {
                return;
            }

            // Move to next node
            $edgeKey = $result['success'] ? 'source' : 'sourceHandle';
            $nextEdge = collect($workflow->edges)
                ->first(function ($edge) use ($currentNode, $result) {
                    if ($edge['source'] !== $currentNode['id']) {
                        return false;
                    }
                    if (isset($result['branch'])) {
                        return ($edge['sourceHandle'] ?? 'default') === $result['branch'];
                    }
                    return true;
                });

            if ($nextEdge) {
                $execution->update(['current_node_id' => $nextEdge['target']]);

                // If delay is specified, schedule the next step with delay
                if (isset($result['delay']) && $result['delay'] > 0) {
                    ExecuteWorkflowStep::dispatch($execution)->delay(now()->addSeconds($result['delay']));
                } else {
                    ExecuteWorkflowStep::dispatch($execution);
                }
            } else {
                $this->completeExecution($execution);
            }
        } catch (\Exception $e) {
            $execution->addLog('Chyba: ' . $e->getMessage());
            $execution->update([
                'status' => WorkflowExecution::STATUS_FAILED,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Validate URL to prevent SSRF attacks
     * Blocks internal networks, localhost, and cloud metadata endpoints
     * Supports both IPv4 and IPv6
     */
    private function isUrlSafeForSsrf(string $url): array
    {
        $parsed = parse_url($url);

        if (!$parsed || empty($parsed['host'])) {
            return [false, 'Invalid URL format'];
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme'] ?? 'http');

        // Only allow http and https
        if (!in_array($scheme, ['http', 'https'])) {
            return [false, "Protocol '{$scheme}' not allowed"];
        }

        // Remove brackets from IPv6 addresses in URL (e.g., [::1] -> ::1)
        $cleanHost = trim($host, '[]');

        // Block localhost variations
        $localhostPatterns = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array($cleanHost, $localhostPatterns)) {
            return [false, 'Localhost access not allowed'];
        }

        // Check if host is already an IP address
        $ip = null;
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            $ip = $cleanHost;
        } else {
            // Try to resolve hostname - first try IPv4
            $ip = gethostbyname($cleanHost);
            if ($ip === $cleanHost) {
                // IPv4 resolution failed, try IPv6
                $dns = dns_get_record($cleanHost, DNS_AAAA);
                if (!empty($dns) && isset($dns[0]['ipv6'])) {
                    $ip = $dns[0]['ipv6'];
                } else {
                    // Could be internal hostname - block by default
                    return [false, 'Cannot resolve hostname'];
                }
            }
        }

        // Check if IP is in blocked ranges (handles both IPv4 and IPv6)
        $blockedReason = $this->isIpBlocked($ip);
        if ($blockedReason !== null) {
            return [false, $blockedReason];
        }

        // Block cloud metadata endpoints by hostname
        $blockedHosts = [
            'metadata.google.internal',
            'metadata.goog',
            'metadata',
        ];
        if (in_array($cleanHost, $blockedHosts)) {
            return [false, 'Cloud metadata endpoint not allowed'];
        }

        return [true, null];
    }

    /**
     * Check if IPv4 is in CIDR range
     */
    private function ipv4InRange(string $ip, string $range): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Check if IPv6 is in CIDR range
     */
    private function ipv6InRange(string $ip, string $range): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range);
        $bits = (int)$bits;

        // Convert to binary representation
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Compare the required number of bits
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        // Compare full bytes
        for ($i = 0; $i < $fullBytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Compare remaining bits if any
        if ($remainingBits > 0 && $fullBytes < 16) {
            $mask = 0xFF << (8 - $remainingBits);
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if IP (v4 or v6) is in a blocked range
     */
    private function isIpBlocked(string $ip): ?string
    {
        // IPv4 blocked ranges
        $blockedIPv4Ranges = [
            '127.0.0.0/8',       // Loopback
            '10.0.0.0/8',        // Private
            '172.16.0.0/12',     // Private
            '192.168.0.0/16',    // Private
            '169.254.0.0/16',    // Link-local (AWS metadata)
            '100.64.0.0/10',     // Carrier-grade NAT
            '192.0.2.0/24',      // Documentation
            '198.51.100.0/24',   // Documentation
            '203.0.113.0/24',    // Documentation
            '224.0.0.0/4',       // Multicast
            '240.0.0.0/4',       // Reserved
            '0.0.0.0/8',         // Reserved
        ];

        // IPv6 blocked ranges
        $blockedIPv6Ranges = [
            '::1/128',           // Loopback
            '::/128',            // Unspecified
            '::ffff:0:0/96',     // IPv4-mapped (could bypass IPv4 blocks)
            '::ffff:127.0.0.0/104',  // IPv4-mapped loopback
            '::ffff:10.0.0.0/104',   // IPv4-mapped private
            '::ffff:172.16.0.0/108', // IPv4-mapped private
            '::ffff:192.168.0.0/112',// IPv4-mapped private
            '::ffff:169.254.0.0/112',// IPv4-mapped link-local
            'fc00::/7',          // Unique local (private)
            'fe80::/10',         // Link-local
            'ff00::/8',          // Multicast
            '64:ff9b::/96',      // NAT64
            '100::/64',          // Discard
            '2001:db8::/32',     // Documentation
        ];

        // Check IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach ($blockedIPv4Ranges as $range) {
                if ($this->ipv4InRange($ip, $range)) {
                    return "Access to internal network ({$range}) not allowed";
                }
            }
            return null;
        }

        // Check IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            foreach ($blockedIPv6Ranges as $range) {
                if ($this->ipv6InRange($ip, $range)) {
                    return "Access to internal network ({$range}) not allowed";
                }
            }
            return null;
        }

        // Unknown IP format - block by default
        return "Invalid IP address format";
    }

    private function executeApiCall(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $context = $execution->context;

        // Log available context keys for debugging
        $contextKeys = $this->getContextKeysRecursive($context);
        $execution->addLog("Dostupné premenné: " . implode(', ', $contextKeys));

        // Store original templates for debugging
        $originalUrl = $config['url'] ?? '';
        $originalBody = $config['body'] ?? '';

        $url = $this->replaceVariables($originalUrl, $context);
        $method = strtolower($config['method'] ?? 'get');
        $headers = $this->replaceVariablesInArray($config['headers'] ?? [], $context);
        $body = $this->replaceVariables($originalBody, $context);
        $timeout = (int) ($config['timeout'] ?? 30);
        $async = (bool) ($config['async'] ?? false);
        $insecure = (bool) ($config['insecure'] ?? false);
        $retryCount = (int) ($config['retry_count'] ?? 0);
        $retryDelay = (int) ($config['retry_delay'] ?? 5);

        // Limit timeout to max 10 minutes (600 seconds)
        $timeout = min($timeout, 600);

        // SSRF Protection: Validate URL before making request
        [$isSafe, $ssrfError] = $this->isUrlSafeForSsrf($url);
        if (!$isSafe) {
            $execution->addLog("SSRF ochrana: {$ssrfError} - URL: {$url}");
            return ['success' => false, 'error' => "SSRF protection: {$ssrfError}"];
        }

        $execution->addLog("API volanie: {$method} {$url}" . ($async ? ' (async)' : '') . ($insecure ? ' (insecure)' : '') . " [timeout: {$timeout}s]");

        // Log request details for debugging
        $debugHeaders = $headers;
        // Mask sensitive headers for security
        foreach (['authorization', 'x-api-key', 'api-key', 'token', 'x-token'] as $sensitiveHeader) {
            foreach ($debugHeaders as $key => $value) {
                if (strtolower($key) === $sensitiveHeader) {
                    $debugHeaders[$key] = substr($value, 0, 10) . '***MASKED***';
                }
            }
        }
        $execution->addLog("Request headers: " . json_encode($debugHeaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($body && $method !== 'get' && $method !== 'delete') {
            // Try to pretty print JSON body, otherwise show raw
            $decodedBody = json_decode($body, true);
            if ($decodedBody !== null) {
                $execution->addLog("Request body: " . json_encode($decodedBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } else {
                $execution->addLog("Request body (raw): " . $body);
            }
        }

        // Fire and forget mode - just send the request and continue
        if ($async) {
            try {
                Http::withHeaders($headers)
                    ->timeout(5) // Short timeout for async - just ensure request is sent
                    ->async()
                    ->withOptions(['verify' => false])
                    ->{$method}($url, $method !== 'get' && $method !== 'delete' ? (json_decode($body, true, self::JSON_MAX_DEPTH) ?: []) : []);

                $execution->addLog("Async API volanie odoslané (nepočkáme na odpoveď)");

                return ['success' => true];
            } catch (\Exception $e) {
                // For async, we consider it success if request was dispatched
                $execution->addLog("Async API volanie odoslané (chyba ignorovaná: {$e->getMessage()})");
                return ['success' => true];
            }
        }

        // Synchronous mode with retry support
        $attempt = 0;
        $maxAttempts = $retryCount + 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $request = Http::withHeaders($headers)->timeout($timeout);

                // If insecure mode is enabled, skip SSL certificate verification
                if ($insecure) {
                    $request = $request->withOptions(['verify' => false]);
                }

                // SECURITY: Limit JSON depth to prevent DoS
                $bodyData = json_decode($body, true, self::JSON_MAX_DEPTH) ?: [];

                $response = match ($method) {
                    'post' => $request->post($url, $bodyData),
                    'put' => $request->put($url, $bodyData),
                    'patch' => $request->patch($url, $bodyData),
                    'delete' => $request->delete($url),
                    default => $request->get($url),
                };

                $success = $response->successful();

                $execution->addLog("API odpoveď: {$response->status()}" . ($attempt > 1 ? " (pokus {$attempt})" : ''), [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                // Store response in context
                $context['last_api_response'] = [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ];
                $execution->update(['context' => $context]);

                // If successful or no retries left, return
                if ($success || $attempt >= $maxAttempts) {
                    return ['success' => $success];
                }

                // Wait before retry
                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }

            } catch (\Exception $e) {
                $lastException = $e;
                $execution->addLog("API chyba (pokus {$attempt}/{$maxAttempts}): {$e->getMessage()}");

                if ($attempt >= $maxAttempts) {
                    // Store error in context
                    $context['last_api_response'] = [
                        'status' => 0,
                        'error' => $e->getMessage(),
                        'body' => null,
                    ];
                    $execution->update(['context' => $context]);

                    return ['success' => false];
                }

                // Wait before retry
                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            }
        }

        return ['success' => false];
    }

    private function executeApproval(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $context = $execution->context;

        $approverEmail = $this->replaceVariables($config['approver_email'] ?? '', $context);

        $approval = ApprovalRequest::create([
            'workflow_execution_id' => $execution->id,
            'node_id' => $node['id'],
            'approver_email' => $approverEmail,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);

        $execution->update(['status' => WorkflowExecution::STATUS_WAITING_APPROVAL]);
        $execution->addLog("Čakám na schválenie od: {$approverEmail}");

        // Send notification email
        $approvalUrl = url("/approvals/{$approval->token}");
        Mail::raw(
            "Bola vám priradená žiadosť na schválenie.\n\nKliknutím na odkaz môžete schváliť alebo zamietnuť:\n{$approvalUrl}",
            function ($message) use ($approverEmail) {
                $message->to($approverEmail)
                    ->subject('Žiadosť o schválenie');
            }
        );

        return ['success' => true, 'wait' => true];
    }

    private function executeCondition(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $context = $execution->context;

        $field = $config['field'] ?? '';
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? '';

        $fieldValue = data_get($context, $field);
        $compareValue = $this->replaceVariables($value, $context);

        $result = match ($operator) {
            'equals' => $fieldValue == $compareValue,
            'not_equals' => $fieldValue != $compareValue,
            'contains' => str_contains((string) $fieldValue, $compareValue),
            'greater_than' => $fieldValue > $compareValue,
            'less_than' => $fieldValue < $compareValue,
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            default => false,
        };

        $execution->addLog("Podmienka: {$field} {$operator} {$value} = " . ($result ? 'true' : 'false'));

        return ['success' => true, 'branch' => $result ? 'true' : 'false'];
    }

    private function executeTransform(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $context = $execution->context;

        $transformations = $config['transformations'] ?? [];

        foreach ($transformations as $transform) {
            $targetField = $transform['target'] ?? '';
            $sourceField = $transform['source'] ?? '';
            $operation = $transform['operation'] ?? 'copy';

            $sourceValue = data_get($context, $sourceField);

            // SECURITY: Limit JSON depth to prevent DoS
            $newValue = match ($operation) {
                'copy' => $sourceValue,
                'uppercase' => strtoupper((string) $sourceValue),
                'lowercase' => strtolower((string) $sourceValue),
                'trim' => trim((string) $sourceValue),
                'json_encode' => json_encode($sourceValue, 0, self::JSON_MAX_DEPTH),
                'json_decode' => json_decode($sourceValue, true, self::JSON_MAX_DEPTH),
                default => $sourceValue,
            };

            data_set($context, $targetField, $newValue);
        }

        $execution->update(['context' => $context]);
        $execution->addLog('Transformácia vykonaná');

        return ['success' => true];
    }

    private function executeEmail(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $context = $execution->context;

        $to = $this->replaceVariables($config['to'] ?? '', $context);

        // If no recipient, try to get from user context
        if (empty($to) && isset($context['user']['email'])) {
            $to = $context['user']['email'];
        }

        if (empty($to)) {
            $execution->addLog("Email neodoslaný - chýba príjemca");
            return ['success' => false];
        }

        // Get submission for template rendering
        $submission = $execution->submission;

        // Check if using a template
        $templateId = $config['template_id'] ?? null;

        if ($templateId) {
            $template = EmailTemplate::find($templateId);

            if (!$template) {
                $execution->addLog("Email neodoslaný - šablóna s ID {$templateId} neexistuje");
                return ['success' => false];
            }

            // Render template
            $htmlContent = $template->renderHtml($submission);
            $subject = $template->renderSubject($submission);

            Mail::send([], [], function ($message) use ($to, $subject, $htmlContent) {
                $message->to($to)
                    ->subject($subject)
                    ->html($htmlContent);
            });

            $execution->addLog("Email odoslaný na: {$to} (šablóna: {$template->name})");
        } else {
            // Fallback to old behavior - plain text
            $subject = $this->replaceVariables($config['subject'] ?? 'Notifikácia', $context);
            $body = $this->replaceVariables($config['body'] ?? '', $context);

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            $execution->addLog("Email odoslaný na: {$to}");
        }

        return ['success' => true];
    }

    private function executeDelay(WorkflowExecution $execution, array $node): array
    {
        $config = $node['data'] ?? [];
        $delaySeconds = (int) ($config['delay_seconds'] ?? 5);

        // Limit delay to max 1 hour (3600 seconds)
        $delaySeconds = min($delaySeconds, 3600);

        $execution->addLog("Čakanie: {$delaySeconds} sekúnd (naplánované)");

        // Return delay info - the executeStep method will handle scheduling
        return [
            'success' => true,
            'delay' => $delaySeconds,
        ];
    }

    private function completeExecution(WorkflowExecution $execution): void
    {
        $execution->update([
            'status' => WorkflowExecution::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $execution->addLog('Workflow dokončený');
    }

    private function replaceVariables(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $path = trim($matches[1]);
            return data_get($context, $path, $matches[0]);
        }, $template);
    }

    private function replaceVariablesInArray(array $items, array $context): array
    {
        $result = [];
        foreach ($items as $key => $value) {
            // Sanitize header key - remove trailing colons and trim whitespace
            $sanitizedKey = rtrim(trim($this->replaceVariables($key, $context)), ':');
            if ($sanitizedKey) {
                $result[$sanitizedKey] = $this->replaceVariables($value, $context);
            }
        }
        return $result;
    }

    /**
     * Get all available context keys recursively for debugging
     */
    private function getContextKeysRecursive(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value) && !empty($value)) {
                // Check if it's an associative array (object-like) vs indexed array
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    $keys = array_merge($keys, $this->getContextKeysRecursive($value, $fullKey));
                } else {
                    $keys[] = "{{$fullKey}}";
                }
            } else {
                $keys[] = "{{$fullKey}}";
            }
        }
        return $keys;
    }

    public function testWorkflow(Workflow $workflow, array $testData): array
    {
        $steps = [];
        $context = ['submission' => $testData];

        $startNode = collect($workflow->nodes)->firstWhere('type', 'start');
        $currentNodeId = null;

        if ($startNode) {
            $firstEdge = collect($workflow->edges)->firstWhere('source', $startNode['id']);
            $currentNodeId = $firstEdge['target'] ?? null;
        }

        $visited = [];
        while ($currentNodeId && !in_array($currentNodeId, $visited)) {
            $visited[] = $currentNodeId;
            $node = collect($workflow->nodes)->firstWhere('id', $currentNodeId);

            if (!$node || $node['type'] === 'end') {
                break;
            }

            $steps[] = [
                'node_id' => $currentNodeId,
                'type' => $node['type'],
                'label' => $node['data']['label'] ?? $node['type'],
                'config' => $node['data'] ?? [],
            ];

            $nextEdge = collect($workflow->edges)->firstWhere('source', $currentNodeId);
            $currentNodeId = $nextEdge['target'] ?? null;
        }

        return [
            'steps' => $steps,
            'context' => $context,
        ];
    }
}

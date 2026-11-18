<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AutomationController extends Controller
{
    use ApiResponse;

    protected array $defaultRules = [
        [
            'key' => 'auto_invoice_on_job_complete',
            'name' => 'Auto-Invoice on Job Completion',
            'description' => 'Automatically generate draft invoices when technician marks work order as completed.',
            'category' => 'Billing',
            'enabled' => true,
        ],
        [
            'key' => 'auto_work_order_on_estimate_approved',
            'name' => 'Auto-Job on Estimate Approval',
            'description' => 'Atomically convert customer-approved estimates into scheduled work orders.',
            'category' => 'Dispatch',
            'enabled' => true,
        ],
        [
            'key' => 'appointment_reminder_24h',
            'name' => '24-Hour Appointment Reminders',
            'description' => 'Queue SMS & Email confirmations 24 hours prior to scheduled technician arrival.',
            'category' => 'Customer Experience',
            'enabled' => true,
        ],
        [
            'key' => 'overdue_invoice_reminder_7d',
            'name' => 'Overdue Invoice Chase Sequence',
            'description' => 'Send automatic polite payment reminder 7 days after invoice due date.',
            'category' => 'Collections',
            'enabled' => true,
        ],
        [
            'key' => 'notify_dispatcher_emergency_request',
            'name' => 'Emergency Dispatch Broadcast',
            'description' => 'Instant push notification to all online dispatchers when an emergency request is lodged.',
            'category' => 'Dispatch',
            'enabled' => true,
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');
        $cacheKey = "company_{$companyId}_automations";

        $rules = Cache::get($cacheKey, $this->defaultRules);

        $recentLogs = AuditLog::where('company_id', $companyId)
            ->whereIn('action', ['estimate.approved', 'work_order.completed', 'invoice.paid', 'appointment.scheduled'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'rules' => $rules,
            'recent_executions' => $recentLogs,
        ], 'Automation rules retrieved successfully.');
    }

    public function toggle(Request $request, string $key): JsonResponse
    {
        $companyId = app('currentCompanyId');
        $cacheKey = "company_{$companyId}_automations";

        $rules = Cache::get($cacheKey, $this->defaultRules);

        $found = false;
        foreach ($rules as &$rule) {
            if ($rule['key'] === $key) {
                $rule['enabled'] = !(bool) $rule['enabled'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            return $this->error('Automation rule not found.', 404);
        }

        Cache::put($cacheKey, $rules, now()->addYears(1));

        return $this->success([
            'rules' => $rules,
        ], 'Automation rule updated successfully.');
    }
}

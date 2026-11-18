<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Appointment;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // 1. KPI Counts
        $todayAppointmentsCount = Appointment::where('company_id', $companyId)
            ->whereDate('start_time', $today)
            ->count();

        $newRequestsCount = ServiceRequest::where('company_id', $companyId)
            ->where('status', 'new')
            ->count();

        $activeJobsCount = WorkOrder::where('company_id', $companyId)
            ->whereIn('status', ['assigned', 'in_progress', 'started'])
            ->count();

        $completedJobsCount = WorkOrder::where('company_id', $companyId)
            ->where('status', 'completed')
            ->count();

        $pendingEstimatesCount = Estimate::where('company_id', $companyId)
            ->whereIn('status', ['draft', 'sent'])
            ->count();

        $unpaidInvoicesCount = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['issued', 'sent', 'partially_paid', 'overdue'])
            ->count();

        $overdueInvoicesCount = Invoice::where('company_id', $companyId)
            ->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($sub) use ($today) {
                      $sub->whereIn('status', ['issued', 'sent', 'partially_paid'])
                          ->whereDate('due_date', '<', $today);
                  });
            })
            ->count();

        $revenueThisMonth = (float) Payment::where('company_id', $companyId)
            ->where('status', 'succeeded')
            ->where('paid_at', '>=', $thisMonth)
            ->sum('amount');

        // 2. Upcoming Appointments (Next 5)
        $upcomingAppointments = Appointment::where('company_id', $companyId)
            ->with(['customer', 'technician'])
            ->where('start_time', '>=', Carbon::now()->subHours(2))
            ->orderBy('start_time', 'asc')
            ->limit(5)
            ->get();

        // 3. Urgent / Actionable Requests
        $urgentRequests = ServiceRequest::where('company_id', $companyId)
            ->with(['customer'])
            ->whereIn('priority', ['urgent', 'emergency', 'high'])
            ->whereIn('status', ['new', 'pending'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 4. Automation Alerts
        $alerts = [];

        if ($overdueInvoicesCount > 0) {
            $alerts[] = [
                'type' => 'overdue_invoices',
                'severity' => 'error',
                'title' => "$overdueInvoicesCount Overdue Invoices",
                'message' => 'Client payment due dates have lapsed. Automatic reminders queued.',
                'action_route' => '/invoices',
            ];
        }

        $completedWithoutInvoice = WorkOrder::where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereDoesntHave('invoice')
            ->count();

        if ($completedWithoutInvoice > 0) {
            $alerts[] = [
                'type' => 'completed_unbilled',
                'severity' => 'warning',
                'title' => "$completedWithoutInvoice Completed Jobs Need Invoicing",
                'message' => 'Technicians completed field work. Tap to generate invoices.',
                'action_route' => '/work-orders',
            ];
        }

        $unassignedRequestsCount = ServiceRequest::where('company_id', $companyId)
            ->where('status', 'new')
            ->doesntHave('appointment')
            ->count();

        if ($unassignedRequestsCount > 0) {
            $alerts[] = [
                'type' => 'unassigned_requests',
                'severity' => 'info',
                'title' => "$unassignedRequestsCount Incoming Requests Unassigned",
                'message' => 'Customers waiting for dispatch and schedule confirmation.',
                'action_route' => '/service-requests',
            ];
        }

        return $this->success([
            'metrics' => [
                'today_appointments' => $todayAppointmentsCount,
                'new_requests' => $newRequestsCount,
                'active_jobs' => $activeJobsCount,
                'completed_jobs' => $completedJobsCount,
                'pending_estimates' => $pendingEstimatesCount,
                'unpaid_invoices' => $unpaidInvoicesCount,
                'overdue_invoices' => $overdueInvoicesCount,
                'revenue_this_month' => $revenueThisMonth,
            ],
            'upcoming_appointments' => $upcomingAppointments,
            'urgent_requests' => $urgentRequests,
            'automation_alerts' => $alerts,
        ], 'Dashboard summary retrieved successfully.');
    }
}

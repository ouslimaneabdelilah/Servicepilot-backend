<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query()->with(['serviceRequest.customer', 'technician']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($techId = $request->input('technician_id')) {
            $query->where('technician_id', $techId);
        }

        // Calendar date range
        if ($date = $request->input('date')) {
            $startOfDay = Carbon::parse($date)->startOfDay();
            $endOfDay = Carbon::parse($date)->endOfDay();
            $query->whereBetween('starts_at', [$startOfDay, $endOfDay]);
        }

        $appointments = $query->orderBy('starts_at')->paginate($request->integer('per_page', 25));

        return $this->paginated($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'service_request_id' => ['required', 'integer', Rule::exists('service_requests', 'id')->where('company_id', $companyId)],
            'technician_id'      => ['nullable', 'integer'],
            'starts_at'          => ['required', 'date'],
            'ends_at'            => ['nullable', 'date', 'after:starts_at'],
            'status'             => ['sometimes', 'in:scheduled,confirmed,en_route,in_progress,completed,cancelled'],
            'notes'              => ['nullable', 'string', 'max:3000'],
        ]);

        // If technician specified, verify technician belongs to active company
        if (!empty($data['technician_id'])) {
            $isMember = DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $data['technician_id'])
                ->exists();

            if (!$isMember) {
                return $this->error('The selected technician does not belong to this company.', 422);
            }
        }

        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'scheduled';

        $appointment = Appointment::create($data);

        return $this->created($appointment->load(['serviceRequest.customer', 'technician']), 'Appointment scheduled successfully.');
    }

    public function show(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->company_id !== app('currentCompanyId'), 404);

        return $this->success($appointment->load(['serviceRequest.customer', 'technician']));
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->company_id !== app('currentCompanyId'), 404);

        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'service_request_id' => ['sometimes', 'integer', Rule::exists('service_requests', 'id')->where('company_id', $companyId)],
            'technician_id'      => ['nullable', 'integer'],
            'starts_at'          => ['sometimes', 'date'],
            'ends_at'            => ['nullable', 'date', 'after:starts_at'],
            'status'             => ['sometimes', 'in:scheduled,confirmed,en_route,in_progress,completed,cancelled'],
            'notes'              => ['nullable', 'string', 'max:3000'],
        ]);

        $appointment->update($data);

        return $this->success($appointment->fresh()->load(['serviceRequest.customer', 'technician']), 'Appointment updated.');
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return $this->success(null, 'Appointment cancelled & removed.');
    }
}

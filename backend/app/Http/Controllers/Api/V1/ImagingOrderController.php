<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImagingOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ImagingOrder;
use App\Models\RadiologyReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ImagingOrderController extends Controller
{
    /**
     * List imaging orders for a patient.
     */
    public function index(Request $request, $patientId): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $patient = User::where('id', $patientId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access - clinician must have this patient
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patientId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $orders = ImagingOrder::where('user_id', $patientId)
            ->where('organization_id', $organizationId)
            ->with('report')
            ->latest('ordered_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn ($order) => $this->orderPayload($order)),
        ]);
    }

    /**
     * Store a new imaging order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'modality' => ['required', 'in:xray,ct,mri,ultrasound,nuclear_medicine,pet_scan,fluoroscopy'],
            'body_region' => ['nullable', 'string', 'max:100'],
            'clinical_indication' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:routine,urgent,stat'],
            'icd_code' => ['nullable', 'string', 'max:20'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $patient = User::findOrFail($request->patient_id);

        // Verify access - clinician must have this patient
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $order = ImagingOrder::create([
            'user_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'modality' => $request->modality,
            'body_region' => $request->body_region,
            'clinical_indication' => $request->clinical_indication,
            'priority' => $request->priority ?? 'routine',
            'status' => ImagingOrderStatus::Pending->value,
            'icd_code' => $request->icd_code,
            'scheduled_at' => $request->scheduled_at,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($order),
            'message' => 'Imaging order created successfully',
        ], 201);
    }

    /**
     * Get a single imaging order.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $order = ImagingOrder::with('report')
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($order),
        ]);
    }

    /**
     * Update imaging order status (workflow).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,scheduled,in_progress,completed,cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $order = ImagingOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Only the ordering clinician or org admin can update status
        if ($request->user()->id !== $order->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update this imaging order',
            ], 403);
        }

        $order->status = $request->status;
        if ($request->status === ImagingOrderStatus::Completed->value) {
            $order->completed_at = now();
        }
        $order->save();

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($order),
            'message' => 'Imaging order status updated successfully',
        ]);
    }

    /**
     * Record result details for a completed imaging order.
     */
    public function recordResult(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'radiation_dose_mgy' => ['nullable', 'numeric'],
            'image_count' => ['nullable', 'integer', 'min:0'],
            'findings' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $order = ImagingOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->user()->id !== $order->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update this imaging order',
            ], 403);
        }

        if ($request->has('radiation_dose_mgy')) {
            $order->radiation_dose_mgy = $request->radiation_dose_mgy;
        }
        if ($request->has('image_count')) {
            $order->image_count = $request->image_count;
        }
        $order->save();

        if ($order->status !== ImagingOrderStatus::Completed->value) {
            $order->status = ImagingOrderStatus::Completed->value;
            $order->completed_at = now();
            $order->save();
        }

        $report = $order->report()->first();

        if ($report && $request->has('findings')) {
            $report->findings = $request->findings;
            $report->save();
        }

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($order->refresh()->load('report')),
            'message' => 'Imaging result recorded successfully',
        ]);
    }

    /**
     * Cancel an imaging order.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $order = ImagingOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->user()->id !== $order->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to cancel this imaging order',
            ], 403);
        }

        $order->status = ImagingOrderStatus::Cancelled->value;
        $order->save();

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($order),
            'message' => 'Imaging order cancelled successfully',
        ]);
    }

    /**
     * Attach a radiology report to an imaging order.
     */
    public function report(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'findings' => ['nullable', 'string'],
            'impression' => ['nullable', 'string'],
            'report_text' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,final'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $order = ImagingOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->user()->id !== $order->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to report this imaging order',
            ], 403);
        }

        $report = RadiologyReport::updateOrCreate(
            ['imaging_order_id' => $order->id],
            [
                'radiologist_id' => $request->user()->id,
                'findings' => $request->findings,
                'impression' => $request->impression,
                'report_text' => $request->report_text,
                'status' => $request->status ?? 'draft',
                'reported_at' => $request->status === 'final' ? now() : null,
            ]
        );

        if ($request->status === 'final' && $order->status !== ImagingOrderStatus::Completed->value) {
            $order->status = ImagingOrderStatus::Completed->value;
            $order->completed_at = now();
            $order->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $report->id,
                'imaging_order_id' => $report->imaging_order_id,
                'radiologist_name' => $report->radiologist?->name,
                'findings' => $report->findings,
                'impression' => $report->impression,
                'report_text' => $report->report_text,
                'status' => $report->status,
                'reported_at' => $report->reported_at?->toISOString(),
            ],
            'message' => 'Radiology report saved successfully',
        ], 201);
    }

    /**
     * Build a consistent order payload.
     */
    private function orderPayload(ImagingOrder $order): array
    {
        $report = $order->relationLoaded('report') ? $order->report : null;

        return [
            'id' => $order->id,
            'modality' => $order->modality,
            'body_region' => $order->body_region,
            'clinical_indication' => $order->clinical_indication,
            'priority' => $order->priority,
            'status' => $order->status,
            'icd_code' => $order->icd_code,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'scheduled_at' => $order->scheduled_at?->toISOString(),
            'completed_at' => $order->completed_at?->toISOString(),
            'radiation_dose_mgy' => $order->radiation_dose_mgy,
            'image_count' => $order->image_count,
            'notes' => $order->notes,
            'clinician_name' => $order->clinician?->name,
            'user_name' => $order->user?->name,
            'report' => $report ? [
                'id' => $report->id,
                'findings' => $report->findings,
                'impression' => $report->impression,
                'report_text' => $report->report_text,
                'status' => $report->status,
                'reported_at' => $report->reported_at?->toISOString(),
            ] : null,
        ];
    }
}

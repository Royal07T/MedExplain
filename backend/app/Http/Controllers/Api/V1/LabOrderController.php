<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class LabOrderController extends Controller
{
    /**
     * List lab orders for a patient.
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

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No access to this patient',
                ], 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $labOrders = LabOrder::where('user_id', $patient->user_id)
            ->where('organization_id', $organizationId)
            ->latest('ordered_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $labOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'test_name' => $order->test_name,
                    'test_code' => $order->test_code,
                    'status' => $order->status,
                    'ordered_at' => $order->ordered_at?->toISOString(),
                    'result_due_date' => $order->result_due_date,
                    'result_received_at' => $order->result_received_at?->toISOString(),
                    'notes' => $order->notes,
                    'clinician_name' => $order->clinician?->name,
                    'result_value' => $order->result_value,
                    'result_unit' => $order->result_unit,
                    'reference_range_low' => $order->reference_range_low,
                    'reference_range_high' => $order->reference_range_high,
                    'is_abnormal' => $order->is_abnormal,
                    'explanation' => $order->explanation,
                ];
            }),
        ]);
    }

    /**
     * Store a new lab order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:patients,user_id'],
            'test_name' => ['required', 'string', 'max:255'],
            'test_code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'result_due_date' => ['nullable', 'date'],
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

        $labOrder = LabOrder::create([
            'user_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'test_name' => $request->test_name,
            'test_code' => $request->test_code,
            'status' => $request->status ?? LabOrderStatus::Pending->value,
            'result_due_date' => $request->result_due_date,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $labOrder->id,
                'test_name' => $labOrder->test_name,
                'test_code' => $labOrder->test_code,
                'status' => $labOrder->status,
                'ordered_at' => $labOrder->ordered_at?->toISOString(),
                'result_due_date' => $labOrder->result_due_date,
                'result_received_at' => $labOrder->result_received_at?->toISOString(),
                'notes' => $labOrder->notes,
                'clinician_name' => $labOrder->clinician?->name,
            ],
            'message' => 'Lab order created successfully',
        ], 201);
    }

    /**
     * Update lab order status (verification workflow).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,ordered,collected,processing,completed,cancelled'],
            'result_value' => ['sometimes', 'string'],
            'result_unit' => ['sometimes', 'string'],
            'reference_range_low' => ['sometimes', 'numeric'],
            'reference_range_high' => ['sometimes', 'numeric'],
            'is_abnormal' => ['sometimes', 'boolean'],
            'explanation' => ['sometimes', 'string'],
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

        $labOrder = LabOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Only the ordering clinician or org admin can update status
        if ($request->user()->id !== $labOrder->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update this lab order',
            ], 403);
        }

        $labOrder->status = $request->status;
        $labOrder->result_received_at = now();
        
        if ($request->has('result_value')) {
            $labOrder->result_value = $request->result_value;
        }
        if ($request->has('result_unit')) {
            $labOrder->result_unit = $request->result_unit;
        }
        if ($request->has('reference_range_low')) {
            $labOrder->reference_range_low = $request->reference_range_low;
        }
        if ($request->has('reference_range_high')) {
            $labOrder->reference_range_high = $request->reference_range_high;
        }
        if ($request->has('is_abnormal')) {
            $labOrder->is_abnormal = $request->is_abnormal;
        }
        if ($request->has('explanation')) {
            $labOrder->explanation = $request->explanation;
        }
        
        $labOrder->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $labOrder->id,
                'status' => $labOrder->status,
                'result_received_at' => $labOrder->result_received_at?->toISOString(),
                'result_value' => $labOrder->result_value,
                'explanation' => $labOrder->explanation,
            ],
            'message' => 'Lab order status updated successfully',
        ]);
    }

    /**
     * Get a single lab order.
     */
    public function show($id): JsonResponse
    {
        $organizationId = request()->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $labOrder = LabOrder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $labOrder->id,
                'test_name' => $labOrder->test_name,
                'test_code' => $labOrder->test_code,
                'status' => $labOrder->status,
                'ordered_at' => $labOrder->ordered_at?->toISOString(),
                'result_due_date' => $labOrder->result_due_date,
                'result_received_at' => $labOrder->result_received_at?->toISOString(),
                'notes' => $labOrder->notes,
                'clinician_name' => $labOrder->clinician?->name,
                'user_name' => $labOrder->user?->name,
            ],
        ]);
    }
}
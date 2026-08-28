<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BillingController extends Controller
{
    /**
     * List invoices for a patient.
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

        $patient = User::findOrFail($patientId);

        // Verify access - clinician must have this patient
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $invoices = Invoice::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('issued_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->amount,
                    'paid_amount' => $invoice->paid_amount,
                    'status' => $invoice->status,
                    'payment_method' => $invoice->payment_method,
                    'issued_at' => $invoice->issued_at?->toISOString(),
                    'due_at' => $invoice->due_at?->toISOString(),
                    'paid_at' => $invoice->paid_at?->toISOString(),
                    'insurance_claim_id' => $invoice->insurance_claim_id,
                    'notes' => $invoice->notes,
                ];
            }),
        ]);
    }

    /**
     * Create a new invoice/charge.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,user_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'in:insurance,cash,credit_card,transfer'],
            'due_days' => ['sometimes', 'integer', 'min:1'],
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

        $dueAt = $request->due_at ?? now()->addDays($request->due_days ?? 30);

        $invoice = Invoice::create([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'invoice_number' => 'INV-' . now()->timestamp,
            'amount' => $request->amount,
            'description' => $request->description,
            'payment_method' => $request->payment_method,
            'due_at' => $dueAt,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'status' => $invoice->status,
                'due_at' => $invoice->due_at?->toISOString(),
                'message' => 'Invoice created successfully',
            ],
        ], 201);
    }

    /**
     * Update invoice payment status.
     */
    public function pay(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $invoice = Invoice::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'paid_amount' => ['required', 'numeric', 'min:0', 'lte:' . $invoice->amount],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invoice->paid_amount = $request->paid_amount;

        if ($request->paid_amount >= $invoice->amount) {
            $invoice->status = 'paid';
            $invoice->paid_at = now();
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $invoice->id,
                'amount' => $invoice->amount,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at?->toISOString(),
                'remaining' => $invoice->amount - $invoice->paid_amount,
            ],
            'message' => 'Payment recorded successfully',
        ]);
    }

    /**
     * Get a single invoice.
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

        $invoice = Invoice::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'patient_id' => $invoice->patient_id,
                'amount' => $invoice->amount,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
                'issued_at' => $invoice->issued_at?->toISOString(),
                'due_at' => $invoice->due_at?->toISOString(),
                'paid_at' => $invoice->paid_at?->toISOString(),
                'insurance_claim_id' => $invoice->insurance_claim_id,
                'notes' => $invoice->notes,
            ],
        ]);
    }

    /**
     * Get patient's own invoices.
     */
    public function patientInvoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $invoices = Invoice::where('patient_id', $user->id)
            ->where('organization_id', $organizationId)
            ->latest('issued_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description,
                    'amount' => $invoice->amount,
                    'paid_amount' => $invoice->paid_amount,
                    'status' => $invoice->status,
                    'payment_method' => $invoice->payment_method,
                    'issued_at' => $invoice->issued_at?->toISOString(),
                    'due_at' => $invoice->due_at?->toISOString(),
                    'paid_at' => $invoice->paid_at?->toISOString(),
                    'notes' => $invoice->notes,
                ];
            }),
        ]);
    }

    /**
     * Patient makes a payment.
     */
    public function patientPay(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $invoice = Invoice::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('patient_id', $user->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'paid_amount' => ['required', 'numeric', 'min:0', 'lte:' . $invoice->amount],
            'payment_method' => ['required', 'in:cash,credit_card,transfer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invoice->paid_amount = $request->paid_amount;
        $invoice->payment_method = $request->payment_method;

        if ($request->paid_amount >= $invoice->amount) {
            $invoice->status = 'paid';
            $invoice->paid_at = now();
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $invoice->id,
                'amount' => $invoice->amount,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at?->toISOString(),
                'remaining' => $invoice->amount - $invoice->paid_amount,
            ],
            'message' => 'Payment recorded successfully',
        ]);
    }
}
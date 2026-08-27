<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBillingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $invoices = Invoice::where('organization_id', $orgId)
            ->with('patient')
            ->orderByDesc('created_at')
            ->paginate(20);

        $summary = [
            'total_revenue' => (float) Invoice::where('organization_id', $orgId)
                ->where('status', 'paid')
                ->sum('paid_amount'),
            'outstanding' => (float) Invoice::where('organization_id', $orgId)
                ->whereIn('status', ['pending', 'partial'])
                ->sum(DB::raw('amount - paid_amount')),
            'pending_count' => Invoice::where('organization_id', $orgId)
                ->where('status', 'pending')
                ->count(),
            'paid_count' => Invoice::where('organization_id', $orgId)
                ->where('status', 'paid')
                ->count(),
        ];

        return response()->json([
            'data' => [
                'invoices' => $invoices,
                'summary' => $summary,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card,insurance,bank_transfer,other',
            'notes' => 'nullable|string',
        ]);

        $orgId = $request->user()->organization_id;
        $invoiceNumber = 'INV-' . strtoupper(uniqid());

        $invoice = Invoice::create([
            ...$validated,
            'organization_id' => $orgId,
            'invoice_number' => $invoiceNumber,
            'status' => 'pending',
            'paid_amount' => 0,
            'issued_at' => now(),
        ]);

        return response()->json(['data' => $invoice], 201);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $invoice->load('patient');

        return response()->json(['data' => $invoice]);
    }

    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,partial,paid,overdue,cancelled',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card,insurance,bank_transfer,other',
        ]);

        $invoice->update([
            'status' => $validated['status'],
            'paid_amount' => $validated['paid_amount'] ?? $invoice->paid_amount,
            'payment_method' => $validated['payment_method'] ?? $invoice->payment_method,
            'paid_at' => $validated['status'] === 'paid' ? now() : $invoice->paid_at,
        ]);

        return response()->json(['data' => $invoice]);
    }
}

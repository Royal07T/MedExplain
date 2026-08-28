<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MedicationReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Patient self-service medication adherence reminders.
 *
 * A patient manages reminders for their own medications and records when a
 * dose was taken. Reminders aid adherence — they are not a prescription record.
 */
final class MedicationReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        $userId = $request->user()?->getAuthIdentifier();

        if (!$organizationId) {
            return $this->noOrganization();
        }

        $reminders = MedicationReminder::where('organization_id', $organizationId)
            ->where('patient_id', $userId)
            ->orderByDesc('active')
            ->orderBy('scheduled_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reminders->map(fn ($r) => $this->payload($r)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medication_name' => ['required', 'string', 'max:255'],
            'dose' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'frequency' => ['nullable', 'string', 'max:50'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return $this->noOrganization();
        }

        $reminder = MedicationReminder::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->user()->getAuthIdentifier(),
            'medication_name' => $request->input('medication_name'),
            'dose' => $request->input('dose'),
            'route' => $request->input('route'),
            'frequency' => $request->input('frequency'),
            'scheduled_time' => $request->input('scheduled_time'),
            'notes' => $request->input('notes'),
            'active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $this->payload($reminder)], 201);
    }

    public function markTaken(Request $request, $id): JsonResponse
    {
        $reminder = $this->owned($request, $id);
        if ($reminder === null) {
            return $this->notFound();
        }

        $reminder->update(['last_taken_at' => now()]);

        return response()->json(['success' => true, 'data' => $this->payload($reminder)]);
    }

    public function toggleActive(Request $request, $id): JsonResponse
    {
        $reminder = $this->owned($request, $id);
        if ($reminder === null) {
            return $this->notFound();
        }

        $reminder->update(['active' => !$reminder->active]);

        return response()->json(['success' => true, 'data' => $this->payload($reminder)]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $reminder = $this->owned($request, $id);
        if ($reminder === null) {
            return $this->notFound();
        }

        $reminder->delete();

        return response()->json(['success' => true]);
    }

    private function owned(Request $request, $id): ?MedicationReminder
    {
        $organizationId = $request->user()?->organization_id;
        $userId = $request->user()?->getAuthIdentifier();

        return MedicationReminder::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('patient_id', $userId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MedicationReminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'medication_name' => $reminder->medication_name,
            'dose' => $reminder->dose,
            'route' => $reminder->route,
            'frequency' => $reminder->frequency,
            'scheduled_time' => $reminder->scheduled_time?->format('H:i'),
            'notes' => $reminder->notes,
            'active' => $reminder->active,
            'last_taken_at' => $reminder->last_taken_at?->toISOString(),
            'created_at' => $reminder->created_at?->toISOString(),
        ];
    }

    private function noOrganization(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No organization context'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Reminder not found'], 404);
    }

    private function invalid(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }
}

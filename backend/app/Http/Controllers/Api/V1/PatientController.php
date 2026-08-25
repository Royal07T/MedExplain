<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Patient;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    /**
     * Search for patients by MRN, name, phone, or DOB within the user's organization.
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'sometimes|string|max:255',
            'field' => 'sometimes|in:mrn,first_name,last_name,phone,date_of_birth',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $query = Patient::byOrganization($organizationId);

        if ($request->filled('query')) {
            $query->search($request->query);
        }

        $patients = $query->paginate(20);

        return $this->successResponse([
            'patients' => PatientResource::collection($patients),
            'total' => $patients->total(),
            'per_page' => $patients->perPage(),
            'current_page' => $patients->currentPage(),
            'last_page' => $patients->lastPage(),
        ]);
    }

    /**
     * View Patient 360 / Unified Health Record for an authorized patient.
     */
    public function show(Request $request, $id)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($id);

        // Verify the authenticated user has access to this patient
        // In full implementation, this would check clinician grants or patient's own record
        if ($request->user()->isClinician()) {
            // Clinician must have explicit grant for this patient
            $hasGrant = $request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists();

            if (!$hasGrant) {
                return $this->errorResponse('No access to patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            // Non-clinician trying to access another user's patient record
            return $this->errorResponse('No access to patient', 403);
        }

        return $this->successResponse([
            'patient' => new PatientResource($patient),
            'patient_360' => $this->buildPatient360($patient),
        ]);
    }

    /**
     * Build the Patient 360 unified health record.
     */
    private function buildPatient360($patient)
    {
        $organizationId = $patient->organization_id;

        return [
            'demographics' => [
                'mrn' => $patient->mrn,
                'full_name' => $patient->first_name . ' ' . $patient->last_name,
                'date_of_birth' => $patient->date_of_birth,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'blood_type' => $patient->blood_type,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
                'next_of_kin' => [
                    'name' => $patient->next_of_kin_name,
                    'phone' => $patient->next_of_kin_phone,
                ],
                'emergency_contact' => [
                    'name' => $patient->emergency_contact_name,
                    'phone' => $patient->emergency_contact_phone,
                ],
            ],
            'contacts' => [
                'phone' => $patient->phone,
                'email' => $patient->email,
            ],
            'next_of_kin' => [
                'name' => $patient->next_of_kin_name,
                'phone' => $patient->next_of_kin_phone,
            ],
            'allergies' => $patient->allergies ? json_decode($patient->allergies, true) : [],
            'immunizations' => $patient->immunizations ? json_decode($patient->immunizations, true) : [],
            'encounters' => $patient->encounters()->with(['clinician'])->latest()->take(10)->get(['*']),
            'recent_lab_results' => \App\Models\LabResult::where('patient_id', $patient->id)
                ->where('organization_id', $organizationId)
                ->latest('collected_at')
                ->take(5)
                ->get(['*']),
            'recent_medications' => \App\Models\Medication::where('patient_id', $patient->id)
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->latest('start_date')
                ->take(5)
                ->get(['*']),
            'recent_vitals' => null, // Would be populated from encounters
        ];
    }

    /**
     * Error response helper.
     */
    private function errorResponse($message, $code, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Success response helper.
     */
    private function successResponse($data)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

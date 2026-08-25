<?php

namespace App\Http\Resources;

use App\Http\Resources\Resources\AnalysisResource;
use App\Http\Resources\Resources\MedicationResource;
use App\Http\Resources\Resources\LabResultResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'mrn' => $this->mrn,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->age,
            'gender' => $this->gender,
            'blood_type' => $this->blood_type,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'allergies' => $this->allergies ? json_decode($this->allergies, true) : [],
            'immunizations' => $this->immunizations ? json_decode($this->immunizations, true) : [],
            'created_at' => $this->created_at,
        ];
    }
}

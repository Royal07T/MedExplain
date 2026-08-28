<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'organization_id',
        'template_id',
        'note_type',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'full_note',
        'author_id',
        'cosigner_id',
        'cosigned_at',
        'status',
    ];

    protected $casts = [
        'cosigned_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function template()
    {
        return $this->belongsTo(ClinicalNoteTemplate::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cosigner()
    {
        return $this->belongsTo(User::class, 'cosigner_id');
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForEncounter($query, $encounterId)
    {
        return $query->where('encounter_id', $encounterId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('note_type', $type);
    }

    public function scopeFinal($query)
    {
        return $query->where('status', 'final');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function isCosigned()
    {
        return !is_null($this->cosigner_id) && !is_null($this->cosigned_at);
    }

    public function cosign(User $cosigner)
    {
        $this->cosigner_id = $cosigner->id;
        $this->cosigned_at = now();
        $this->save();
    }

    public function finalize()
    {
        $this->status = 'final';
        $this->save();
    }
}

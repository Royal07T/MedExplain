<?php

namespace App\Enums;

enum DocumentType: string
{
    case LabReport = 'lab_report';
    case DoctorReport = 'doctor_report';
    case RadiologyReport = 'radiology_report';
    case Unknown = 'unknown';
}
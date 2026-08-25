<?php

namespace App\Enums;

enum UserRole: string
{
    case Patient = 'patient';
    case Clinician = 'clinician';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
    case NursingStaff = 'nursing_staff';
}
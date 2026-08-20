<?php

namespace App\Enums;

enum HealthQueryIntent: string
{
    case ReportComparison = 'REPORT_COMPARISON';
    case LabTrend = 'LAB_TREND';
    case MedicationContext = 'MEDICATION_CONTEXT';
    case RecentHealthChanges = 'RECENT_HEALTH_CHANGES';
    case CurrentVsPrevious = 'CURRENT_VS_PREVIOUS';
    case HealthTimeline = 'HEALTH_TIMELINE';
    case LabHistory = 'LAB_HISTORY';
    case MedicationHistory = 'MEDICATION_HISTORY';
    case GeneralHealthQuestion = 'GENERAL_HEALTH_QUESTION';
}
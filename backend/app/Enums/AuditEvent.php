<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Register = 'register';
    case Login = 'login';
    case Logout = 'logout';
    case ResendVerification = 'resend_verification';
    case EmailVerified = 'email_verified';
    case RequestPasswordReset = 'request_password_reset';
    case ResetPassword = 'reset_password';

    // Document events — used from Milestone 2 onward.
    case DocumentUploaded = 'document_uploaded';
    case DocumentViewed = 'document_viewed';
    case DocumentDeleted = 'document_deleted';
    case AnalysisCreated = 'analysis_created';

    // Access control & integration events — used from Milestone 4 onward.
    case ClinicianAccessGranted = 'clinician_access_granted';
    case ClinicianRecordViewed = 'clinician_record_viewed';
    case PatientConsentGranted = 'consent_granted';
    case PatientConsentRevoked = 'consent_revoked';
    case PartnerTokenIssued = 'partner_token_issued';
    case PartnerRecordAccessed = 'partner_record_accessed';

    // Plan / subscription events.
    case PlanUpgraded = 'plan_upgraded';
    case PlanCancelled = 'plan_cancelled';
}
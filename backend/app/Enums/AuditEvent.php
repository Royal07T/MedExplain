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
}
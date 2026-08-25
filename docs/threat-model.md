# MEDEXPLAIN OS — Threat Model

## 1. Overview

This threat model covers the security threats relevant to the MEDEXPLAIN OS healthcare platform, a multi-tenant healthcare platform combining HMS, AI-native platform for AI-powered clinical intelligence, EMR, LIS, pharmacy, and administrative operations.

The platform processes highly sensitive patient data including PII, PHI, laboratory results, medications, clinical notes, and insurance information. This document identifies the significant threats, their attack vectors, impacts, mitigations, detection mechanisms, and recovery strategies.

---

## 2. Threat Catalog

### 2.1 External Attacker

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| SQL injection | Crafted input through API endpoints aiming to manipulate database queries | Unauthorized data access, data modification, data exfiltration, potential patient record corruption | Parameterized queries via Eloquent ORM, input validation on all API endpoints, prepared statements, least-privilege database accounts | WAF alerts, query pattern monitoring in application logs, automated SQL injection testing (SQLMap) | Incident response: rotate DB credentials, patch vulnerable endpoints, forensic analysis of data modifications, restore from clean backup |
| API authentication bypass | Exploiting weaknesses in Sanctum token validation, forged tokens | Full account takeover, unauthorized access to all patient data for that organization | Secure token comparison, token binding to user IP/device where appropriate, rate-limited auth endpoints, token revocation on suspicious activity | Auth failure monitoring, anomalous token patterns, rate limit triggers | Forensic account review, revoke all tokens, enforce MFA, update auth logic |
| Port scanning / service enumeration | Probing for open ports, service versions | Information gathering for targeted attacks | Closed unnecessary ports, WAF rate limiting, no service exposure beyond intended surface | IDS/IPS alerts, firewall logs | Service reconfiguration, rotate exposed credentials |
| DDoS attacks | Flooding API endpoints with requests | Service unavailability, degraded patient care availability | Rate limiting per endpoint (auth: 5/min, API: 60/min, health-query: 10/min), request size limits, queue worker configuration | Monitoring of request rates, error thresholds, health check alerts | Traffic diversion, scale resources, investigate source IPs |

### 2.2 Malicious Patient

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| IDOR / BOLA | Manipulating user IDs in API requests (e.g., `/api/v1/documents/5`) to access other patients' records | Access to unrelated patient medical records, privacy violation, potential harm | Server-side authorization via Laravel Policies with organization scoping, never trust frontend-supplied IDs, organization_id on all resources, correlation ID auditing | Access audit logs, attempted IDOR patterns in logs, 403 error tracking | Terminate access, investigate intent, reinforce authorization, update policies if needed |
| Search injection | Crafting search queries to extract broad patient data | Exposure of patient lists, partial data leakage | Search restricted to user's organization, no broad patient listing without auth, sensitive fields hidden from search suggestions | Search log monitoring, unusual query patterns | Review search implementation, tighten filters |
| Self-data manipulation | Modifying own medical records, lab results, medications | Fabricated medical history, compromised clinical decision-making | Append-only/versioned records for clinical notes, lab results, diagnoses, medication changes; every modification logged with original value, who, when, reason; immutable clinical history | Version comparison in audit logs, discrepancy detection, change reason validation | Revert to original, investigate, apply disciplinary action if insider, document correction |

### 2.3 Compromised Employee Account

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Credential theft | Phishing, keyloggers, credential stuffing | Unauthorized access with employee's permissions and organizational role | MFA optional (architecture ready), session binding, login throttling (5/min), automatic logout after inactivity, suspicious session termination | Failed login monitoring, IP anomaly detection, geolocation mismatch alerts, session revocation on suspicious activity | Force password reset, revoke all sessions, investigate compromise, implement MFA if not already |
| Privilege escalation | Employee exploiting role permissions beyond needed scope | Access to data/organization sections beyond job requirements | RBAC with least privilege, resource-level authorization, explicit grant requirements for sensitive operations, department scoping, separation of duties | Permission audits, access review workflows, anomalous access pattern detection | Revoke excess permissions, enforce role-based access, conduct access review |
| Session hijacking | Session fixation, sidejacking on unsecured networks | Impersonation of employee, access to patient data under their identity | HttpOnly/Secure cookies (configuration), token rotation, short session expiration, re-authentication for sensitive operations | Session anomaly detection, IP/user-agent mismatch, concurrent session monitoring | Invalidate compromised sessions, force re-authentication |

### 2.4 Malicious Employee

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Direct database access | Abusing legitimate DB credentials to export patient data | Mass data exfiltration, violation of trust, legal liability | Database account with minimal privileges, connection pooling, all queries audited, encryption at rest, network segmentation | Database access monitoring, query pattern analysis, anomaly detection on bulk exports | Immediate credential revocation, forensic investigation, breach notification if exfiltrated confirmed |
| Audit log tampering | Deleting or modifying audit logs to cover malicious activity | Inability to investigate incidents, lack of accountability | Immutable audit log storage (append-only, separate storage/log system), log integrity checks, hash chaining, restricted write access | Log integrity verification failures, missing log entries, hash mismatch alerts | Rotate logging infrastructure, investigate gap, implement tamper-evident logging |
| Malicious document upload | Uploading crafted PDFs/lab reports with prompt injection or malware | AI prompt injection, malware execution, data exfiltration through AI responses | File type validation, file signature validation, malware scanning architecture, never execute uploaded files, store outside web directory, short-lived authorized download URLs | Malware scanner alerts, file type rejection logs, upload attempt monitoring | Quarantine uploaded file, scan with antivirus, investigate, update scanning rules |

### 2.5 Privilege Escalation

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Role inflation | User gradually granted more permissions than needed | Broad access without justification, insider threat enabler | RBAC with strict permission hierarchy, periodic access review (quarterly), just-in-time access for sensitive operations, elevation requires supervisor approval | Access review workflow completion tracking, pending elevation approvals, permission audit reports | Revoke unjustified permissions, document justification, update role hierarchy if legitimate |
| Permission creep | Permissions granted additively without removal | User accumulates permissions beyond original role scope | Permission management with explicit grant/revoke, automatic permission expiration, role-based default permissions | Quarterly access review reports, permission usage analytics | Remove unjustified permissions, reassign to appropriate role |

### 2.6 Cross-Tenant Access

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Org A accesses Org B data | No organization_id filtering in queries, shared namespace, client-supplied org_id trusted | Organization A reads/modifies/deletes Organization B's patient records, legal/regulatory violation, loss of trust | **Every resource has organization_id**, determined from authenticated user session, never from client request; database-level foreign key constraints; all queries scoped by organization_id; tenant isolation middleware; RAG retrieval organization-scoped; cross-tenant test suite | Cross-tenant test failures, access audit logs showing org_id mismatches, 403 errors for cross-org access attempts | Immediate query fix, database constraint verification, test coverage expansion, incident review if exploitation confirmed |
| Vector database leakage | Global RAG index without tenant scoping | Patient A's documents retrievable by Patient B or unrelated organization | Tenant-aware and permission-aware RAG retrieval, organization_id filter on all vector searches, document-level permission checks, never global vector database | RAG retrieval testing with cross-org queries, embedding similarity anomalies, retrieval authorization test results | Restructure RAG to organization-specific indices, implement permission checks at retrieval layer, re-embed all documents |

### 2.7 Stolen Session

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Session fixation | Fixed session ID before login | Session takeover after user authenticates | Regenerate session ID on login, secure cookie attributes (HttpOnly, Secure, SameSite) | Session ID rotation logs, authentication monitoring | Invalidate affected sessions, force re-login |
| Session sidejacking | Packet capture on unencrypted connection | Token theft from network traffic | Enforce HTTPS everywhere, HSTS, never transmit tokens in plaintext, API responses never include raw tokens in URLs | TLS monitoring, protocol downgrade attempts, mixed content warnings | Invalidate compromised sessions, rotate secrets |

### 2.8 Compromised API Key

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| X-Service-Key leakage | Service key exposed in code, repo, logs, environment | AI service abused, unauthorized data access through AI endpoints | Rotate keys regularly, short-lived keys, environment-only storage, never commit to repo, monitor for key leakage, constant-time comparison (already implemented) | Secret scanning in CI/CD, monitoring for key usage anomalies, entropy checks on reported keys | Rotate service key, update all internal service references, revoke old key, investigate usage |

### 2.9 Malicious Uploaded Document

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Prompt injection in PDFs/lab reports | Document contains adversarial text instructions targeting AI system | AI follows malicious instructions, potential data leakage, hallucinated results | Separate SYSTEM INSTRUCTIONS from USER INPUT from RETRIEVED DATA from DOCUMENT CONTENT; treat all retrieved documents as DATA, not trusted instructions; prompt injection defenses; LLM output validation; never blindly follow document instructions | Prompt injection test suite, unusual AI output patterns, document content analysis | Quarantine document, invalidate AI responses using that document, update injection detection, forensic analysis |
| Malware in uploaded files | PDFs, images, documents containing executable code or malware | System compromise, data exfiltration, lateral movement | File type validation (MIME), file signature validation where possible, limit file size (10MB), malware scanning architecture, store outside executable web directories, never execute uploaded files, use private object storage, generate short-lived authorized download URLs | Antivirus/anti-malware scanner alerts, file type rejection logs, upload attempt monitoring | Quarantine file, scan with multiple engines, update scanning rules, investigate source |

### 2.10 AI Data Exfiltration

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| AI response leaking patient data | AI generates output containing data from other patients/organizations | Cross-patient data disclosure, privacy violation, HIPAA/NDPR implications | Authorization-aware RAG (tenant + permission scoped); citations to source records only from user's organization; no global vector database; fact vs interpretation distinction; structured output validation; never invent clinical information | AI output monitoring, cross-patient retrieval test results, content validation checks | Immediate RAG fix, investigation of data leak path, patient notification if PHI exposed, review authorization logic |

### 2.11 Database Compromise

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Direct database misconfiguration | Open ports, weak credentials, excessive privileges | All patient data exposed, total breach | Database encryption at rest, least-privilege accounts, connection pooling, network segmentation, no direct public access, all access through application layer, audit all connections | Database monitoring, intrusion detection, abnormal query patterns, credential scanning | Rotate credentials, restart database with correct config, forensic analysis, breach assessment if data accessed |

### 2.12 Unauthorized Export

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Bulk data export via API | Automated API calls extracting large patient datasets | Mass privacy violation, regulatory non-compliance | Rate limiting on export endpoints, pagination on all list endpoints, maximum request size limits, export auditing with correlation IDs, require explicit authorization for export operations, anomaly detection on export volume | Export log monitoring, rate limit triggers, volume anomaly detection, correlation ID tracking | Terminate export session, investigate, legal compliance review, patient notification if applicable |

### 2.13 Insider Threat

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Abused legitimate access | Employee accessing data outside job requirements, or modifying records maliciously | Targeted data access/modification, trust violation | RBAC with least privilege, separation of duties, just-in-time access, comprehensive audit logging, regular access reviews, MFA, monitoring of anomalous behavior | Quarterly access reviews, anomaly behavior detection, audit log analysis, permission usage analytics | Revoke access, investigate, disciplinary action if warranted, implement additional controls |

### 2.14 Third-Party Service Compromise

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| OpenAI / LLM provider compromise | Provider service outage, key leakage, provider-side vulnerability | AI service unavailable, potential data exposure through AI responses | Provider-agnostic gateway (configurable), fallback to stub provider, no sensitive data sent as prompt content if avoidable, structured output validation, never trust LLM output as clinical fact | Provider status monitoring, failover to stub/alternative provider, usage monitoring | Switch to alternate provider, rotate keys, investigate provider incident, update gateway config |

### 2.15 Prompt Injection (AI Security)

| Threat | Attack Vector | Impact | Mitigation | Detection | Recovery |
|---|---|---|---|---|---|
| Patient documents with adversarial instructions | PDFs, lab reports, clinical notes containing crafted text targeting AI | AI follows malicious instructions, data exfiltration, hallucinated clinical decisions | **CRITICAL**: Separate SYSTEM INSTRUCTIONS from USER INPUT from RETRIEVED DATA from DOCUMENT CONTENT; retrieved medical documents are DATA, not trusted instructions; implement prompt injection defenses; never blindly follow instructions from PDFs/lab reports/clinical notes; LLM output validated against Pydantic schemas; facts/changes referencing unknown tests dropped; empty output falls back to deterministic summary; "consult a professional" disclaimer always present | Prompt injection test suite, cross-domain retrieval tests, AI output anomaly detection, source citation validation | Quarantine affected document, invalidate AI responses using that document, update injection detection patterns, forensic analysis, update RAG retrieval filters |

---

## 3. Cross-Cutting Concerns

### 3.1 Tenant Isolation

**Attack Vector:** Organization A accesses Organization B's data through any API endpoint, RAG retrieval, or database query.

**Impact:** Violates organizational data boundaries, potential legal/regulatory consequences, loss of trust between healthcare organizations using the platform.

**Mitigation:**
- Every database table has `organization_id` foreign key
- Organization context determined from authenticated user session, **never** from client-supplied request parameter
- All queries automatically scoped by organization_id via Eloquent scope or query wrapper
- RAG/Vector retrieval explicitly filtered by user's organization_id
- Document policies enforce organization scoping
- Middleware (`TenantIsolation`) sets organization context from authenticated user
- Database-level constraints enforce organization relationship
- Automated test suite specifically tests cross-tenant data leakage

**Detection:**
- Audit logs showing organization_id for every request
- Automated cross-tenant test suite (pass/fail)
- Access attempts returning 403 for cross-org requests
- Query pattern monitoring for missing organization_id

### 3.2 Audit Logging

**Requirement:** Immutable, security-conscious audit trail for all sensitive operations.

**Tracked Events:**
- Login, logout, failed login
- Patient record viewed, patient record modified
- Clinical note created, diagnosis changed
- Medication prescribed, medication changed
- Laboratory result created, modified, verified
- Document uploaded, viewed, deleted
- Patient data exported
- AI access to patient data
- Permission changes, role changes
- Administrative actions

**Audit Log Metadata:**
- Actor (user_id, actor_type, organization_id)
- Organization
- Action
- Resource type
- Resource identifier
- Timestamp
- Outcome (success/failure)
- Request correlation ID

**Protection:**
- Audit logs stored in append-only format, separate from primary data
- Hash chaining for integrity verification
- Restricted write access (only logging service can write)
- Regular integrity checks
- Logs themselves protected from unauthorized modification
- No sensitive clinical content stored in logs (redacted/PII-free)

### 3.3 Data Minimization

**Principle:** Collect only what is needed, retain only as long as necessary.

**Implementation:**
- Field-level access control: users only see fields they're authorized for
- Automatic data retention policies (configurable per data type)
- PII redaction in logs and error responses
- Export controls: minimum necessary data left the system
- Document storage: private object storage, not public buckets
- AI retrieval: only fetch data user is authorized to access

### 3.4 Fail-Safe Defaults

**Principle:** Default to deny; everything must be explicitly allowed.

**Implementation:**
- Authentication required for all API endpoints (except public auth/health)
- Authorization enforced server-side; frontend checks are UI-only, not security
- Role + resource-level permissions; having a role does not grant all resources
- Sensitive operations require additional authorization beyond role
- Default deny on all new resources until permission explicitly granted
- Audit logs capture all access attempts (success and failure)

---

## 4. Detection and Monitoring

**Key Monitoring Areas:**
- Auth failure rates and patterns
- Unusual query patterns (especially cross-org access attempts)
- Rate limit triggers across all endpoint categories
- Anomalous session activity (IP changes, concurrent sessions)
- AI output anomalies (cross-patient references, unexpected data)
- Audit log integrity verification failures
- Rate limit and export volume anomalies
- Database access pattern anomalies

**Monitoring Tools:**
- Structured application logs with correlation IDs
- Request ID propagation across all service boundaries
- Metrics dashboard for rate limits, error rates, anomalous patterns
- Alerting on threshold violations (auth failures, rate limit hits, export anomalies)
- Database query monitoring for suspicious patterns
- AI service output validation and monitoring

---

## 5. Recovery Strategies

**Incident Response Framework:**

1. **Identify:** Determine scope of incident, data affected, vectors involved
2. **Contain:** Isolate affected systems, revoke compromised credentials, restrict access
3. **Eradicate:** Remove attack vectors (patched vulnerabilities, rotated keys, revoked access)
4. **Recover:** Restore from clean backups, verify data integrity, resume normal operations
5. **Learn:** Post-incident review, update threat model, improve controls, update documentation

**Backup and Restore:**
- Automated daily database backups, encrypted at rest
- Point-in-time recovery where supported
- Document storage backup (versioned, separate from database)
- Backup retention policy (minimum 90 days, tested monthly)
- Restore testing performed quarterly (documented, proven)
- Disaster recovery plan documented and reviewed annually

**Breach Notification:**
- If patient PHI is confirmed exfiltrated, follow applicable regulations
- Document what data was accessed/exfiltrated
- Determine scope and affected individuals/organizations
- Coordinate with legal/ compliance teams
- Notify affected patients/organizations as required by law
- Review and improve controls to prevent recurrence

---

## 6. Threat Model Maintenance

**Review Frequency:**
- Quarterly review of all threat categories
- After any security incident or near-miss
- After major feature releases or architectural changes
- When new regulations or compliance requirements emerge
- When third-party service terms or security postures change

**Updates Required:**
- Threat model document updated with new/threat categories
- Controls updated to address new threats
- Test suite updated to cover new attack vectors
- Documentation updated to reflect current security posture
- Team training on emerging threats and mitigation strategies

**Responsibility:**
- Security team owns threat model maintenance
- Architecture team ensures controls are implemented in new features
- Engineering team implements and tests security controls
- Compliance team validates against regulatory requirements
- Leadership approves risk acceptance for identified threats

---
*Document generated as part of MEDEXPLAIN OS secure development lifecycle.*
*Last reviewed: 2026-08-24*
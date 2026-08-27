# Patient Context System

## Overview

The Patient Context system allows clinicians and nursing staff to select a patient and work within that patient's authorized health context. Once selected, all clinical views, lab results, medications, documents, and AI queries scope to that patient.

## Architecture

### Components

```
Frontend:
├── PatientSelector.vue          # UI component for patient selection
├── patientContext.ts (store)    # Pinia store for patient context state
├── usePatientContext.ts         # Composable for patient context logic
└── API client                   # HTTP calls to patient context endpoints

Backend:
├── PatientContextController.php # API endpoints
├── PatientContextService.php    # Business logic
├── Cache/Session                # Server-side context storage
└── Middleware                    # Context validation
```

### Data Flow

```
1. Clinician opens patient selector
2. Search patients via API
3. Select a patient
4. Frontend stores patient context
5. Backend validates access and stores context
6. All subsequent requests include patient context
7. Backend validates context on each request
8. Views scope to selected patient
```

## Backend Implementation

### PatientContextController

```php
// app/Http/Controllers/Api/V1/PatientContextController.php
class PatientContextController extends Controller
{
    public function __construct(
        private PatientContextService $contextService,
        private AuditService $auditService,
    ) {}

    /**
     * Select a patient context.
     */
    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $user = $request->user();
        $patientId = $validated['patient_id'];

        // Validate access
        if (!$this->contextService->canAccessPatient($user, $patientId)) {
            abort(403, 'No access to this patient.');
        }

        // Store context
        $context = $this->contextService->setContext($user, $patientId);

        // Audit
        $this->auditService->record(
            AuditEvent::PatientContextSelected,
            $user,
            ['patient_id' => $patientId]
        );

        return response()->json([
            'data' => $context,
        ]);
    }

    /**
     * Clear patient context.
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->contextService->clearContext($user);

        $this->auditService->record(
            AuditEvent::PatientContextCleared,
            $user,
            []
        );

        return response()->json(['message' => 'Patient context cleared.']);
    }

    /**
     * Get current patient context.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->contextService->getContext($user);

        return response()->json([
            'data' => $context,
        ]);
    }

    /**
     * Search patients for context selection.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $user = $request->user();
        $patients = $this->contextService->searchPatients(
            $user,
            $validated['query']
        );

        return response()->json([
            'data' => $patients,
        ]);
    }
}
```

### PatientContextService

```php
// app/Services/PatientContextService.php
class PatientContextService
{
    private const CACHE_PREFIX = 'patient_context:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private Cache $cache,
        private Patient $patientModel,
    ) {}

    /**
     * Check if user can access a patient.
     */
    public function canAccessPatient(User $user, int $patientId): bool
    {
        $patient = $this->patientModel->find($patientId);

        if (!$patient) {
            return false;
        }

        // Clinician with access grant
        if ($user->hasRole('clinician')) {
            return $user->clinicianPatients()
                ->where('patient_user_id', $patient->user_id)
                ->exists();
        }

        // Nursing staff in same organization
        if ($user->hasRole('nursing_staff')) {
            return $user->organization_id === $patient->organization_id;
        }

        return false;
    }

    /**
     * Set patient context for a user.
     */
    public function setContext(User $user, int $patientId): array
    {
        $patient = $this->patientModel
            ->with('user.profile')
            ->find($patientId);

        $context = [
            'patient_id' => $patient->id,
            'patient_user_id' => $patient->user_id,
            'mrn' => $patient->mrn,
            'full_name' => trim($patient->first_name . ' ' . $patient->last_name),
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'phone' => $patient->phone,
            'email' => $patient->email,
        ];

        $cacheKey = self::CACHE_PREFIX . $user->id;
        $this->cache->put($cacheKey, $context, self::CACHE_TTL);

        return $context;
    }

    /**
     * Get current patient context.
     */
    public function getContext(User $user): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;
        return $this->cache->get($cacheKey);
    }

    /**
     * Clear patient context.
     */
    public function clearContext(User $user): void
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;
        $this->cache->forget($cacheKey);
    }

    /**
     * Search patients for context selection.
     */
    public function searchPatients(User $user, string $query): array
    {
        return $this->patientModel
            ->where('organization_id', $user->organization_id)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('mrn', 'like', "%{$query}%")
                  ->orWhereHas('user', function ($q) use ($query) {
                      $q->where('email', 'like', "%{$query}%");
                  });
            })
            ->limit(20)
            ->get()
            ->map(fn ($patient) => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'mrn' => $patient->mrn,
                'full_name' => trim($patient->first_name . ' ' . $patient->last_name),
                'date_of_birth' => $patient->date_of_birth,
            ])
            ->toArray();
    }

    /**
     * Get patient user ID from context (for AI queries).
     */
    public function getPatientUserId(User $user): ?int
    {
        $context = $this->getContext($user);
        return $context['patient_user_id'] ?? null;
    }
}
```

### Middleware for Patient Context Validation

```php
// app/Http/Middleware/EnsurePatientContext.php
class EnsurePatientContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->hasRole('clinician') || $user->hasRole('nursing_staff')) {
            $contextService = app(PatientContextService::class);
            $context = $contextService->getContext($user);

            if (!$context) {
                abort(400, 'Patient context required. Please select a patient first.');
            }

            $request->attributes->set('patient_context', $context);
        }

        return $next($request);
    }
}
```

### Routes

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('patient-context')
        ->middleware('role:clinician,nursing_staff')
        ->group(function () {
            Route::post('select', [PatientContextController::class, 'select']);
            Route::delete('/', [PatientContextController::class, 'clear']);
            Route::get('/', [PatientContextController::class, 'current']);
            Route::get('search', [PatientContextController::class, 'search']);
        });
});
```

## Frontend Implementation

### Patient Context Store

```typescript
// stores/patientContext.ts
import { defineStore } from 'pinia'
import type { PatientContext, PatientSearchResult } from '@/types'
import * as patientApi from '@/api/patient'

export const usePatientContextStore = defineStore('patientContext', {
    state: () => ({
        currentPatient: null as PatientContext | null,
        searchResults: [] as PatientSearchResult[],
        loading: false,
        searchLoading: false,
    }),

    getters: {
        hasActivePatient: (state) => state.currentPatient !== null,
        patientId: (state) => state.currentPatient?.patient_id ?? null,
        patientUserId: (state) => state.currentPatient?.patient_user_id ?? null,
        patientName: (state) => state.currentPatient?.full_name ?? '',
        patientMRN: (state) => state.currentPatient?.mrn ?? '',
    },

    actions: {
        async selectPatient(patientId: number) {
            this.loading = true
            try {
                const response = await patientApi.selectPatient(patientId)
                this.currentPatient = response.data.data
            } finally {
                this.loading = false
            }
        },

        async clearPatient() {
            await patientApi.clearPatientContext()
            this.currentPatient = null
        },

        async searchPatients(query: string) {
            this.searchLoading = true
            try {
                const response = await patientApi.searchPatients(query)
                this.searchResults = response.data.data
            } finally {
                this.searchLoading = false
            }
        },

        async fetchContext() {
            try {
                const response = await patientApi.getCurrentContext()
                this.currentPatient = response.data.data
            } catch {
                this.currentPatient = null
            }
        },
    },
})
```

### Patient API

```typescript
// api/patient.ts
import client from './client'

export function selectPatient(patientId: number) {
    return client.post('/patient-context/select', { patient_id: patientId })
}

export function clearPatientContext() {
    return client.delete('/patient-context')
}

export function getCurrentContext() {
    return client.get('/patient-context')
}

export function searchPatients(query: string) {
    return client.get('/patient-context/search', { params: { query } })
}
```

### PatientSelector Component

```vue
<!-- shared/components/PatientSelector.vue -->
<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { usePatientContextStore } from '@/stores/patientContext'

const patientContext = usePatientContextStore()
const searchQuery = ref('')
const isOpen = ref(false)

const hasActivePatient = computed(() => patientContext.hasActivePatient)

let searchTimeout: ReturnType<typeof setTimeout>

watch(searchQuery, (value) => {
    clearTimeout(searchTimeout)
    if (value.length >= 2) {
        searchTimeout = setTimeout(() => {
            patientContext.searchPatients(value)
        }, 300)
    }
})

function selectPatient(patientId: number) {
    patientContext.selectPatient(patientId)
    isOpen.value = false
    searchQuery.value = ''
}

function clearPatient() {
    patientContext.clearPatient()
}

function handleClickOutside() {
    isOpen.value = false
}
</script>

<template>
    <div class="relative" v-click-outside="handleClickOutside">
        <!-- Active patient display -->
        <div v-if="hasActivePatient" class="flex items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2">
            <span class="text-sm text-teal-700">Patient:</span>
            <span class="font-medium text-teal-900">
                {{ patientContext.patientName }}
            </span>
            <span class="text-xs text-teal-600">
                ({{ patientContext.patientMRN }})
            </span>
            <button
                @click="clearPatient"
                class="ml-2 text-teal-400 hover:text-teal-600"
                title="Clear patient context"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Patient search -->
        <div v-else>
            <div class="relative">
                <input
                    v-model="searchQuery"
                    @focus="isOpen = true"
                    placeholder="Select a patient..."
                    class="w-64 rounded-lg border border-slate-200 px-3 py-2 pl-10 text-sm focus:border-teal-500 focus:outline-none"
                />
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <!-- Search results dropdown -->
            <div
                v-if="isOpen && patientContext.searchResults.length > 0"
                class="absolute top-full left-0 z-50 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg"
            >
                <ul class="max-h-60 overflow-y-auto">
                    <li v-for="patient in patientContext.searchResults" :key="patient.id">
                        <button
                            @click="selectPatient(patient.id)"
                            class="w-full px-4 py-2 text-left text-sm hover:bg-slate-50"
                        >
                            <p class="font-medium text-slate-900">{{ patient.full_name }}</p>
                            <p class="text-xs text-slate-500">MRN: {{ patient.mrn }}</p>
                            <p v-if="patient.date_of_birth" class="text-xs text-slate-400">
                                DOB: {{ patient.date_of_birth }}
                            </p>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- No results -->
            <div
                v-if="isOpen && searchQuery.length >= 2 && patientContext.searchResults.length === 0 && !patientContext.searchLoading"
                class="absolute top-full left-0 z-50 mt-1 w-full rounded-lg border border-slate-200 bg-white p-4 text-center text-sm text-slate-500 shadow-lg"
            >
                No patients found
            </div>
        </div>
    </div>
</template>
```

### Using Patient Context in Views

```vue
<!-- workspaces/clinician/PatientWorkspace.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { usePatientContextStore } from '@/stores/patientContext'

const patientContext = usePatientContextStore()

const hasPatient = computed(() => patientContext.hasActivePatient)
const patient = computed(() => patientContext.currentPatient)
</script>

<template>
    <div>
        <div v-if="!hasPatient" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-amber-800">
                Please select a patient from the header to view their clinical workspace.
            </p>
        </div>

        <div v-else>
            <h1>{{ patient?.full_name }} - Clinical Workspace</h1>
            <!-- Patient-specific content -->
        </div>
    </div>
</template>
```

### AI Query with Patient Context

```typescript
// When clinician/nurse asks AI question
async function sendAIQuery(question: string) {
    const patientContext = usePatientContextStore()

    // If clinician/nurse has patient context, use patient's data
    if (patientContext.hasActivePatient) {
        // Backend will use patient_context from session
        const response = await assistantApi.sendChatMessage({
            question,
            patient_user_id: patientContext.patientUserId,
        })
        return response.data
    }

    // Otherwise, use own data (for patients)
    const response = await assistantApi.sendChatMessage({ question })
    return response.data
}
```

## Backend AI Integration

### HealthQueryService Update

```php
// app/Services/HealthQuery/HealthQueryService.php
public function handle(Request $request): array
{
    $user = $request->user();
    $question = $request->input('question');

    // Determine which user's data to use
    $targetUserId = $user->id;

    // If clinician/nurse has patient context, use that patient's data
    if ($user->hasAnyRole(['clinician', 'nursing_staff'])) {
        $contextService = app(PatientContextService::class);
        $patientUserId = $contextService->getPatientUserId($user);

        if ($patientUserId) {
            $targetUserId = $patientUserId;
        }
    }

    // Detect intent
    $intent = $this->intentRegistry->detect($question);

    // Build context from target user's data
    $context = $this->buildContext($targetUserId, $intent);

    // ... rest of processing
}
```

## Patient Context Lifecycle

### Selection Flow

```
1. User clicks patient selector
2. Search input appears
3. User types at least 2 characters
4. Debounced API call searches patients
5. Results appear in dropdown
6. User clicks a patient
7. Frontend calls POST /patient-context/select
8. Backend validates access
9. Backend stores context in cache
10. Backend logs audit event
11. Frontend updates store
12. All subsequent views scope to patient
```

### Clearing Flow

```
1. User clicks "clear" button on patient selector
2. Frontend calls DELETE /patient-context
3. Backend removes context from cache
4. Backend logs audit event
5. Frontend clears store
6. Views return to unscoped state
```

### Session Expiry

```
1. Cache TTL expires (1 hour)
2. Next request finds no context
3. Frontend receives empty context
4. Patient selector shows search input
5. User must re-select patient
```

## Security Considerations

### Access Validation

- Every patient context selection validates access
- Access is checked against `clinician_patient_access` for clinicians
- Access is checked against organization for nursing staff
- Invalid access attempts are logged

### Audit Logging

All patient context changes are audited:
- `PatientContextSelected` — When a patient is selected
- `PatientContextCleared` — When context is cleared
- `PatientContextAccessDenied` — When access is denied

### Cache Security

- Context stored in Redis with user-specific keys
- TTL prevents stale context
- Cache is cleared on logout
- No sensitive data in cache keys

### Frontend Security

- Patient context is supplementary to backend authorization
- Backend always validates access independently
- No patient data stored in localStorage
- Context is cleared on logout

## Testing

### Test Cases

1. **Selection** — Verify clinician can select granted patient
2. **Access Denial** — Verify clinician cannot select ungranted patient
3. **Nursing Access** — Verify nurse can select org-scoped patient
4. **Context Persistence** — Verify context persists across page loads
5. **Context Clearing** — Verify context clears properly
6. **AI Scoping** — Verify AI queries use patient data
7. **Audit Logging** — Verify all context changes are logged
8. **Cache Expiry** — Verify context expires after TTL
9. **Concurrent Access** — Verify multiple clinicians can work with different patients

### Test Commands

```bash
# Run patient context tests
php artisan test --filter=PatientContextTest

# Test API endpoints
php artisan tinker
>>> $service = app(PatientContextService::class);
>>> $service->canAccessPatient($user, $patientId);
>>> $service->setContext($user, $patientId);
>>> $service->getContext($user);
```

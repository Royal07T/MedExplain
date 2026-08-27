<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import LoadingState from '@/components/LoadingState.vue'
import { cancelPlan, upgradePlan } from '@/api/plan'
import { useAuth } from '@/composables/useAuth'
import { useReportsStore } from '@/stores/reports'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const { store: auth, isEmailVerified } = useAuth()
const reports = useReportsStore()
const { routeName } = useRoutePrefix()

const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp'
const AVATAR_MAX_MB = 2

const form = ref({
  name: '',
  firstName: '',
  lastName: '',
  dateOfBirth: '',
  gender: '',
})
const savingProfile = ref(false)
const avatarUploading = ref(false)
const profileSaved = ref(false)
const profileError = ref<string | null>(null)
const avatarError = ref<string | null>(null)
const verificationSent = ref(false)
const verificationError = ref<string | null>(null)

const initials = computed(() => {
  const name = auth.user?.name?.trim() ?? ''
  if (!name) return '?'
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})

const memberSince = computed(() => {
  const created = auth.user?.created_at
  return created
    ? new Date(created).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    : '—'
})

const plan = computed(() => auth.user?.plan ?? 'free')

const planLabel = computed(() => (plan.value === 'pro' ? 'Pro' : 'Free'))

const planUpdating = ref(false)
const planError = ref<string | null>(null)
const planMessage = ref<string | null>(null)

async function handleUpgrade() {
  planUpdating.value = true
  planError.value = null
  planMessage.value = null
  try {
    await upgradePlan()
    await auth.fetchUser()
    planMessage.value = 'You are now on the Pro plan. Thanks for supporting MedExplain!'
  } catch {
    planError.value = 'Could not upgrade right now. Please try again.'
  } finally {
    planUpdating.value = false
  }
}

async function handleCancel() {
  planUpdating.value = true
  planError.value = null
  planMessage.value = null
  try {
    await cancelPlan()
    await auth.fetchUser()
    planMessage.value = 'Your subscription was cancelled. You are back on the Free plan.'
  } catch {
    planError.value = 'Could not cancel right now. Please try again.'
  } finally {
    planUpdating.value = false
  }
}

const pendingCount = computed(
  () =>
    reports.documents.filter((d) => d.status === 'processing' || d.status === 'uploaded')
      .length,
)

const failedCount = computed(() => reports.documents.filter((d) => d.status === 'failed').length)

onMounted(() => {
  if (!auth.user) {
    void auth.fetchUser().catch(() => {})
  }
  resetForm()
  void reports.fetch().catch(() => {})
})

function resetForm() {
  form.value = {
    name: auth.user?.name ?? '',
    firstName: auth.user?.profile?.first_name ?? '',
    lastName: auth.user?.profile?.last_name ?? '',
    dateOfBirth: auth.user?.profile?.date_of_birth ?? '',
    gender: auth.user?.profile?.gender ?? '',
  }
}

const genderOptions = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
  { value: 'prefer_not_to_say', label: 'Prefer not to say' },
]

function pickAvatar(file: File | undefined) {
  avatarError.value = null
  if (!file) return
  if (!AVATAR_ACCEPT.split(',').includes(file.type)) {
    avatarError.value = 'Please choose a JPG, PNG, or WebP image.'
    return
  }
  if (file.size > AVATAR_MAX_MB * 1024 * 1024) {
    avatarError.value = `Image must be ${AVATAR_MAX_MB} MB or smaller.`
    return
  }
  void uploadAvatar(file)
}

async function uploadAvatar(file: File) {
  avatarUploading.value = true
  avatarError.value = null
  try {
    await auth.updateAvatar(file)
  } catch {
    avatarError.value = 'Upload failed. Please try again.'
  } finally {
    avatarUploading.value = false
  }
}

const isUnchanged = computed(
  () =>
    form.value.name.trim() === (auth.user?.name ?? '') &&
    form.value.firstName.trim() === (auth.user?.profile?.first_name ?? '') &&
    form.value.lastName.trim() === (auth.user?.profile?.last_name ?? '') &&
    (form.value.dateOfBirth || null) === (auth.user?.profile?.date_of_birth ?? null) &&
    (form.value.gender || null) === (auth.user?.profile?.gender ?? null),
)

async function saveProfile() {
  profileError.value = null
  profileSaved.value = false
  savingProfile.value = true
  try {
    await auth.updateProfile({
      name: form.value.name.trim(),
      first_name: form.value.firstName.trim() || null,
      last_name: form.value.lastName.trim() || null,
      date_of_birth: form.value.dateOfBirth || null,
      gender: form.value.gender || null,
    })
    profileSaved.value = true
    window.setTimeout(() => {
      profileSaved.value = false
    }, 2500)
  } catch {
    profileError.value = 'Could not save your profile. Please try again.'
  } finally {
    savingProfile.value = false
  }
}

async function handleResendVerification() {
  verificationError.value = null
  verificationSent.value = false
  try {
    await auth.resendVerificationEmail()
    verificationSent.value = true
    window.setTimeout(() => {
      verificationSent.value = false
    }, 4000)
  } catch {
    verificationError.value = 'Could not send a verification email right now.'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div v-if="!auth.user" class="py-8">
      <LoadingState />
    </div>

    <template v-else>
      <section
        class="overflow-hidden rounded-2xl bg-gradient-to-br from-teal-700 via-teal-600 to-teal-500 text-white shadow-sm"
      >
        <div class="flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-center md:justify-between">
          <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
              <div
                class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-white/20 text-2xl font-bold text-white ring-4 ring-white/30"
              >
                <img
                  v-if="auth.user.profile?.avatar_url"
                  :src="auth.user.profile.avatar_url"
                  :alt="`${auth.user.name} profile picture`"
                  class="h-full w-full object-cover"
                />
                <span v-else>{{ initials }}</span>
              </div>

              <label
                class="group absolute -bottom-1 -right-1 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-white text-teal-700 shadow-md transition hover:bg-teal-50"
                :class="avatarUploading ? 'pointer-events-none opacity-70' : ''"
                title="Upload a profile picture"
              >
                <svg
                  v-if="!avatarUploading"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                  />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                  />
                </svg>
                <input
                  type="file"
                  class="hidden"
                  accept=".jpg,.jpeg,.png,.webp"
                  :disabled="avatarUploading"
                  @change="pickAvatar(($event.target as HTMLInputElement).files?.[0])"
                />
              </label>
            </div>

            <div class="min-w-0">
              <h1 class="truncate text-2xl font-bold sm:text-3xl">{{ auth.user.name }}</h1>
              <p class="mt-1 flex items-center gap-2 truncate text-sm text-teal-100">
                {{ auth.user.email }}
                <span
                  class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    isEmailVerified ? 'bg-white/25 text-white' : 'bg-amber-400/90 text-amber-950'
                  "
                >
                  <svg
                    v-if="isEmailVerified"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-3 w-3"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                  {{ isEmailVerified ? 'Verified' : 'Unverified' }}
                </span>
              </p>
              <p class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-teal-50">
                <span>Member since {{ memberSince }}</span>
                <span class="hidden sm:inline">&bull;</span>
                <span class="inline-flex items-center gap-1">
                  {{ planLabel }} plan
                  <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="
                      plan === 'pro' ? 'bg-amber-400/90 text-amber-950' : 'bg-white/20 text-white'
                    "
                  >
                    {{ planLabel }}
                  </span>
                </span>
              </p>
            </div>
          </div>

          <div v-if="!isEmailVerified" class="shrink-0 md:max-w-xs">
            <div class="rounded-xl bg-white/15 p-4 backdrop-blur">
              <p class="text-sm font-semibold">Verify your email</p>
              <p class="mt-0.5 text-xs text-teal-50">
                Confirm your inbox to unlock the full experience.
              </p>
              <button
                type="button"
                class="mt-3 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-teal-700 hover:bg-teal-50"
                @click="handleResendVerification"
              >
                Resend verification email
              </button>
              <p v-if="verificationSent" class="mt-2 text-xs font-medium text-emerald-200">
                Verification email sent.
              </p>
              <p v-if="verificationError" class="mt-2 text-xs text-red-200">
                {{ verificationError }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <p v-if="avatarError" class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
        {{ avatarError }}
      </p>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Uploaded reports</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ reports.documents.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Analyzed</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ reports.processedCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">In progress</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ pendingCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Needs attention</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ failedCount }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-semibold text-slate-900">Personal information</h2>
              <p class="mt-0.5 text-sm text-slate-500">
                How you appear across MedExplain.
              </p>
            </div>
            <span
              v-if="profileSaved"
              class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700"
            >
              Saved
            </span>
          </div>

          <form class="mt-5 space-y-4 border-t border-slate-100 pt-5" @submit.prevent="saveProfile">
            <div>
              <label for="display-name" class="block text-sm font-medium text-slate-700">
                Display name
              </label>
              <input
                id="display-name"
                v-model="form.name"
                type="text"
                maxlength="255"
                required
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="Your name"
              />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="first-name" class="block text-sm font-medium text-slate-700">
                  First name
                </label>
                <input
                  id="first-name"
                  v-model="form.firstName"
                  type="text"
                  maxlength="255"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                  placeholder="Jane"
                />
              </div>
              <div>
                <label for="last-name" class="block text-sm font-medium text-slate-700">
                  Last name
                </label>
                <input
                  id="last-name"
                  v-model="form.lastName"
                  type="text"
                  maxlength="255"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                  placeholder="Doe"
                />
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="date-of-birth" class="block text-sm font-medium text-slate-700">
                  Date of birth
                </label>
                <input
                  id="date-of-birth"
                  v-model="form.dateOfBirth"
                  type="date"
                  :max="new Date().toISOString().split('T')[0]"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
              </div>
              <div>
                <label for="gender" class="block text-sm font-medium text-slate-700">Gender</label>
                <select
                  id="gender"
                  v-model="form.gender"
                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                >
                  <option value="">Prefer not to say</option>
                  <option v-for="option in genderOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </div>
            </div>

            <p v-if="profileError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ profileError }}
            </p>

            <div class="flex items-center gap-3">
              <button
                type="submit"
                :disabled="savingProfile || isUnchanged || !form.name.trim()"
                class="rounded-md bg-teal-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {{ savingProfile ? 'Saving…' : 'Save profile' }}
              </button>
            </div>
          </form>
        </section>

        <section class="space-y-6">
          <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-slate-900">Account</h2>
            <dl class="mt-4 space-y-4 border-t border-slate-100 pt-4">
              <div>
                <dt class="text-sm text-slate-500">Email address</dt>
                <dd class="truncate font-medium text-slate-900">{{ auth.user.email }}</dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500">Email verification</dt>
                <dd>
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="
                      isEmailVerified
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-amber-100 text-amber-700'
                    "
                  >
                    {{ isEmailVerified ? 'Verified' : 'Not verified' }}
                  </span>
                </dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500">Plan</dt>
                <dd class="font-medium text-slate-900">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="
                      plan === 'pro'
                        ? 'bg-amber-100 text-amber-800'
                        : 'bg-slate-100 text-slate-600'
                    "
                  >
                    {{ planLabel }} plan
                  </span>
                </dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500">Member since</dt>
                <dd class="font-medium text-slate-900">
                  {{ new Date(auth.user.created_at).toLocaleDateString() }}
                </dd>
              </div>
            </dl>
          </div>

          <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <h2 class="font-semibold text-slate-900">Plan &amp; billing</h2>
              <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="
                  plan === 'pro'
                    ? 'bg-amber-100 text-amber-800'
                    : 'bg-slate-100 text-slate-600'
                "
              >
                <svg
                  v-if="plan === 'pro'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-3 w-3"
                  fill="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
                  />
                </svg>
                {{ planLabel }} plan
              </span>
            </div>

            <p class="mt-2 text-sm text-slate-500">
              <template v-if="plan === 'pro'">
                You have full access to all MedExplain features. Your Pro plan
                keeps the app running.
              </template>
              <template v-else>
                The Free plan includes core reports, trends, and the AI
                assistant. Upgrade for unlimited features and priority support.
              </template>
            </p>

            <p
              v-if="planMessage"
              class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
            >
              {{ planMessage }}
            </p>
            <p v-if="planError" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ planError }}
            </p>

            <button
              v-if="plan === 'free'"
              type="button"
              class="mt-4 w-full rounded-md bg-teal-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="planUpdating"
              @click="handleUpgrade"
            >
              {{ planUpdating ? 'Upgrading…' : 'Upgrade to Pro' }}
            </button>
            <button
              v-else
              type="button"
              class="mt-4 w-full rounded-md border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="planUpdating"
              @click="handleCancel"
            >
              {{ planUpdating ? 'Cancelling…' : 'Cancel subscription' }}
            </button>
          </div>

          <router-link
            :to="{ name: routeName('settings') }"
            class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-teal-400 hover:text-teal-700"
          >
            Manage account settings
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </router-link>
        </section>
      </div>
    </template>
  </div>
</template>
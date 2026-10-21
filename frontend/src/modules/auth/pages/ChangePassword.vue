<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/authStore'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()
const authStore = useAuthStore()

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Requirements evaluation
const hasMinLength = computed(() => newPassword.value.length >= 8)
const hasUppercase = computed(() => /[A-Z]/.test(newPassword.value))
const hasLowercase = computed(() => /[a-z]/.test(newPassword.value))
const hasNumber = computed(() => /[0-9]/.test(newPassword.value))
const hasSpecial = computed(() => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>/?`~]/.test(newPassword.value))
const passwordsMatch = computed(() => newPassword.value.length > 0 && newPassword.value === confirmPassword.value)

// Password score: 0 to 5
const strengthScore = computed(() => {
  let score = 0
  if (hasMinLength.value) score++
  if (hasUppercase.value) score++
  if (hasLowercase.value) score++
  if (hasNumber.value) score++
  if (hasSpecial.value) score++
  return score
})

const strengthLabel = computed(() => {
  if (!newPassword.value) return 'Not Entered'
  if (strengthScore.value <= 2) return 'Weak'
  if (strengthScore.value <= 4) return 'Moderate'
  return 'Strong'
})

const strengthColor = computed(() => {
  if (!newPassword.value) return 'bg-slate-200'
  if (strengthScore.value <= 2) return 'bg-rose-500'
  if (strengthScore.value <= 4) return 'bg-amber-500'
  return 'bg-emerald-500'
})

const strengthTextColor = computed(() => {
  if (!newPassword.value) return 'text-slate-400'
  if (strengthScore.value <= 2) return 'text-rose-600'
  if (strengthScore.value <= 4) return 'text-amber-600'
  return 'text-emerald-600'
})

const isFormValid = computed(() => {
  return (
    currentPassword.value.trim().length > 0 &&
    hasMinLength.value &&
    hasUppercase.value &&
    hasLowercase.value &&
    hasNumber.value &&
    hasSpecial.value &&
    passwordsMatch.value
  )
})

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

const submitChangePassword = async () => {
  errorMessage.value = null
  successMessage.value = null

  if (!isFormValid.value) {
    errorMessage.value = 'Please ensure all password requirements are satisfied before submitting.'
    return
  }

  isSubmitting.value = true

  try {
    const response = await apiClient.put('/user/change-password', {
      current_password: currentPassword.value,
      new_password: newPassword.value,
      new_password_confirmation: confirmPassword.value,
    })

    successMessage.value = response.data?.message || 'Password changed successfully! Redirecting...'

    // Update stored user object to reflect must_change_password = false
    if (authStore.user) {
      authStore.user.must_change_password = false
      localStorage.setItem('auth_user', JSON.stringify(authStore.user))
    }

    // Redirect after brief pause
    setTimeout(() => {
      const role = authStore.user?.role
      if (role === 'dept_head') {
        router.push('/dept-head/dashboard')
      } else if (role === 'admin') {
        router.push('/admin/dashboard')
      } else if (role === 'student') {
        router.push('/student')
      } else {
        router.push('/instructor/dashboard')
      }
    }, 1200)
  } catch (err: any) {
    const resp = err.response?.data
    if (resp?.errors) {
      const firstKey = Object.keys(resp.errors)[0]
      errorMessage.value = resp.errors[firstKey][0]
    } else {
      errorMessage.value = resp?.message || 'Failed to update password. Please verify your current temporary password.'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex flex-col justify-between text-slate-800">
    <!-- Header -->
    <header class="w-full px-6 sm:px-12 pt-4 pb-2 border-b border-slate-200/80 bg-white/80 backdrop-blur-md sticky top-0 z-20">
      <div class="max-w-6xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-white shadow-2xs border border-slate-200 p-0.5 flex items-center justify-center">
            <img src="../../../assets/images/logo.png" alt="Wollo University" class="w-full h-full object-contain rounded-full" />
          </div>
          <div>
            <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-none">
              Wollo <span class="text-[#5138ed]">University</span>
            </h1>
            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Online Examination &amp; Academic Management Portal</p>
          </div>
        </div>

        <button
          type="button"
          @click="handleLogout"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-rose-600 px-3 py-1.5 rounded-lg border border-slate-200 hover:border-rose-200 hover:bg-rose-50/50 transition-colors cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          <span>Sign Out</span>
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center px-4 py-8">
      <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 sm:p-8">
        <!-- Security Shield Icon & Title -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#5138ed] shadow-inner mb-3">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
          </div>
          <span class="inline-block px-3 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-amber-50 text-amber-700 border border-amber-200 mb-2">
            First Sign-In Security Requirement
          </span>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Create Your Permanent Password</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-1.5 max-w-md mx-auto">
            You signed in using a temporary password. To protect your exam materials and student records, you must choose a new private password before continuing.
          </p>
        </div>

        <!-- Alert Banners -->
        <div v-if="errorMessage" class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-2.5 text-xs text-rose-700">
          <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>{{ errorMessage }}</span>
        </div>

        <div v-if="successMessage" class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-2.5 text-xs text-emerald-700">
          <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="font-semibold">{{ successMessage }}</span>
        </div>

        <!-- Password Form -->
        <form @submit.prevent="submitChangePassword" class="space-y-4">
          <!-- Current / Temporary Password -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Current (Temporary) Password <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input
                :type="showCurrentPassword ? 'text' : 'password'"
                v-model="currentPassword"
                required
                autocomplete="current-password"
                placeholder="Enter the password received in your email"
                class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all bg-slate-50/50"
              />
              <button
                type="button"
                @click="showCurrentPassword = !showCurrentPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                tabindex="-1"
              >
                <svg v-if="showCurrentPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>

          <!-- New Password -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                New Password <span class="text-rose-500">*</span>
              </label>
              <span class="text-[11px] font-bold" :class="strengthTextColor">
                Strength: {{ strengthLabel }}
              </span>
            </div>
            <div class="relative">
              <input
                :type="showNewPassword ? 'text' : 'password'"
                v-model="newPassword"
                required
                autocomplete="new-password"
                placeholder="Choose a strong, unique password"
                class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all bg-slate-50/50"
              />
              <button
                type="button"
                @click="showNewPassword = !showNewPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                tabindex="-1"
              >
                <svg v-if="showNewPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>

            <!-- Strength Meter Progress Bar -->
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden flex gap-1">
              <div
                v-for="i in 5"
                :key="i"
                class="flex-1 h-full rounded-full transition-all duration-300"
                :class="i <= strengthScore ? strengthColor : 'bg-slate-200'"
              ></div>
            </div>
          </div>

          <!-- Confirm New Password -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              Confirm New Password <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input
                :type="showConfirmPassword ? 'text' : 'password'"
                v-model="confirmPassword"
                required
                autocomplete="new-password"
                placeholder="Re-enter your new password"
                class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all bg-slate-50/50"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                tabindex="-1"
              >
                <svg v-if="showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
            <p v-if="confirmPassword && !passwordsMatch" class="text-[11px] text-rose-600 mt-1">
              Passwords do not match.
            </p>
          </div>

          <!-- Password Policy Checklist -->
          <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 space-y-1.5 text-xs text-slate-600">
            <p class="font-bold text-[11px] uppercase tracking-wider text-slate-700 mb-1">Password Requirements:</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
              <div class="flex items-center gap-2" :class="hasMinLength ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="hasMinLength ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="hasMinLength"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>At least 8 characters</span>
              </div>

              <div class="flex items-center gap-2" :class="hasUppercase ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="hasUppercase ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="hasUppercase"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>Uppercase letter (A-Z)</span>
              </div>

              <div class="flex items-center gap-2" :class="hasLowercase ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="hasLowercase ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="hasLowercase"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>Lowercase letter (a-z)</span>
              </div>

              <div class="flex items-center gap-2" :class="hasNumber ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="hasNumber ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="hasNumber"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>Number (0-9)</span>
              </div>

              <div class="flex items-center gap-2" :class="hasSpecial ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="hasSpecial ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="hasSpecial"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>Special symbol (!@#$%^&*)</span>
              </div>

              <div class="flex items-center gap-2" :class="passwordsMatch ? 'text-emerald-700' : 'text-slate-500'">
                <svg class="w-3.5 h-3.5" :class="passwordsMatch ? 'text-emerald-600' : 'text-slate-300'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" v-if="passwordsMatch"/>
                  <circle cx="12" cy="12" r="9" stroke-width="2" v-else/>
                </svg>
                <span>Passwords match</span>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button
              type="submit"
              :disabled="!isFormValid || isSubmitting"
              class="w-full py-3 px-4 rounded-xl text-white font-bold text-sm shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              :class="isFormValid && !isSubmitting ? 'bg-[#5138ed] hover:bg-[#432dd4] active:scale-[0.99]' : 'bg-slate-400'"
            >
              <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isSubmitting ? 'Saving New Password...' : 'Save Password & Enter Portal' }}</span>
              <svg v-if="!isSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
          </div>
        </form>
      </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-4 text-center text-xs text-slate-400 border-t border-slate-200/60 bg-white">
      <p>&copy; {{ new Date().getFullYear() }} Wollo University. All rights reserved. • Examination Security Services</p>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useAuthStore } from '../../auth/store/authStore'

const authStore = useAuthStore()

// State
const isLoading = ref(true)
const isSaving = ref(false)
const saved = ref(false)

const profile = ref({
  id: null as number | null,
  fullName: '',
  email: '',
  phone: '',
  office: '',
  department: '',
  role: ''
})

const security = ref({
  currentPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const showPasswords = ref({
  current: false,
  new: false,
  confirm: false
})

const notifications = ref({
  emailOnInstructorJoin: true,
  emailOnCourseCreate: true,
  emailOnExamPublish: false,
  weeklyReport: true,
  emailConfig: {
    senderName: 'Wollo University Exam Portal',
    senderEmail: 'fitsumgashaw22@gmail.com',
    recipientEmail: 'fitsumgashaw22@gmail.com',
    smtpHost: 'smtp.gmail.com',
    smtpPort: '587',
    smtpEncryption: 'tls',
    smtpPassword: '••••••••••••••••',
    hasPassword: true
  }
})

const smtpStatus = ref<'connected' | 'failed' | 'testing' | 'unconfigured' | 'configured'>('connected')
const lastTestTime = ref<string | null>(null)
const lastTestResult = ref<string | null>(null)
const showSmtpPassword = ref(false)
const isSendingTest = ref(false)
const selectedTestType = ref('integration')
const testEmailStatus = ref<{ show: boolean; success: boolean; message: string; details?: string } | null>(null)

const formatRelativeTime = (isoStr: string | null) => {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr)
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

// Validation & Error states
const errors = ref<Record<string, string>>({})

// Toast notification
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>({
  show: false,
  message: '',
  type: 'success'
})

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4500)
}

// Fetch current user settings
const fetchSettings = async () => {
  try {
    isLoading.value = true
    errors.value = {}

    const res = await apiClient.get('/dept-head/settings')
    if (res.data?.status === 'success' && res.data?.data) {
      const data = res.data.data
      if (data.profile) {
        profile.value = {
          id: data.profile.id ?? null,
          fullName: data.profile.fullName || authStore.user?.name || '',
          email: data.profile.email || authStore.user?.email || '',
          phone: data.profile.phone || '+251980426395',
          office: data.profile.office || 'Block A, Room 204',
          department: data.profile.department || 'Computer Science',
          role: data.profile.role || 'dept_head'
        }
      }
      if (data.notifications) {
        smtpStatus.value = data.notifications.smtpStatus || 'connected'
        lastTestTime.value = data.notifications.lastTestTime || null
        lastTestResult.value = data.notifications.lastTestResult || null

        notifications.value = {
          emailOnInstructorJoin: !!data.notifications.emailOnInstructorJoin,
          emailOnCourseCreate: !!data.notifications.emailOnCourseCreate,
          emailOnExamPublish: !!data.notifications.emailOnExamPublish,
          weeklyReport: !!data.notifications.weeklyReport,
          emailConfig: {
            senderName: data.notifications.emailConfig?.senderName || 'Wollo University Exam Portal',
            senderEmail: data.notifications.emailConfig?.senderEmail || 'fitsumgashaw22@gmail.com',
            recipientEmail: data.notifications.emailConfig?.recipientEmail || data.profile?.email || 'fitsumgashaw22@gmail.com',
            smtpHost: data.notifications.emailConfig?.smtpHost || 'smtp.gmail.com',
            smtpPort: String(data.notifications.emailConfig?.smtpPort || '587'),
            smtpEncryption: data.notifications.emailConfig?.smtpEncryption || 'tls',
            smtpPassword: data.notifications.emailConfig?.smtpPassword || '••••••••••••••••',
            hasPassword: !!data.notifications.emailConfig?.hasPassword
          }
        }
      }
    }
  } catch (err: any) {
    console.error('Failed to load department head settings:', err)
    if (authStore.user) {
      profile.value.fullName = authStore.user.name || 'fitsum gashaw'
      profile.value.email = authStore.user.email || 'fitsumgashaw22@gmail.com'
      profile.value.phone = authStore.user.phone || '+251980426395'
      profile.value.office = authStore.user.office || 'Block A, Room 204'
    }
    showToast('Failed to load settings from server.', 'error')
  } finally {
    isLoading.value = false
  }
}

// Validate inputs
const validate = (): boolean => {
  errors.value = {}

  if (!profile.value.fullName.trim()) {
    errors.value.fullName = 'Full Name is required.'
  }

  // Password validation if attempting to change
  if (security.value.newPassword || security.value.confirmPassword || security.value.currentPassword) {
    if (!security.value.currentPassword) {
      errors.value.currentPassword = 'Enter your current password to set a new one.'
    }
    if (!security.value.newPassword) {
      errors.value.newPassword = 'New password is required.'
    } else if (security.value.newPassword.length < 6) {
      errors.value.newPassword = 'New password must be at least 6 characters.'
    }
    if (security.value.newPassword !== security.value.confirmPassword) {
      errors.value.confirmPassword = 'Passwords do not match.'
    }
  }

  return Object.keys(errors.value).length === 0
}

// Save Settings Handler
const saveSettings = async () => {
  if (!validate()) {
    showToast('Please fix the errors before saving.', 'error')
    return
  }

  try {
    isSaving.value = true
    errors.value = {}

    const payload: Record<string, any> = {
      fullName: profile.value.fullName.trim(),
      phone: profile.value.phone.trim(),
      office: profile.value.office.trim(),
      notifications: {
        emailOnInstructorJoin: notifications.value.emailOnInstructorJoin,
        emailOnCourseCreate: notifications.value.emailOnCourseCreate,
        emailOnExamPublish: notifications.value.emailOnExamPublish,
        weeklyReport: notifications.value.weeklyReport,
        emailConfig: {
          senderName: notifications.value.emailConfig.senderName?.trim() || 'Wollo University Exam Portal',
          senderEmail: notifications.value.emailConfig.senderEmail?.trim() || 'fitsumgashaw22@gmail.com',
          recipientEmail: notifications.value.emailConfig.recipientEmail?.trim() || 'fitsumgashaw22@gmail.com',
          smtpHost: notifications.value.emailConfig.smtpHost?.trim() || 'smtp.gmail.com',
          smtpPort: String(notifications.value.emailConfig.smtpPort || '587').trim(),
          smtpEncryption: notifications.value.emailConfig.smtpEncryption || 'tls',
          smtpPassword: notifications.value.emailConfig.smtpPassword?.trim() || ''
        }
      }
    }

    if (security.value.newPassword) {
      payload.currentPassword = security.value.currentPassword
      payload.newPassword = security.value.newPassword
      payload.confirmPassword = security.value.confirmPassword
    }

    const res = await apiClient.put('/dept-head/settings', payload)

    if (res.data?.status === 'success') {
      // Clear password fields
      security.value.currentPassword = ''
      security.value.newPassword = ''
      security.value.confirmPassword = ''

      // Sync authStore user name and profile
      if (authStore.user) {
        authStore.user.name = profile.value.fullName
        authStore.user.phone = profile.value.phone
        authStore.user.office = profile.value.office
        authStore.user.notification_preferences = payload.notifications
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }

      saved.value = true
      showToast(res.data.message || 'Notification preferences and settings saved successfully!', 'success')
      setTimeout(() => {
        saved.value = false
      }, 3000)
    }
  } catch (err: any) {
    console.error('Error saving settings:', err)
    if (err.response?.data?.errors) {
      const respErrors = err.response.data.errors
      for (const key of Object.keys(respErrors)) {
        errors.value[key] = Array.isArray(respErrors[key]) ? respErrors[key][0] : respErrors[key]
      }
      showToast(Object.values(errors.value)[0] || 'Validation error occurred.', 'error')
    } else {
      showToast(err.response?.data?.message || 'Failed to save settings. Please try again.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}

// Send Test Notification Email Handler
const sendTestEmail = async () => {
  try {
    isSendingTest.value = true
    testEmailStatus.value = null
    smtpStatus.value = 'testing'

    const payload = {
      emailConfig: {
        senderName: notifications.value.emailConfig.senderName?.trim() || 'Wollo University Exam Portal',
        senderEmail: notifications.value.emailConfig.senderEmail?.trim() || 'fitsumgashaw22@gmail.com',
        recipientEmail: notifications.value.emailConfig.recipientEmail?.trim() || 'fitsumgashaw22@gmail.com',
        smtpHost: notifications.value.emailConfig.smtpHost?.trim() || 'smtp.gmail.com',
        smtpPort: notifications.value.emailConfig.smtpPort || '587',
        smtpEncryption: notifications.value.emailConfig.smtpEncryption || 'tls',
        smtpPassword: notifications.value.emailConfig.smtpPassword?.trim() || ''
      },
      testType: selectedTestType.value
    }

    const res = await apiClient.post('/dept-head/settings/test-email', payload)

    if (res.data?.status === 'success') {
      const target = res.data.details?.recipient || payload.emailConfig.recipientEmail
      const verifiedTime = res.data.lastTestTime || new Date().toISOString()
      lastTestTime.value = verifiedTime
      smtpStatus.value = 'connected'

      testEmailStatus.value = {
        show: true,
        success: true,
        message: res.data.message || `Test notification email dispatched successfully to ${target}!`,
        details: `Connection verified at ${new Date(verifiedTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })} via smtp.gmail.com:587 (STARTTLS)`
      }
      showToast(testEmailStatus.value.message, 'success')
    }
  } catch (err: any) {
    console.error('Failed to send test email:', err)
    smtpStatus.value = 'failed'
    const errMessage = err.response?.data?.message || 'Failed to dispatch test notification email.'
    const errDetails = err.response?.data?.error_details || ''
    testEmailStatus.value = {
      show: true,
      success: false,
      message: errMessage,
      details: errDetails
    }
    showToast(errMessage, 'error')
  } finally {
    isSendingTest.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="max-w-[1500px] mx-auto space-y-6 pb-12 min-w-0 max-w-full font-sans">

    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-xl border text-[13px] font-bold transition-all transform animate-bounce-short max-w-[90vw]"
      :class="toast.type === 'success' ? 'bg-slate-900 text-white border-slate-700' : 'bg-rose-600 text-white border-rose-500'"
    >
      <svg v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <svg v-else class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <span class="break-words">{{ toast.message }}</span>
    </div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-[20px] sm:text-[22px] font-bold text-slate-800 tracking-tight">Account Settings</h1>
        <p class="text-[12px] sm:text-[13px] text-slate-500 mt-0.5">Manage your personal profile and notification preferences.</p>
      </div>

      <!-- Save Changes Button -->
      <button
        @click="saveSettings"
        :disabled="isSaving"
        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] text-[13px] font-bold rounded-xl transition-all shadow-sm active:scale-95 disabled:opacity-60 cursor-pointer w-full sm:w-auto"
        :class="saved
          ? 'bg-emerald-500 text-white shadow-emerald-200'
          : 'bg-[#5138ed] hover:bg-indigo-700 text-white shadow-indigo-200'"
      >
        <!-- Spinner while saving -->
        <svg v-if="isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <!-- Checkmark if saved -->
        <svg v-else-if="saved" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <!-- Floppy icon otherwise -->
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
        </svg>
        <span>{{ isSaving ? 'Saving...' : (saved ? 'Saved!' : 'Save Changes') }}</span>
      </button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6 h-64 animate-pulse flex items-center justify-center">
        <div class="w-8 h-8 border-3 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin"></div>
      </div>
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6 h-64 animate-pulse flex items-center justify-center">
        <div class="w-8 h-8 border-3 border-indigo-200 border-t-[#5138ed] rounded-full animate-spin"></div>
      </div>
    </div>

    <!-- Main Settings Cards Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- 1. Profile Information Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-9 h-9 bg-indigo-50 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Profile Information</h3>
            <p class="text-[11px] text-slate-400">Your personal details</p>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Full Name -->
          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Full Name</label>
            <input
              v-model="profile.fullName"
              type="text"
              placeholder="e.g. fitsum gashaw"
              class="w-full border rounded-xl px-4 py-2.5 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
              :class="errors.fullName ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200 bg-white'"
            />
            <p v-if="errors.fullName" class="text-rose-500 text-[11px] mt-1">{{ errors.fullName }}</p>
          </div>

          <!-- Email Address (Read-only) -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-[12px] font-bold text-slate-600">Email Address</label>
              <span class="text-[10px] text-slate-400 italic">Read-only account identifier</span>
            </div>
            <input
              v-model="profile.email"
              disabled
              type="email"
              class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-xl px-4 py-2.5 min-h-[44px] text-[13px] font-medium cursor-not-allowed focus:outline-none"
            />
          </div>

          <!-- Phone Number & Office -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Phone Number</label>
              <input
                v-model="profile.phone"
                type="text"
                placeholder="+251980426395"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
              />
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Office</label>
              <input
                v-model="profile.office"
                type="text"
                placeholder="Block A, Room 204"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Security Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center shrink-0">
            <svg class="w-4.5 h-4.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Security</h3>
            <p class="text-[11px] text-slate-400">Update your password</p>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Current Password -->
          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Current Password</label>
            <div class="relative">
              <input
                v-model="security.currentPassword"
                :type="showPasswords.current ? 'text' : 'password'"
                placeholder="••••••••"
                class="w-full border rounded-xl px-4 py-2.5 pr-11 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
                :class="errors.currentPassword ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200 bg-white'"
              />
              <button
                type="button"
                @click="showPasswords.current = !showPasswords.current"
                class="w-10 h-10 flex items-center justify-center absolute right-1 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer"
              >
                <svg v-if="showPasswords.current" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
            <p v-if="errors.currentPassword" class="text-rose-500 text-[11px] mt-1">{{ errors.currentPassword }}</p>
          </div>

          <!-- New Password -->
          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">New Password</label>
            <div class="relative">
              <input
                v-model="security.newPassword"
                :type="showPasswords.new ? 'text' : 'password'"
                placeholder="At least 6 characters"
                class="w-full border rounded-xl px-4 py-2.5 pr-11 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
                :class="errors.newPassword ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200 bg-white'"
              />
              <button
                type="button"
                @click="showPasswords.new = !showPasswords.new"
                class="w-10 h-10 flex items-center justify-center absolute right-1 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer"
              >
                <svg v-if="showPasswords.new" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
            <p v-if="errors.newPassword" class="text-rose-500 text-[11px] mt-1">{{ errors.newPassword }}</p>
          </div>

          <!-- Confirm New Password -->
          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Confirm New Password</label>
            <div class="relative">
              <input
                v-model="security.confirmPassword"
                :type="showPasswords.confirm ? 'text' : 'password'"
                placeholder="Repeat new password"
                class="w-full border rounded-xl px-4 py-2.5 pr-11 min-h-[44px] text-[13px] text-slate-800 font-medium transition-colors focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
                :class="errors.confirmPassword ? 'border-rose-400 bg-rose-50/20' : 'border-slate-200 bg-white'"
              />
              <button
                type="button"
                @click="showPasswords.confirm = !showPasswords.confirm"
                class="w-10 h-10 flex items-center justify-center absolute right-1 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer"
              >
                <svg v-if="showPasswords.confirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
            <p v-if="errors.confirmPassword" class="text-rose-500 text-[11px] mt-1">{{ errors.confirmPassword }}</p>
          </div>
        </div>
      </div>

      <!-- 3. Notification Preferences Card (Col-Span 2) -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6 col-span-1 lg:col-span-2">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
            </div>
            <div>
              <h3 class="text-[15px] font-bold text-slate-800">Notification Preferences</h3>
              <p class="text-[11px] sm:text-[12px] text-slate-400 mt-0.5">
                Choose which automated notifications are delivered directly to your Department Head email (<strong class="text-slate-600 font-semibold">{{ profile.email || 'your account email' }}</strong>).
              </p>
            </div>
          </div>
        </div>

        <!-- 3B. Notification Triggers (When should we email you?) -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <h4 class="text-[13px] font-bold text-slate-700">When should we email you?</h4>
            <span class="text-[11px] text-slate-400">(Toggle individual event triggers)</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-3 sm:gap-y-4">
            <!-- Toggle 1: New Instructor Joins -->
            <div class="flex items-center justify-between py-2.5 min-h-[44px] border-b border-slate-50 last:border-0 gap-3">
              <div class="flex flex-col">
                <span class="text-[12px] sm:text-[13px] font-semibold text-slate-700">Email me when a new instructor joins</span>
                <span class="text-[10px] text-slate-400">Receive alert with instructor details upon faculty addition</span>
              </div>
              <button
                type="button"
                @click="notifications.emailOnInstructorJoin = !notifications.emailOnInstructorJoin"
                :class="notifications.emailOnInstructorJoin ? 'bg-[#5138ed]' : 'bg-slate-200'"
                class="relative inline-flex w-11 h-6 rounded-full transition-colors duration-200 cursor-pointer focus:outline-none shrink-0"
              >
                <span
                  :class="notifications.emailOnInstructorJoin ? 'translate-x-5' : 'translate-x-0.5'"
                  class="inline-block w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform duration-200"
                ></span>
              </button>
            </div>

            <!-- Toggle 2: New Course Created -->
            <div class="flex items-center justify-between py-2.5 min-h-[44px] border-b border-slate-50 last:border-0 gap-3">
              <div class="flex flex-col">
                <span class="text-[12px] sm:text-[13px] font-semibold text-slate-700">Email me when a new course is created</span>
                <span class="text-[10px] text-slate-400">Receive notification when curriculum courses are added</span>
              </div>
              <button
                type="button"
                @click="notifications.emailOnCourseCreate = !notifications.emailOnCourseCreate"
                :class="notifications.emailOnCourseCreate ? 'bg-[#5138ed]' : 'bg-slate-200'"
                class="relative inline-flex w-11 h-6 rounded-full transition-colors duration-200 cursor-pointer focus:outline-none shrink-0"
              >
                <span
                  :class="notifications.emailOnCourseCreate ? 'translate-x-5' : 'translate-x-0.5'"
                  class="inline-block w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform duration-200"
                ></span>
              </button>
            </div>

            <!-- Toggle 3: Exam Published -->
            <div class="flex items-center justify-between py-2.5 min-h-[44px] border-b border-slate-50 last:border-0 gap-3">
              <div class="flex flex-col">
                <span class="text-[12px] sm:text-[13px] font-semibold text-slate-700">Email me when an exam is published</span>
                <span class="text-[10px] text-slate-400">Instant notification when instructor publishes an exam</span>
              </div>
              <button
                type="button"
                @click="notifications.emailOnExamPublish = !notifications.emailOnExamPublish"
                :class="notifications.emailOnExamPublish ? 'bg-[#5138ed]' : 'bg-slate-200'"
                class="relative inline-flex w-11 h-6 rounded-full transition-colors duration-200 cursor-pointer focus:outline-none shrink-0"
              >
                <span
                  :class="notifications.emailOnExamPublish ? 'translate-x-5' : 'translate-x-0.5'"
                  class="inline-block w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform duration-200"
                ></span>
              </button>
            </div>

            <!-- Toggle 4: Weekly Department Report -->
            <div class="flex items-center justify-between py-2.5 min-h-[44px] border-b border-slate-50 last:border-0 gap-3">
              <div class="flex flex-col">
                <span class="text-[12px] sm:text-[13px] font-semibold text-slate-700">Send me a weekly department report</span>
                <span class="text-[10px] text-slate-400">Weekly automated summary of submissions, courses & assessments</span>
              </div>
              <button
                type="button"
                @click="notifications.weeklyReport = !notifications.weeklyReport"
                :class="notifications.weeklyReport ? 'bg-[#5138ed]' : 'bg-slate-200'"
                class="relative inline-flex w-11 h-6 rounded-full transition-colors duration-200 cursor-pointer focus:outline-none shrink-0"
              >
                <span
                  :class="notifications.weeklyReport ? 'translate-x-5' : 'translate-x-0.5'"
                  class="inline-block w-5 h-5 bg-white rounded-full shadow mt-0.5 transition-transform duration-200"
                ></span>
              </button>
            </div>
          </div>
        
        <!-- Direct Email Delivery Information Banner -->
        <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
          <div>
            <h5 class="text-[12px] font-bold text-slate-700">Automated Direct Email Delivery</h5>
            <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
              Active notifications are automatically delivered directly to your registered Department Head email address:
              <strong class="font-semibold text-indigo-700">{{ profile.email || 'your account email' }}</strong>.
              Delivery authenticates securely through the university exam portal mail service.
            </p>
          </div>
        </div>
      </div>

      </div>

    </div>

  </div>
</template>

<style scoped>
@keyframes bounceShort {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
.animate-bounce-short {
  animation: bounceShort 0.4s ease;
}
</style>

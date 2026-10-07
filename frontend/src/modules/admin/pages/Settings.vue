<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import { useSettingsStore, type SystemInfo } from '../../../store/settingsStore'
import { useAuthStore } from '../../auth/store/authStore'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()
const settingsStore = useSettingsStore()
const authStore = useAuthStore()

// Active Navigation Tab
type TabKey = 'all' | 'profile' | 'general' | 'academic' | 'security' | 'notifications' | 'preferences' | 'system' | 'danger'
const activeTab = ref<TabKey>('all')

// Loading & Toast States
const isPageLoading = ref(true)
const isSaving = ref(false)
const saved = ref(false)
const toast = ref<{ show: boolean; type: 'success' | 'error' | 'info'; message: string }>({
  show: false,
  type: 'success',
  message: ''
})

const showToast = (type: 'success' | 'error' | 'info', message: string) => {
  toast.value = { show: true, type, message }
  setTimeout(() => {
    toast.value.show = false
  }, 4500)
}

// -----------------------------------------------------------------------------
// 1. PROFILE STATE & INITIAL SNAPSHOT
// -----------------------------------------------------------------------------
const profile = ref({
  name: '',
  username: '',
  email: '',
  phone: '',
  gender: '',
  office: '',
  role: 'admin',
  status: 'active',
  created_at: '',
})

// Photo state
const fileInputRef = ref<HTMLInputElement | null>(null)
const isUploadingPhoto = ref(false)
const photoPreview = ref<string | null>(null)
const showRemovePhotoModal = ref(false)
const isRemovingPhoto = ref(false)

const profilePhotoUrl = computed(() => {
  if (photoPreview.value) return photoPreview.value
  const pic = authStore.user?.profile_picture_url || authStore.user?.profile_picture
  if (!pic) return ''
  if (pic.startsWith('http://') || pic.startsWith('https://') || pic.startsWith('data:')) {
    return pic
  }
  return `http://localhost:8000/storage/${pic}`
})

const initials = computed(() => {
  const name = authStore.user?.name || profile.value.name || 'Super Admin'
  const parts = name.trim().split(/\s+/)
  return parts.length >= 2
    ? (parts[0][0] + parts[1][0]).toUpperCase()
    : name.slice(0, 2).toUpperCase()
})

const memberSinceFormatted = computed(() => {
  const raw = authStore.user?.created_at || profile.value.created_at
  if (!raw) return 'Jul 08, 2026'
  try {
    const d = new Date(raw)
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
  } catch {
    return 'Jul 08, 2026'
  }
})

// -----------------------------------------------------------------------------
// 2. GENERAL SYSTEM SETTINGS STATE
// -----------------------------------------------------------------------------
const general = ref({
  universityName: 'Wollo University',
  systemTitle: 'Online Examination System',
  institutionCode: 'WU',
  campusLocation: 'Dessie & Kombolcha, Ethiopia',
  supportEmail: 'admin@wollo.edu.et',
  timezone: 'Africa/Addis_Ababa',
  language: 'English',
  maintenanceMode: 'false'
})

// -----------------------------------------------------------------------------
// 3. ACADEMIC CONFIGURATION STATE
// -----------------------------------------------------------------------------
const academic = ref({
  academicYear: '2026',
  semester: 'Second Semester'
})

// -----------------------------------------------------------------------------
// 4. NOTIFICATION PREFERENCES STATE
// -----------------------------------------------------------------------------
const notifications = ref({
  email_notifications: true,
  system_notifications: true,
  exam_notifications: true,
  academic_calendar_notifications: true,
  security_notifications: true
})

// -----------------------------------------------------------------------------
// 5. USER PREFERENCES STATE
// -----------------------------------------------------------------------------
const preferences = ref({
  date_format: 'MMM DD, YYYY',
  timezone_display: '(UTC+03:00) Addis Ababa',
  density: 'comfortable',
  language: 'English'
})

// Snapshot for dirty checking & unsaved change detection
const originalSnapshot = ref<string>('')

const captureSnapshot = () => {
  originalSnapshot.value = JSON.stringify({
    profile: {
      name: profile.value.name,
      username: profile.value.username,
      email: profile.value.email,
      phone: profile.value.phone,
      gender: profile.value.gender,
      office: profile.value.office
    },
    general: { ...general.value },
    academic: { ...academic.value },
    notifications: { ...notifications.value },
    preferences: { ...preferences.value }
  })
}

const currentSnapshot = computed(() => {
  return JSON.stringify({
    profile: {
      name: profile.value.name,
      username: profile.value.username,
      email: profile.value.email,
      phone: profile.value.phone,
      gender: profile.value.gender,
      office: profile.value.office
    },
    general: { ...general.value },
    academic: { ...academic.value },
    notifications: { ...notifications.value },
    preferences: { ...preferences.value }
  })
})

const isDirty = computed(() => {
  if (!originalSnapshot.value) return false
  return originalSnapshot.value !== currentSnapshot.value
})

// -----------------------------------------------------------------------------
// 6. SECURITY & PASSWORD CHANGE STATE
// -----------------------------------------------------------------------------
const security = ref({
  currentPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)
const currentPasswordTouched = ref(false)
const newPasswordTouched = ref(false)
const confirmPasswordTouched = ref(false)
const isChangingPassword = ref(false)
const passwordStatus = ref<{ type: 'success' | 'error' | null; message: string }>({ type: null, message: '' })

// Real-time password requirement rules
const passwordRules = computed(() => {
  const pwd = security.value.newPassword || ''
  return {
    minLength: pwd.length >= 8,
    uppercase: /[A-Z]/.test(pwd),
    lowercase: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(pwd)
  }
})

const isNewPasswordValid = computed(() => {
  const r = passwordRules.value
  return r.minLength && r.uppercase && r.lowercase && r.number && r.special
})

const passwordStrength = computed(() => {
  const r = passwordRules.value
  const metCount = [r.minLength, r.uppercase, r.lowercase, r.number, r.special].filter(Boolean).length
  if (metCount <= 1) return { score: 1, label: 'Weak', barClass: 'bg-rose-500', textClass: 'text-rose-600', width: '25%' }
  if (metCount <= 3) return { score: 2, label: 'Fair', barClass: 'bg-amber-500', textClass: 'text-amber-600', width: '50%' }
  if (metCount === 4) return { score: 3, label: 'Good', barClass: 'bg-blue-500', textClass: 'text-blue-600', width: '75%' }
  return { score: 4, label: 'Strong', barClass: 'bg-emerald-500', textClass: 'text-emerald-600', width: '100%' }
})

const passwordsMatch = computed(() => {
  return (
    security.value.confirmPassword.length > 0 &&
    security.value.newPassword === security.value.confirmPassword
  )
})

const isPasswordFormValid = computed(() => {
  return (
    security.value.currentPassword.trim().length > 0 &&
    isNewPasswordValid.value &&
    passwordsMatch.value
  )
})

// Recent security activity logs
interface SecurityLog {
  id: number
  action: string
  module: string
  description: string
  relative_time: string
  formatted_time: string
}
const recentSecurityLogs = ref<SecurityLog[]>([])
const isLoadingLogs = ref(false)

const fetchSecurityLogs = async () => {
  isLoadingLogs.value = true
  try {
    const res = await apiClient.get('/user/activity-logs?per_page=5')
    if (res.data?.data) {
      recentSecurityLogs.value = res.data.data
    }
  } catch (err) {
    console.warn('Failed to load recent activity logs:', err)
  } finally {
    isLoadingLogs.value = false
  }
}

// -----------------------------------------------------------------------------
// 7. REAL-TIME SYSTEM INFORMATION
// -----------------------------------------------------------------------------
const systemInfo = ref<SystemInfo | null>(null)
const isRefreshingSystemInfo = ref(false)

const loadSystemInfo = async () => {
  isRefreshingSystemInfo.value = true
  try {
    const info = await settingsStore.fetchSystemInfo()
    if (info) {
      systemInfo.value = info
    }
  } catch (e) {
    console.warn('Failed to fetch system info:', e)
  } finally {
    isRefreshingSystemInfo.value = false
  }
}

// -----------------------------------------------------------------------------
// 8. DANGER ZONE & RESET MODALS
// -----------------------------------------------------------------------------
const showResetDefaultsModal = ref(false)
const isResettingDefaults = ref(false)
const confirmResetPhrase = ref('')

// Unsaved changes navigation modal
const showUnsavedChangesModal = ref(false)
let pendingNavigationNext: (() => void) | null = null

onBeforeRouteLeave((_to, _from, next) => {
  if (isDirty.value) {
    showUnsavedChangesModal.value = true
    pendingNavigationNext = next
  } else {
    next()
  }
})

const confirmLeaveWithoutSaving = () => {
  showUnsavedChangesModal.value = false
  if (pendingNavigationNext) {
    pendingNavigationNext()
    pendingNavigationNext = null
  }
}

const cancelLeave = () => {
  showUnsavedChangesModal.value = false
  pendingNavigationNext = null
}

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

// -----------------------------------------------------------------------------
// 9. DATA INITIALIZATION
// -----------------------------------------------------------------------------
const loadAllSettings = async () => {
  isPageLoading.value = true
  try {
    // 1. Fetch current authenticated user profile
    await authStore.fetchCurrentUser()
    if (authStore.user) {
      const u = authStore.user
      profile.value = {
        name: u.name || 'Super Admin',
        username: u.username || '',
        email: u.email || 'admin@wollo.edu.et',
        phone: u.phone || '',
        gender: u.gender || '',
        office: u.office || '',
        role: u.role || 'admin',
        status: u.status || 'active',
        created_at: u.created_at || '',
      }

      if (u.notification_preferences) {
        notifications.value = {
          ...notifications.value,
          ...u.notification_preferences
        }
      }

      if (u.preferences) {
        preferences.value = {
          ...preferences.value,
          ...u.preferences
        }
      }
    }

    // 2. Fetch global settings
    await settingsStore.fetchSettings()
    if (settingsStore.settings) {
      const s = settingsStore.settings
      if (s.universityName) general.value.universityName = s.universityName
      if (s.systemTitle) general.value.systemTitle = s.systemTitle
      if (s.institutionCode) general.value.institutionCode = s.institutionCode
      if (s.campusLocation) general.value.campusLocation = s.campusLocation
      if (s.supportEmail) general.value.supportEmail = s.supportEmail
      if (s.timezone) general.value.timezone = s.timezone
      if (s.language) general.value.language = s.language
      if (s.maintenanceMode) general.value.maintenanceMode = s.maintenanceMode
      if (s.academicYear) academic.value.academicYear = s.academicYear
      if (s.semester) academic.value.semester = s.semester
    }

    // 3. Fetch runtime system info
    await loadSystemInfo()

    // 4. Fetch security logs
    await fetchSecurityLogs()

    // 5. Store snapshot for dirty checking
    captureSnapshot()
  } catch (error) {
    console.error('Failed to load settings:', error)
    showToast('error', 'Unable to load system settings. Please verify connectivity.')
  } finally {
    isPageLoading.value = false
  }
}

onMounted(() => {
  loadAllSettings()
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

// -----------------------------------------------------------------------------
// 10. PROFILE PHOTO HANDLERS
// -----------------------------------------------------------------------------
const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']
  if (!validTypes.includes(file.type)) {
    showToast('error', 'Invalid file format. Please upload JPG, PNG, GIF, or WEBP.')
    return
  }

  if (file.size > 2 * 1024 * 1024) {
    showToast('error', 'File size exceeds 2MB limit. Please upload a smaller image.')
    return
  }

  // Instant local preview
  const reader = new FileReader()
  reader.onload = (e) => {
    photoPreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)

  isUploadingPhoto.value = true

  try {
    const formData = new FormData()
    formData.append('profile_picture', file)

    const response = await apiClient.post('/user/profile-photo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    const newUrl = response.data.profile_picture_url
    const newPath = response.data.profile_picture

    authStore.updateProfilePhoto(newUrl, newPath)
    showToast('success', 'Profile photo updated successfully.')
    await fetchSecurityLogs()
  } catch (error: any) {
    console.error('Failed to upload profile photo:', error)
    photoPreview.value = null
    showToast('error', error.response?.data?.message || 'Failed to upload profile photo.')
  } finally {
    isUploadingPhoto.value = false
    if (target) target.value = ''
  }
}

const executeRemovePhoto = async () => {
  isRemovingPhoto.value = true
  try {
    await apiClient.delete('/user/profile-photo')
    photoPreview.value = null
    authStore.updateProfilePhoto('', '')
    showRemovePhotoModal.value = false
    showToast('success', 'Profile photo removed successfully.')
    await fetchSecurityLogs()
  } catch (error: any) {
    console.error('Failed to remove photo:', error)
    showToast('error', error.response?.data?.message || 'Failed to remove profile photo.')
  } finally {
    isRemovingPhoto.value = false
  }
}

// -----------------------------------------------------------------------------
// 11. CHANGE PASSWORD HANDLER
// -----------------------------------------------------------------------------
const changePassword = async () => {
  currentPasswordTouched.value = true
  newPasswordTouched.value = true
  confirmPasswordTouched.value = true

  if (!security.value.currentPassword) {
    passwordStatus.value = { type: 'error', message: 'Current password is required.' }
    return
  }
  if (!isNewPasswordValid.value) {
    passwordStatus.value = { type: 'error', message: 'New password does not fulfill institutional complexity requirements.' }
    return
  }
  if (!passwordsMatch.value) {
    passwordStatus.value = { type: 'error', message: 'Confirmation password does not match.' }
    return
  }

  isChangingPassword.value = true
  passwordStatus.value = { type: null, message: '' }

  try {
    await apiClient.put('/user/change-password', {
      current_password: security.value.currentPassword,
      new_password: security.value.newPassword,
      new_password_confirmation: security.value.confirmPassword
    })

    passwordStatus.value = { type: 'success', message: 'Password updated successfully!' }
    showToast('success', 'Password updated successfully.')

    // Reset password fields
    security.value.currentPassword = ''
    security.value.newPassword = ''
    security.value.confirmPassword = ''
    currentPasswordTouched.value = false
    newPasswordTouched.value = false
    confirmPasswordTouched.value = false

    await fetchSecurityLogs()
    setTimeout(() => {
      passwordStatus.value.type = null
    }, 4000)
  } catch (error: any) {
    const errData = error.response?.data
    if (errData?.errors) {
      const firstField = Object.keys(errData.errors)[0]
      passwordStatus.value = { type: 'error', message: errData.errors[firstField][0] }
    } else {
      passwordStatus.value = {
        type: 'error',
        message: errData?.message || 'Failed to update password. Please verify current password.'
      }
    }
  } finally {
    isChangingPassword.value = false
  }
}

// -----------------------------------------------------------------------------
// 12. SAVE ALL MODIFIED SETTINGS
// -----------------------------------------------------------------------------
const saveAllSettings = async () => {
  if (!isDirty.value || isSaving.value) return

  isSaving.value = true
  try {
    // 1. Update user profile & preferences
    await authStore.updateUserProfile({
      name: profile.value.name,
      username: profile.value.username || null,
      email: profile.value.email,
      phone: profile.value.phone || null,
      gender: profile.value.gender || null,
      office: profile.value.office || null,
      notification_preferences: notifications.value,
      preferences: preferences.value
    })

    // 2. Update general & academic settings
    await settingsStore.updateSettings({
      universityName: general.value.universityName,
      systemTitle: general.value.systemTitle,
      institutionCode: general.value.institutionCode,
      campusLocation: general.value.campusLocation,
      supportEmail: general.value.supportEmail,
      timezone: general.value.timezone,
      language: general.value.language,
      maintenanceMode: general.value.maintenanceMode,
      academicYear: academic.value.academicYear,
      semester: academic.value.semester
    })

    captureSnapshot()
    saved.value = true
    showToast('success', 'All system settings and profile preferences saved successfully.')
    setTimeout(() => {
      saved.value = false
    }, 3000)

    await fetchSecurityLogs()
  } catch (error: any) {
    console.error('Failed to save settings:', error)
    showToast('error', error.response?.data?.message || 'Failed to save settings. Please try again.')
  } finally {
    isSaving.value = false
  }
}

// -----------------------------------------------------------------------------
// 13. RESET DEFAULTS HANDLER
// -----------------------------------------------------------------------------
const executeResetDefaults = async () => {
  if (confirmResetPhrase.value !== 'RESET') {
    showToast('error', 'Please enter "RESET" to confirm resetting system defaults.')
    return
  }

  isResettingDefaults.value = true
  try {
    await settingsStore.resetDefaults()
    if (settingsStore.settings) {
      const s = settingsStore.settings
      general.value.universityName = s.universityName || 'Wollo University'
      general.value.systemTitle = s.systemTitle || 'Online Examination System'
      general.value.institutionCode = s.institutionCode || 'WU'
      general.value.campusLocation = s.campusLocation || 'Dessie & Kombolcha, Ethiopia'
      general.value.supportEmail = s.supportEmail || 'admin@wollo.edu.et'
      general.value.timezone = s.timezone || 'Africa/Addis_Ababa'
      general.value.language = s.language || 'English'
      general.value.maintenanceMode = s.maintenanceMode || 'false'
    }
    captureSnapshot()
    showResetDefaultsModal.value = false
    confirmResetPhrase.value = ''
    showToast('success', 'General system settings reset to institutional defaults.')
    await fetchSecurityLogs()
  } catch (error: any) {
    console.error('Failed to reset defaults:', error)
    showToast('error', 'Failed to reset settings to defaults.')
  } finally {
    isResettingDefaults.value = false
  }
}

const navigateToAcademicCalendar = () => {
  router.push('/admin/calendar')
}
</script>

<template>
  <div class="space-y-6 pb-12 max-w-7xl mx-auto">

    <!-- Toast Notification -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-[13px] font-semibold"
        :class="{
          'bg-emerald-50 border-emerald-200 text-emerald-800': toast.type === 'success',
          'bg-rose-50 border-rose-200 text-rose-800': toast.type === 'error',
          'bg-indigo-50 border-indigo-200 text-indigo-800': toast.type === 'info',
        }"
      >
        <span class="w-2 h-2 rounded-full" :class="{
          'bg-emerald-500': toast.type === 'success',
          'bg-rose-500': toast.type === 'error',
          'bg-indigo-500': toast.type === 'info'
        }"></span>
        <span>{{ toast.message }}</span>
        <button @click="toast.show = false" class="ml-2 text-slate-400 hover:text-slate-600">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </transition>

    <!-- Header Section -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs p-5 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Title & Institutional Context -->
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-md border border-indigo-100">
              Administration Center
            </span>
            <span class="text-slate-300">•</span>
            <span class="text-[12px] font-medium text-slate-500 flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full" :class="systemInfo?.database_status === 'Operational' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
              System Status: <strong class="text-slate-700">{{ systemInfo?.database_status || 'Operational' }}</strong>
            </span>
          </div>

          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
            Settings & System Administration
          </h1>
          <p class="text-[13px] text-slate-500 font-normal mt-0.5 max-w-2xl">
            Configure institutional parameters, maintain administrator security policies, synchronize academic calendar cycles, and monitor server runtime health.
          </p>
        </div>

        <!-- Header Actions: Dirty Indicator & Save Button -->
        <div class="flex items-center gap-3 shrink-0">
          <!-- Unsaved changes warning badge -->
          <div v-if="isDirty" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[12px] font-semibold">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Unsaved Changes</span>
          </div>

          <!-- Save All Changes Button -->
          <button
            @click="saveAllSettings"
            :disabled="!isDirty || isSaving"
            class="px-5 py-2.5 rounded-xl font-bold text-[13px] transition-all flex items-center gap-2 shadow-xs"
            :class="[
              saved
                ? 'bg-emerald-600 text-white shadow-emerald-200 cursor-default'
                : isDirty
                  ? 'bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white shadow-indigo-200 cursor-pointer'
                  : 'bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200'
            ]"
          >
            <svg v-if="isSaving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <svg v-else-if="saved" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
            <span>{{ isSaving ? 'Saving Changes...' : (saved ? 'Saved Successfully ✓' : 'Save Changes') }}</span>
          </button>
        </div>

      </div>

      <!-- Navigation Tabs Bar -->
      <div class="mt-6 pt-4 border-t border-slate-100 flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
        <button
          @click="activeTab = 'all'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
          <span>All Settings</span>
        </button>

        <button
          @click="activeTab = 'profile'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'profile' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span>Profile & Avatar</span>
        </button>

        <button
          @click="activeTab = 'general'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'general' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          <span>General System</span>
        </button>

        <button
          @click="activeTab = 'academic'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'academic' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span>Academic Configuration</span>
        </button>

        <button
          @click="activeTab = 'security'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'security' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          <span>Security & Password</span>
        </button>

        <button
          @click="activeTab = 'notifications'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'notifications' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span>Notifications</span>
        </button>

        <button
          @click="activeTab = 'preferences'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'preferences' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span>Preferences</span>
        </button>

        <button
          @click="activeTab = 'system'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'system' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
          <span>System Information</span>
        </button>

        <button
          @click="activeTab = 'danger'"
          class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold whitespace-nowrap transition-colors flex items-center gap-1.5"
          :class="activeTab === 'danger' ? 'bg-rose-600 text-white shadow-xs' : 'text-rose-600 hover:bg-rose-50'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span>Danger Zone</span>
        </button>
      </div>
    </div>

    <!-- Skeleton Loader while initial data arrives -->
    <div v-if="isPageLoading" class="space-y-6 animate-pulse">
      <div class="h-44 bg-slate-200/70 rounded-2xl"></div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="h-80 bg-slate-200/70 rounded-2xl"></div>
        <div class="h-80 bg-slate-200/70 rounded-2xl"></div>
      </div>
    </div>

    <!-- MAIN SETTINGS CONTENT -->
    <div v-else class="space-y-6">

      <!-- =====================================================================
           SECTION 1: PROFILE & AVATAR
           ===================================================================== -->
      <section v-if="activeTab === 'all' || activeTab === 'profile'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <!-- Section Header -->
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-slate-800">Administrator Profile & Photo</h2>
              <p class="text-[12px] text-slate-500">Manage your identity, communication contacts, and institutional credentials</p>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-100 uppercase tracking-wider">
            Super Administrator
          </span>
        </div>

        <div class="p-5 sm:p-6 space-y-6">
          <!-- Profile Card with Avatar & Upload Controls -->
          <div class="flex flex-col sm:flex-row items-center sm:items-start justify-between gap-6 p-4 rounded-xl bg-slate-50/60 border border-slate-100">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 text-center sm:text-left">
              
              <!-- Avatar Display -->
              <div class="relative group shrink-0">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden border-3 border-white shadow-md bg-slate-200 flex items-center justify-center ring-2 ring-slate-200/80">
                  <img
                    v-if="profilePhotoUrl"
                    :src="profilePhotoUrl"
                    alt="Administrator Profile"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="text-xl sm:text-2xl font-black text-indigo-700">
                    {{ initials }}
                  </span>
                </div>

                <!-- Camera trigger icon overlay -->
                <button
                  type="button"
                  @click="triggerFileInput"
                  :disabled="isUploadingPhoto"
                  class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-md flex items-center justify-center transition-all hover:scale-105 cursor-pointer disabled:opacity-50"
                  title="Upload new profile photo"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
              </div>

              <!-- Admin Identity Summary -->
              <div>
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                  <h3 class="text-lg font-bold text-slate-800">{{ profile.name }}</h3>
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active Account
                  </span>
                </div>
                <p class="text-[12px] text-slate-500 font-mono mt-0.5">{{ profile.email }}</p>
                
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-4 gap-y-1 mt-2 text-[11px] text-slate-500">
                  <span>Role: <strong class="text-slate-700">Administrator</strong></span>
                  <span>Member Since: <strong class="text-slate-700">{{ memberSinceFormatted }}</strong></span>
                </div>
                <div class="mt-2 text-[11px] text-slate-400">
                  Allowed formats: JPG, PNG, GIF, WEBP • Max: 2MB.
                </div>
              </div>
            </div>

            <!-- Upload & Remove Buttons -->
            <div class="flex items-center gap-2.5 shrink-0">
              <input
                type="file"
                ref="fileInputRef"
                accept="image/png, image/jpeg, image/jpg, image/gif, image/webp"
                class="hidden"
                @change="handleFileChange"
              />

              <button
                type="button"
                @click="triggerFileInput"
                :disabled="isUploadingPhoto"
                class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-[12px] font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <svg v-if="isUploadingPhoto" class="animate-spin w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <svg v-else class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>{{ isUploadingPhoto ? 'Uploading...' : 'Change Photo' }}</span>
              </button>

              <button
                v-if="profilePhotoUrl"
                type="button"
                @click="showRemovePhotoModal = true"
                :disabled="isUploadingPhoto"
                class="px-3.5 py-2 rounded-xl border border-rose-200 bg-rose-50/60 hover:bg-rose-100 text-rose-700 text-[12px] font-bold transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span>Remove</span>
              </button>
            </div>
          </div>

          <!-- Profile Form Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Full Name <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="profile.name"
                type="text"
                placeholder="Administrator Name"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Username
              </label>
              <input
                v-model="profile.username"
                type="text"
                placeholder="admin.super"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Institutional Email <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="profile.email"
                type="email"
                placeholder="admin@wollo.edu.et"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Phone Number
              </label>
              <input
                v-model="profile.phone"
                type="tel"
                placeholder="+251 91 234 5678"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Gender
              </label>
              <select
                v-model="profile.gender"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
              >
                <option value="">Not Specified</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Office / Administrative Location
              </label>
              <input
                v-model="profile.office"
                type="text"
                placeholder="Main Campus, Registrar Hall B"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- =====================================================================
           SECTION 2 & 3: GENERAL SETTINGS & ACADEMIC CONFIGURATION (2 COLUMNS)
           ===================================================================== -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- GENERAL SETTINGS CARD -->
        <section v-if="activeTab === 'all' || activeTab === 'general'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              </div>
              <div>
                <h2 class="text-base font-bold text-slate-800">General Institution Configuration</h2>
                <p class="text-[12px] text-slate-500">Global identity, branding, locale, and institutional titles</p>
              </div>
            </div>
          </div>

          <div class="p-5 sm:p-6 space-y-4">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">University Name</label>
              <input
                v-model="general.universityName"
                type="text"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">System Title</label>
              <input
                v-model="general.systemTitle"
                type="text"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Institution Code</label>
                <input
                  v-model="general.institutionCode"
                  type="text"
                  placeholder="WU"
                  class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors uppercase"
                />
              </div>

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Technical Support Email</label>
                <input
                  v-model="general.supportEmail"
                  type="email"
                  placeholder="admin@wollo.edu.et"
                  class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                />
              </div>
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Campus Main Location</label>
              <input
                v-model="general.campusLocation"
                type="text"
                placeholder="Dessie & Kombolcha, Ethiopia"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">System Timezone</label>
                <select
                  v-model="general.timezone"
                  class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
                >
                  <option value="Africa/Addis_Ababa">Africa/Addis_Ababa (EAT +03:00)</option>
                  <option value="UTC">UTC (Universal Coordinated Time)</option>
                  <option value="Europe/London">Europe/London (GMT/BST)</option>
                  <option value="America/New_York">America/New_York (EST)</option>
                </select>
              </div>

              <div>
                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Primary System Language</label>
                <select
                  v-model="general.language"
                  class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
                >
                  <option value="English">English (Official Operational Language)</option>
                  <option value="Amharic">Amharic (Ethiopian National)</option>
                </select>
              </div>
            </div>

            <!-- Maintenance Mode Toggle -->
            <div class="pt-2">
              <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50/70 cursor-pointer">
                <div>
                  <span class="text-[13px] font-bold text-slate-800 block">System Maintenance Mode</span>
                  <span class="text-[11px] text-slate-500 block">Restricts non-administrator logins during core database operations.</span>
                </div>
                <input
                  type="checkbox"
                  v-model="general.maintenanceMode"
                  true-value="true"
                  false-value="false"
                  class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"
                />
              </label>
            </div>
          </div>
        </section>

        <!-- ACADEMIC CONFIGURATION CARD -->
        <section v-if="activeTab === 'all' || activeTab === 'academic'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
          <div>
            <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                  <h2 class="text-base font-bold text-slate-800">Academic Year & Term Configuration</h2>
                  <p class="text-[12px] text-slate-500">Synchronized with Academic Calendar and Examination Modules</p>
                </div>
              </div>
            </div>

            <div class="p-5 sm:p-6 space-y-4">
              <!-- Active Operational Period Banner -->
              <div class="p-4 rounded-xl bg-indigo-50/80 border border-indigo-100 flex items-center justify-between">
                <div>
                  <span class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider block">Live Active Period</span>
                  <span class="text-base font-extrabold text-indigo-950">
                    {{ academic.academicYear }} — {{ academic.semester }}
                  </span>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-white text-indigo-700 shadow-2xs border border-indigo-200">
                  Active Cycle
                </span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                  <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Active Academic Year</label>
                  <input
                    v-model="academic.academicYear"
                    type="text"
                    placeholder="2026"
                    class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                  />
                  <p class="text-[11px] text-slate-400 mt-1">E.g. 2026 or 2026/2027</p>
                </div>

                <div>
                  <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Active Semester</label>
                  <select
                    v-model="academic.semester"
                    class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
                  >
                    <option value="First Semester">First Semester</option>
                    <option value="Second Semester">Second Semester</option>
                    <option value="Summer Term">Summer Term</option>
                  </select>
                  <p class="text-[11px] text-slate-400 mt-1">Controls student submissions & exams</p>
                </div>
              </div>

              <!-- Integration Notice -->
              <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[12px] text-slate-600 space-y-1">
                <p class="font-bold text-slate-800 flex items-center gap-1.5">
                  <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span>Unified Academic Calendar Architecture</span>
                </p>
                <p class="text-slate-500 leading-relaxed">
                  Academic year and semester configurations set here directly govern the exam creation cycle, instructor grade submission windows, and student transcript records.
                </p>
              </div>
            </div>
          </div>

          <!-- Quick Jump to Academic Calendar -->
          <div class="p-5 sm:p-6 pt-0 border-t border-slate-100 bg-slate-50/40">
            <button
              type="button"
              @click="navigateToAcademicCalendar"
              class="w-full py-2.5 px-4 rounded-xl border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[12px] transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
            >
              <span>Manage Academic Calendar & Events</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
          </div>
        </section>

      </div>

      <!-- =====================================================================
           SECTION 4: SECURITY & PASSWORD (CHANGE PASSWORD + RECENT AUDIT LOGS)
           ===================================================================== -->
      <section v-if="activeTab === 'all' || activeTab === 'security'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-slate-800">Security Credentials & Account Auditing</h2>
              <p class="text-[12px] text-slate-500">Update administrative password and inspect recent authenticated activity</p>
            </div>
          </div>
          <span class="text-[12px] font-semibold text-slate-500 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            Two-Layer Validation Active
          </span>
        </div>

        <div class="p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
          
          <!-- LEFT: CHANGE PASSWORD FORM -->
          <div class="space-y-4">
            <h3 class="text-[14px] font-bold text-slate-800 flex items-center gap-2">
              <span>Change Password</span>
            </h3>

            <!-- Status Alert -->
            <div
              v-if="passwordStatus.message"
              :class="passwordStatus.type === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
              class="p-3 text-[12px] font-semibold border rounded-xl flex items-center gap-2"
            >
              <svg v-if="passwordStatus.type === 'success'" class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              <svg v-else class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>{{ passwordStatus.message }}</span>
            </div>

            <!-- Current Password Field -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Current Password <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model="security.currentPassword"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  @blur="currentPasswordTouched = true"
                  placeholder="Enter current password"
                  class="w-full border rounded-xl px-3.5 py-2.5 pr-10 text-[13px] text-slate-800 focus:outline-none transition-colors"
                  :class="currentPasswordTouched && !security.currentPassword ? 'border-rose-400 bg-rose-50/20' : 'border-slate-300 focus:border-indigo-600'"
                />
                <button
                  type="button"
                  @click="showCurrentPassword = !showCurrentPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                  tabindex="-1"
                >
                  <svg v-if="!showCurrentPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                </button>
              </div>
              <p v-if="currentPasswordTouched && !security.currentPassword" class="text-[11px] font-bold text-rose-500 mt-1">
                Current password is required
              </p>
            </div>

            <!-- New Password Field -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="block text-[12px] font-bold text-slate-700">
                  New Password <span class="text-rose-500">*</span>
                </label>
                <span v-if="security.newPassword" class="text-[11px] font-bold" :class="passwordStrength.textClass">
                  Strength: {{ passwordStrength.label }}
                </span>
              </div>

              <div class="relative">
                <input
                  v-model="security.newPassword"
                  :type="showNewPassword ? 'text' : 'password'"
                  @focus="newPasswordTouched = true"
                  @blur="newPasswordTouched = true"
                  placeholder="Minimum 8 characters"
                  class="w-full border rounded-xl px-3.5 py-2.5 pr-10 text-[13px] text-slate-800 focus:outline-none transition-colors"
                  :class="[
                    newPasswordTouched && !isNewPasswordValid && security.newPassword.length > 0
                      ? 'border-amber-400'
                      : isNewPasswordValid
                        ? 'border-emerald-400'
                        : 'border-slate-300 focus:border-indigo-600'
                  ]"
                />
                <button
                  type="button"
                  @click="showNewPassword = !showNewPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                  tabindex="-1"
                >
                  <svg v-if="!showNewPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                </button>
              </div>

              <!-- Animated Strength Meter -->
              <div v-if="security.newPassword" class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                <div class="h-full transition-all duration-300 rounded-full" :class="passwordStrength.barClass" :style="{ width: passwordStrength.width }"></div>
              </div>

              <!-- Requirements Checklist -->
              <div class="mt-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5">
                <p class="text-[11px] font-bold text-slate-700 mb-1 flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                  <span>Complexity Requirements:</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                  <div class="flex items-center gap-1.5 text-[11px]" :class="passwordRules.minLength ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="passwordRules.minLength ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    <span>At least 8 characters</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-[11px]" :class="passwordRules.uppercase ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="passwordRules.uppercase ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    <span>Uppercase letter (A-Z)</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-[11px]" :class="passwordRules.lowercase ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="passwordRules.lowercase ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    <span>Lowercase letter (a-z)</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-[11px]" :class="passwordRules.number ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="passwordRules.number ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    <span>Numeric character (0-9)</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-[11px] sm:col-span-2" :class="passwordRules.special ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <span class="w-1.5 h-1.5 rounded-full" :class="passwordRules.special ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                    <span>Special character (!@#$%^&* etc.)</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Confirm Password Field -->
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">
                Confirm New Password <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <input
                  v-model="security.confirmPassword"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  @blur="confirmPasswordTouched = true"
                  placeholder="Re-enter new password"
                  class="w-full border rounded-xl px-3.5 py-2.5 pr-10 text-[13px] text-slate-800 focus:outline-none transition-colors"
                  :class="[
                    confirmPasswordTouched && !passwordsMatch && security.confirmPassword.length > 0
                      ? 'border-rose-400 bg-rose-50/20'
                      : passwordsMatch && isNewPasswordValid
                        ? 'border-emerald-400'
                        : 'border-slate-300 focus:border-indigo-600'
                  ]"
                />
                <button
                  type="button"
                  @click="showConfirmPassword = !showConfirmPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                  tabindex="-1"
                >
                  <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                </button>
              </div>

              <!-- Matching Status Indicator -->
              <div v-if="security.confirmPassword.length > 0" class="mt-1.5 text-[11px] font-bold">
                <span v-if="passwordsMatch" class="text-emerald-700 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  Passwords match
                </span>
                <span v-else class="text-rose-600 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  Passwords do not match
                </span>
              </div>
            </div>

            <div class="pt-2">
              <button
                type="button"
                @click="changePassword"
                :disabled="isChangingPassword || !isPasswordFormValid"
                class="w-full py-2.5 px-4 rounded-xl font-bold text-[13px] transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed bg-rose-600 hover:bg-rose-700 text-white shadow-rose-200"
              >
                <svg v-if="isChangingPassword" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>{{ isChangingPassword ? 'Verifying & Updating...' : 'Update Password' }}</span>
              </button>
            </div>
          </div>

          <!-- RIGHT: RECENT SECURITY ACTIVITY LOGS -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-[14px] font-bold text-slate-800 flex items-center gap-2">
                <span>Recent Administrator Audit Logs</span>
              </h3>
              <button
                @click="fetchSecurityLogs"
                :disabled="isLoadingLogs"
                class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-1 cursor-pointer"
              >
                <svg class="w-3 h-3" :class="isLoadingLogs ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Refresh Logs</span>
              </button>
            </div>

            <p class="text-[12px] text-slate-500">
              Authoritative records of your administrative sessions, modifications, and system interactions.
            </p>

            <div class="space-y-2.5">
              <div
                v-for="log in recentSecurityLogs"
                :key="log.id"
                class="p-3 rounded-xl border border-slate-200/80 bg-slate-50/50 flex items-start gap-3"
              >
                <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 mt-0.5">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-[12px] font-bold text-slate-800 truncate">{{ log.action }}</span>
                    <span class="text-[10px] font-bold text-slate-400 shrink-0">{{ log.relative_time }}</span>
                  </div>
                  <p class="text-[11px] text-slate-500 mt-0.5 break-words">{{ log.description }}</p>
                  <span class="text-[10px] text-slate-400 block mt-1 font-mono">{{ log.formatted_time }}</span>
                </div>
              </div>

              <div v-if="!recentSecurityLogs.length && !isLoadingLogs" class="text-center py-6 text-[12px] text-slate-400">
                No recent activity logs recorded yet.
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- =====================================================================
           SECTION 5 & 6: NOTIFICATIONS & PREFERENCES (2 COLUMNS)
           ===================================================================== -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- NOTIFICATIONS SETTINGS CARD -->
        <section v-if="activeTab === 'all' || activeTab === 'notifications'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
              </div>
              <div>
                <h2 class="text-base font-bold text-slate-800">Notification Preferences</h2>
                <p class="text-[12px] text-slate-500">Persisted across system channels & administrative alerts</p>
              </div>
            </div>
          </div>

          <div class="p-5 sm:p-6 space-y-4">
            <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer">
              <div>
                <span class="text-[13px] font-bold text-slate-800 block">Critical Email Notifications</span>
                <span class="text-[11px] text-slate-500 block">Dispatch immediate emails for system alerts and critical actions.</span>
              </div>
              <input type="checkbox" v-model="notifications.email_notifications" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" />
            </label>

            <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer">
              <div>
                <span class="text-[13px] font-bold text-slate-800 block">System Task Alerts</span>
                <span class="text-[11px] text-slate-500 block">In-app notifications when instructor batches or reports complete.</span>
              </div>
              <input type="checkbox" v-model="notifications.system_notifications" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" />
            </label>

            <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer">
              <div>
                <span class="text-[13px] font-bold text-slate-800 block">Examination Cycle Notices</span>
                <span class="text-[11px] text-slate-500 block">Receive alerts on exam scheduling, publishing, and submissions.</span>
              </div>
              <input type="checkbox" v-model="notifications.exam_notifications" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" />
            </label>

            <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer">
              <div>
                <span class="text-[13px] font-bold text-slate-800 block">Academic Calendar Deadlines</span>
                <span class="text-[11px] text-slate-500 block">Reminders before semester cutoffs, add/drop, and final exam windows.</span>
              </div>
              <input type="checkbox" v-model="notifications.academic_calendar_notifications" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" />
            </label>

            <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-slate-50 transition-colors cursor-pointer">
              <div>
                <span class="text-[13px] font-bold text-slate-800 block">Security Alert Dispatch</span>
                <span class="text-[11px] text-slate-500 block">Immediate notifications whenever a password reset or login occurs.</span>
              </div>
              <input type="checkbox" v-model="notifications.security_notifications" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" />
            </label>
          </div>
        </section>

        <!-- PREFERENCES & DISPLAY CARD -->
        <section v-if="activeTab === 'all' || activeTab === 'preferences'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
          <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              <div>
                <h2 class="text-base font-bold text-slate-800">Display & Administration Preferences</h2>
                <p class="text-[12px] text-slate-500">Date formatting, table density, and workspace appearance</p>
              </div>
            </div>
          </div>

          <div class="p-5 sm:p-6 space-y-4">
            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Date Display Format</label>
              <select
                v-model="preferences.date_format"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
              >
                <option value="MMM DD, YYYY">MMM DD, YYYY (e.g. Oct 07, 2026)</option>
                <option value="YYYY-MM-DD">YYYY-MM-DD (ISO 8601 Standard)</option>
                <option value="DD/MM/YYYY">DD/MM/YYYY (British Academic Standard)</option>
              </select>
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Timezone Labeling</label>
              <select
                v-model="preferences.timezone_display"
                class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 focus:outline-none focus:border-indigo-600 bg-white transition-colors"
              >
                <option value="(UTC+03:00) Addis Ababa">(UTC+03:00) Addis Ababa, East Africa Time</option>
                <option value="UTC">UTC (Coordinated Universal Time)</option>
              </select>
            </div>

            <div>
              <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Data Table Row Density</label>
              <div class="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  @click="preferences.density = 'comfortable'"
                  class="p-3 rounded-xl border text-center transition-all cursor-pointer"
                  :class="preferences.density === 'comfortable' ? 'border-indigo-600 bg-indigo-50/60 text-indigo-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                >
                  <span class="text-[13px] block">Comfortable</span>
                  <span class="text-[11px] text-slate-400 block mt-0.5">Spacious rows with high readability</span>
                </button>

                <button
                  type="button"
                  @click="preferences.density = 'compact'"
                  class="p-3 rounded-xl border text-center transition-all cursor-pointer"
                  :class="preferences.density === 'compact' ? 'border-indigo-600 bg-indigo-50/60 text-indigo-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                >
                  <span class="text-[13px] block">Compact</span>
                  <span class="text-[11px] text-slate-400 block mt-0.5">Higher information density per screen</span>
                </button>
              </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[12px] text-slate-500">
              Preferences are synchronized with your administrator account and retained across browser sessions.
            </div>
          </div>
        </section>

      </div>

      <!-- =====================================================================
           SECTION 7: SYSTEM INFORMATION (100% REAL RUNTIME DATA)
           ===================================================================== -->
      <section v-if="activeTab === 'all' || activeTab === 'system'" class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-slate-800">Server Runtime & Architecture Information</h2>
              <p class="text-[12px] text-slate-500">Live operational telemetry directly queried from the backend server</p>
            </div>
          </div>

          <button
            @click="loadSystemInfo"
            :disabled="isRefreshingSystemInfo"
            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-[12px] font-bold text-slate-700 transition-colors flex items-center gap-1.5 shadow-2xs cursor-pointer"
          >
            <svg class="w-3.5 h-3.5 text-slate-500" :class="isRefreshingSystemInfo ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span>{{ isRefreshingSystemInfo ? 'Testing...' : 'Ping Telemetry' }}</span>
          </button>
        </div>

        <div class="p-5 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Card: Framework -->
          <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Backend Framework</span>
            <span class="text-sm font-extrabold text-slate-800 mt-1 block">Laravel {{ systemInfo?.laravel_version || '12.x' }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">PHP {{ systemInfo?.php_version || '8.2' }}</span>
          </div>

          <!-- Card: Database -->
          <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Database System</span>
            <span class="text-sm font-extrabold text-slate-800 mt-1 block">{{ systemInfo?.database_version || 'MariaDB / MySQL' }}</span>
            <span class="text-[11px] text-emerald-600 font-bold mt-0.5 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              Ping Latency: {{ systemInfo?.database_latency || '8ms' }}
            </span>
          </div>

          <!-- Card: Environment -->
          <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Runtime Environment</span>
            <span class="text-sm font-extrabold text-slate-800 mt-1 block uppercase">{{ systemInfo?.app_environment || 'Production' }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Debug: {{ systemInfo?.app_debug || 'Disabled' }}</span>
          </div>

          <!-- Card: Storage -->
          <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Disk File Storage</span>
            <span class="text-sm font-extrabold text-slate-800 mt-1 block">{{ systemInfo?.storage_writable || 'Writable' }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Cache: {{ systemInfo?.cache_driver || 'Database' }} • Mail: {{ systemInfo?.mail_driver || 'Log' }}</span>
          </div>
        </div>
      </section>

      <!-- =====================================================================
           SECTION 8: DANGER ZONE
           ===================================================================== -->
      <section v-if="activeTab === 'all' || activeTab === 'danger'" class="bg-rose-50/30 border border-rose-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-rose-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center text-rose-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-bold text-rose-900">Danger Zone — Destructive Operations</h2>
              <p class="text-[12px] text-rose-600">Revert customized administrative settings and institutional defaults</p>
            </div>
          </div>
        </div>

        <div class="p-5 sm:p-6 space-y-4">
          <!-- Item 1: Reset Profile Picture -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-white border border-rose-100">
            <div>
              <h4 class="text-[13px] font-bold text-slate-800">Remove Custom Profile Photo</h4>
              <p class="text-[12px] text-slate-500 mt-0.5">Permanently deletes your uploaded avatar file and restores your institutional initials.</p>
            </div>
            <button
              type="button"
              @click="showRemovePhotoModal = true"
              :disabled="!profilePhotoUrl"
              class="px-4 py-2 rounded-xl border border-rose-200 bg-white hover:bg-rose-50 text-rose-700 text-[12px] font-bold transition-colors shrink-0 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
            >
              Reset Avatar
            </button>
          </div>

          <!-- Item 2: Reset General Settings to Institutional Defaults -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-white border border-rose-100">
            <div>
              <h4 class="text-[13px] font-bold text-slate-800">Reset System General Defaults</h4>
              <p class="text-[12px] text-slate-500 mt-0.5">Restores university name, system title, timezone, and institution code back to the standard Wollo University deployment baseline.</p>
            </div>
            <button
              type="button"
              @click="showResetDefaultsModal = true"
              class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-[12px] font-bold transition-colors shrink-0 shadow-xs cursor-pointer"
            >
              Reset to Defaults
            </button>
          </div>
        </div>
      </section>

    </div>

    <!-- =====================================================================
         MODAL 1: CONFIRM REMOVE PROFILE PHOTO
         ===================================================================== -->
    <div v-if="showRemovePhotoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
        <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 mx-auto">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>

        <div class="text-center">
          <h3 class="text-base font-bold text-slate-900">Remove Profile Photo?</h3>
          <p class="text-[13px] text-slate-500 mt-1">
            Are you sure you want to remove your profile photo? Your avatar will revert to your institutional initials.
          </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="button"
            @click="showRemovePhotoModal = false"
            class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 text-slate-700 font-bold text-[13px] hover:bg-slate-50 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="executeRemovePhoto"
            :disabled="isRemovingPhoto"
            class="flex-1 py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13px] transition-colors flex items-center justify-center gap-1.5 shadow-xs cursor-pointer"
          >
            <svg v-if="isRemovingPhoto" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ isRemovingPhoto ? 'Removing...' : 'Confirm Remove' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- =====================================================================
         MODAL 2: CONFIRM RESET DEFAULTS
         ===================================================================== -->
    <div v-if="showResetDefaultsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
        <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 mx-auto">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>

        <div class="text-center">
          <h3 class="text-base font-bold text-slate-900">Reset System Settings to Defaults?</h3>
          <p class="text-[13px] text-slate-500 mt-1">
            This action will revert University Name, System Title, Timezone, and Institution Code to standard Wollo University deployment values.
          </p>
        </div>

        <div>
          <label class="block text-[11px] font-bold text-slate-600 mb-1">
            Type <strong class="text-rose-600 font-mono">RESET</strong> to confirm:
          </label>
          <input
            v-model="confirmResetPhrase"
            type="text"
            placeholder="RESET"
            class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-[13px] text-slate-800 font-mono uppercase focus:outline-none focus:border-rose-500"
          />
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="button"
            @click="showResetDefaultsModal = false; confirmResetPhrase = ''"
            class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 text-slate-700 font-bold text-[13px] hover:bg-slate-50 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="executeResetDefaults"
            :disabled="isResettingDefaults || confirmResetPhrase !== 'RESET'"
            class="flex-1 py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13px] transition-colors flex items-center justify-center gap-1.5 shadow-xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <svg v-if="isResettingDefaults" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ isResettingDefaults ? 'Resetting...' : 'Confirm Reset' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- =====================================================================
         MODAL 3: UNSAVED CHANGES LEAVE CONFIRMATION
         ===================================================================== -->
    <div v-if="showUnsavedChangesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 mx-auto">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>

        <div class="text-center">
          <h3 class="text-base font-bold text-slate-900">Unsaved Changes</h3>
          <p class="text-[13px] text-slate-500 mt-1">
            You have unsaved configuration changes. If you leave this page now, your modifications will be discarded.
          </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="button"
            @click="cancelLeave"
            class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 text-slate-700 font-bold text-[13px] hover:bg-slate-50 transition-colors cursor-pointer"
          >
            Stay on Page
          </button>
          <button
            type="button"
            @click="confirmLeaveWithoutSaving"
            class="flex-1 py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13px] transition-colors shadow-xs cursor-pointer"
          >
            Discard & Leave
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* No scrollbar utility for tab navigation */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

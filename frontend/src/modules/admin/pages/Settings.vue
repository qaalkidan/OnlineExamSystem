<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useSettingsStore } from '../../../store/settingsStore'
import { useAuthStore } from '../../auth/store/authStore'
import apiClient from '../../../core/api/apiClient'

const settingsStore = useSettingsStore()
const authStore = useAuthStore()
const saved = ref(false)

onMounted(() => {
  authStore.fetchCurrentUser()
})

// Profile Photo States
const fileInputRef = ref<HTMLInputElement | null>(null)
const isUploadingPhoto = ref(false)
const photoPreview = ref<string | null>(null)
const photoStatus = ref<{ type: 'success' | 'error' | null; message: string }>({ type: null, message: '' })

const profilePhotoUrl = computed(() => {
  if (photoPreview.value) return photoPreview.value
  const pic = authStore.user?.profile_picture_url || authStore.user?.profile_picture
  if (!pic) return 'https://i.pravatar.cc/150?u=admin123'
  if (pic.startsWith('http://') || pic.startsWith('https://') || pic.startsWith('data:')) {
    return pic
  }
  return `http://localhost:8000/storage/${pic}`
})

const hasCustomPhoto = computed(() => {
  return !!(authStore.user?.profile_picture || photoPreview.value)
})

const initials = computed(() => {
  const name = authStore.user?.name || 'Super Admin'
  const parts = name.trim().split(/\s+/)
  return parts.length >= 2
    ? (parts[0][0] + parts[1][0]).toUpperCase()
    : name.slice(0, 2).toUpperCase()
})

const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  // Validate format
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']
  if (!validTypes.includes(file.type)) {
    photoStatus.value = { type: 'error', message: 'Please select a valid image file (JPG, PNG, GIF, or WEBP).' }
    return
  }

  // Validate size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    photoStatus.value = { type: 'error', message: 'Image size must be less than 2MB.' }
    return
  }

  // Show local preview immediately
  const reader = new FileReader()
  reader.onload = (e) => {
    photoPreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)

  isUploadingPhoto.value = true
  photoStatus.value = { type: null, message: '' }

  try {
    const formData = new FormData()
    formData.append('profile_picture', file)

    const response = await apiClient.post('/user/profile-photo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    const newUrl = response.data.profile_picture_url
    const newPath = response.data.profile_picture

    authStore.updateProfilePhoto(newUrl, newPath)
    photoStatus.value = { type: 'success', message: 'Profile photo updated successfully!' }
    setTimeout(() => {
      photoStatus.value.type = null
      photoStatus.value.message = ''
    }, 4000)
  } catch (error: any) {
    console.error('Failed to upload profile photo:', error)
    photoPreview.value = null
    photoStatus.value = {
      type: 'error',
      message: error.response?.data?.message || 'Failed to upload profile photo. Please try again.',
    }
  } finally {
    isUploadingPhoto.value = false
    if (target) target.value = ''
  }
}

const handleRemovePhoto = async () => {
  if (!confirm('Are you sure you want to remove your profile photo?')) return

  isUploadingPhoto.value = true
  photoStatus.value = { type: null, message: '' }

  try {
    await apiClient.delete('/user/profile-photo')
    photoPreview.value = null
    authStore.updateProfilePhoto('', '')
    photoStatus.value = { type: 'success', message: 'Profile photo removed successfully.' }
    setTimeout(() => {
      photoStatus.value.type = null
      photoStatus.value.message = ''
    }, 4000)
  } catch (error: any) {
    console.error('Failed to remove profile photo:', error)
    photoStatus.value = {
      type: 'error',
      message: error.response?.data?.message || 'Failed to remove profile photo.',
    }
  } finally {
    isUploadingPhoto.value = false
  }
}

// General Settings
const general = ref({
  universityName: 'Wollo University',
  systemTitle:    'Online Examination System',
  timezone:       'Africa/Addis_Ababa',
  language:       'English',
  academicYear:   settingsStore.academicYear,
  semester:       settingsStore.semester,
})

// Sync form with store when store loads
watch(
  () => [settingsStore.academicYear, settingsStore.semester],
  ([newYear, newSem]) => {
    general.value.academicYear = newYear
    general.value.semester = newSem
  },
  { immediate: true }
)

// Security / Change Password Settings
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

const passwordStatus = ref<{ type: 'success' | 'error' | null; message: string }>({ type: null, message: '' })
const isChangingPassword = ref(false)

// Real-time password requirement rules
const passwordRules = computed(() => {
  const pwd = security.value.newPassword || ''
  return {
    minLength: pwd.length >= 8,
    uppercase: /[A-Z]/.test(pwd),
    lowercase: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(pwd),
  }
})

const isNewPasswordValid = computed(() => {
  const r = passwordRules.value
  return r.minLength && r.uppercase && r.lowercase && r.number && r.special
})

const passwordStrength = computed(() => {
  const r = passwordRules.value
  const metCount = [r.minLength, r.uppercase, r.lowercase, r.number, r.special].filter(Boolean).length
  if (metCount <= 1) return { score: 1, label: 'Weak', barClass: 'bg-rose-500', textClass: 'text-rose-500', width: '25%' }
  if (metCount <= 3) return { score: 2, label: 'Medium', barClass: 'bg-amber-500', textClass: 'text-amber-500', width: '50%' }
  if (metCount === 4) return { score: 3, label: 'Good', barClass: 'bg-blue-500', textClass: 'text-blue-500', width: '75%' }
  return { score: 4, label: 'Strong', barClass: 'bg-emerald-500', textClass: 'text-emerald-500', width: '100%' }
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

const changePassword = async () => {
  currentPasswordTouched.value = true
  newPasswordTouched.value = true
  confirmPasswordTouched.value = true

  if (!security.value.currentPassword) {
    passwordStatus.value = { type: 'error', message: 'Please enter your current password.' }
    return
  }
  if (!passwordRules.value.minLength) {
    passwordStatus.value = { type: 'error', message: 'New password must be at least 8 characters long.' }
    return
  }
  if (!passwordRules.value.uppercase || !passwordRules.value.lowercase) {
    passwordStatus.value = { type: 'error', message: 'New password must contain both uppercase and lowercase letters.' }
    return
  }
  if (!passwordRules.value.number) {
    passwordStatus.value = { type: 'error', message: 'New password must contain at least one number (0-9).' }
    return
  }
  if (!passwordRules.value.special) {
    passwordStatus.value = { type: 'error', message: 'New password must contain at least one special character (!@#$%^&*).' }
    return
  }
  if (security.value.newPassword !== security.value.confirmPassword) {
    passwordStatus.value = { type: 'error', message: 'Confirm password does not match new password.' }
    return
  }

  isChangingPassword.value = true
  passwordStatus.value = { type: null, message: '' }

  try {
    await apiClient.put('/user/change-password', {
      current_password:          security.value.currentPassword,
      new_password:              security.value.newPassword,
      new_password_confirmation: security.value.confirmPassword,
    })

    passwordStatus.value = { type: 'success', message: 'Password changed successfully!' }
    security.value.currentPassword = ''
    security.value.newPassword = ''
    security.value.confirmPassword = ''
    currentPasswordTouched.value = false
    newPasswordTouched.value = false
    confirmPasswordTouched.value = false
    setTimeout(() => { passwordStatus.value.type = null }, 4000)

  } catch (error: any) {
    const errData = error.response?.data
    if (errData?.errors) {
      const firstField = Object.keys(errData.errors)[0]
      passwordStatus.value = { type: 'error', message: errData.errors[firstField][0] }
    } else {
      passwordStatus.value = {
        type: 'error',
        message: errData?.message || 'Failed to change password. Please try again.',
      }
    }
  } finally {
    isChangingPassword.value = false
  }
}

const saveSettings = async () => {
  try {
    await settingsStore.updateTerm(general.value.academicYear, general.value.semester)
    saved.value = true
    setTimeout(() => { saved.value = false }, 3000)
  } catch (error) {
    alert('Failed to save settings. Please try again.')
  }
}
</script>

<template>
  <div class="space-y-6">

    <!-- Top Admin Profile & Photo Card -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
      <div class="flex flex-col sm:flex-row items-center sm:items-start justify-between gap-4 sm:gap-6">

        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 text-center sm:text-left">
          <!-- Profile Avatar with Camera Overlay -->
          <div class="relative group shrink-0">
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden border-4 border-slate-50 shadow-md bg-slate-100 flex items-center justify-center ring-2 ring-slate-100">
              <img
                v-if="profilePhotoUrl"
                :src="profilePhotoUrl"
                alt="Admin Profile"
                class="w-full h-full object-cover"
              />
              <span v-else class="text-xl sm:text-2xl font-black text-slate-500">
                {{ initials }}
              </span>
            </div>

            <!-- Camera button overlay -->
            <button
              type="button"
              @click="triggerFileInput"
              class="absolute bottom-0 right-0 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#5138ed] hover:bg-indigo-700 text-white shadow-md flex items-center justify-center transition-all hover:scale-110"
              title="Change Profile Photo"
            >
              <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
              </svg>
            </button>
          </div>

          <!-- User Info & Guidelines -->
          <div class="min-w-0">
            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
              <h2 class="text-base sm:text-lg font-bold text-slate-800">{{ authStore.user?.name || 'Super Admin' }}</h2>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-600 border border-rose-100">
                Administrator
              </span>
            </div>
            <p class="text-[12px] sm:text-[13px] text-slate-500 font-medium mt-0.5 break-all">{{ authStore.user?.email || 'admin@wollo.edu.et' }}</p>
            <div class="flex items-center justify-center sm:justify-start gap-1.5 mt-2 text-[11px] text-slate-400">
              <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span>Allowed formats: JPG, PNG, GIF, WEBP. Max: 2MB.</span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2.5 sm:gap-3 w-full sm:w-auto">
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
            class="flex-1 sm:flex-none justify-center px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-[13px] font-bold transition-all shadow-sm flex items-center gap-2 disabled:opacity-50"
          >
            <svg v-if="isUploadingPhoto" class="animate-spin w-4 h-4 text-[#5138ed]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <svg v-else class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            {{ isUploadingPhoto ? 'Uploading...' : 'Change Photo' }}
          </button>

          <button
            v-if="hasCustomPhoto"
            type="button"
            @click="handleRemovePhoto"
            :disabled="isUploadingPhoto"
            class="px-3.5 py-2.5 rounded-xl border border-rose-100 bg-rose-50/60 hover:bg-rose-100 text-rose-600 text-[13px] font-bold transition-all flex items-center gap-1.5 disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Remove
          </button>
        </div>

      </div>

      <!-- Photo Status Message -->
      <div v-if="photoStatus.message" :class="photoStatus.type === 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100'" class="mt-4 p-3 text-[12px] font-semibold border rounded-xl flex items-center gap-2">
        <svg v-if="photoStatus.type === 'success'" class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <svg v-else class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>{{ photoStatus.message }}</span>
      </div>
    </div>

    <!-- Page Actions -->
    <div class="flex items-center justify-end">
      <button @click="saveSettings" :disabled="settingsStore.isLoading" class="w-full sm:w-auto justify-center flex items-center gap-2 px-5 py-2.5 text-[13px] font-bold rounded-xl transition-all shadow-sm disabled:opacity-50"
        :class="saved ? 'bg-emerald-500 text-white shadow-emerald-200' : 'bg-[#5138ed] hover:bg-indigo-700 text-white shadow-indigo-200'">
        <svg v-if="settingsStore.isLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <svg v-else-if="saved" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
        {{ settingsStore.isLoading ? 'Saving...' : (saved ? 'Saved!' : 'Save Changes') }}
      </button>
    </div>

    <!-- 2 Columns Grid: General Settings & Change Password -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">

      <!-- General Settings -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-9 h-9 bg-indigo-50 rounded-xl flex items-center justify-center">
            <svg class="w-4.5 h-4.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <div><h3 class="text-[14px] font-bold text-slate-800">General Settings</h3><p class="text-[11px] text-slate-400">Institution and system info</p></div>
        </div>
        <div class="space-y-4">
          <div><label class="block text-[12px] font-bold text-slate-600 mb-1.5">University Name</label><input v-model="general.universityName" readonly class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"></div>
          <div><label class="block text-[12px] font-bold text-slate-600 mb-1.5">System Title</label><input v-model="general.systemTitle" readonly class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none"></div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><label class="block text-[12px] font-bold text-slate-600 mb-1.5">Timezone</label>
              <input v-model="general.timezone" readonly class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none">
            </div>
            <div>
              <label class="block text-[12px] font-bold text-slate-600 mb-1.5">Language</label>
              <!-- English only as requested -->
              <input v-model="general.language" readonly class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] bg-slate-50 text-slate-500 cursor-not-allowed focus:outline-none font-medium">
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><label class="block text-[12px] font-bold text-slate-600 mb-1.5">Academic Year</label><input v-model="general.academicYear" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"></div>
            <div><label class="block text-[12px] font-bold text-slate-600 mb-1.5">Semester</label>
              <select v-model="general.semester" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] focus:outline-none focus:border-[#5138ed] bg-white">
                <option>First Semester</option><option>Second Semester</option><option>Summer</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Security / Change Password Settings -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center">
            <svg class="w-4.5 h-4.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
          </div>
          <div><h3 class="text-[14px] font-bold text-slate-800">Change Password</h3><p class="text-[11px] text-slate-400">Update your account password</p></div>
        </div>
        <div class="space-y-4">
          
          <div v-if="passwordStatus.type" :class="passwordStatus.type === 'success' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100'" class="p-3 text-[12px] font-medium border rounded-xl flex items-center gap-2">
            <svg v-if="passwordStatus.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ passwordStatus.message }}
          </div>

          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">
              Current Password <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input 
                v-model="security.currentPassword" 
                :type="showCurrentPassword ? 'text' : 'password'" 
                @blur="currentPasswordTouched = true"
                placeholder="Enter your current password" 
                :class="[
                  'w-full border rounded-xl px-4 py-2.5 pr-10 text-[13px] focus:outline-none transition-colors',
                  currentPasswordTouched && !security.currentPassword
                    ? 'border-rose-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 bg-rose-50/20'
                    : 'border-slate-200 focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]'
                ]"
              >
              <button 
                type="button" 
                @click="showCurrentPassword = !showCurrentPassword" 
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1 cursor-pointer"
                tabindex="-1"
                title="Toggle password visibility"
              >
                <svg v-if="!showCurrentPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
              </button>
            </div>
            <p v-if="currentPasswordTouched && !security.currentPassword" class="text-[11px] font-bold text-rose-500 mt-1">
              Current password is required
            </p>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-[12px] font-bold text-slate-600">
                New Password <span class="text-rose-500">*</span>
              </label>
              <!-- Strength badge if user has typed something -->
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
                placeholder="Minimum 8 characters with Aa, 1, #" 
                :class="[
                  'w-full border rounded-xl px-4 py-2.5 pr-10 text-[13px] focus:outline-none transition-colors',
                  newPasswordTouched && !isNewPasswordValid && security.newPassword.length > 0
                    ? 'border-amber-300 focus:border-amber-500 focus:ring-1 focus:ring-amber-500'
                    : isNewPasswordValid
                      ? 'border-emerald-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500'
                      : 'border-slate-200 focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]'
                ]"
              >
              <button 
                type="button" 
                @click="showNewPassword = !showNewPassword" 
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1 cursor-pointer"
                tabindex="-1"
                title="Toggle password visibility"
              >
                <svg v-if="!showNewPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
              </button>
            </div>

            <!-- Password Strength Bar (when user types) -->
            <div v-if="security.newPassword" class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
              <div class="h-full transition-all duration-300 rounded-full" :class="passwordStrength.barClass" :style="{ width: passwordStrength.width }"></div>
            </div>

            <!-- Real-time Password Requirements Checklist -->
            <div class="mt-2.5 p-3 rounded-xl bg-slate-50 border border-slate-100 space-y-1.5">
              <p class="text-[11px] font-bold text-slate-600 mb-1 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Password Requirements:
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                <!-- Minimum 8 chars -->
                <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="passwordRules.minLength ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="passwordRules.minLength" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                  </svg>
                  <span>At least 8 characters</span>
                </div>

                <!-- Uppercase -->
                <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="passwordRules.uppercase ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="passwordRules.uppercase" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                  </svg>
                  <span>One uppercase letter (A-Z)</span>
                </div>

                <!-- Lowercase -->
                <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="passwordRules.lowercase ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="passwordRules.lowercase" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                  </svg>
                  <span>One lowercase letter (a-z)</span>
                </div>

                <!-- Number -->
                <div class="flex items-center gap-1.5 text-[11px] transition-colors" :class="passwordRules.number ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="passwordRules.number" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                  </svg>
                  <span>One number (0-9)</span>
                </div>

                <!-- Special Character -->
                <div class="flex items-center gap-1.5 text-[11px] transition-colors sm:col-span-2" :class="passwordRules.special ? 'text-emerald-600 font-bold' : 'text-slate-400 font-medium'">
                  <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="passwordRules.special" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    <circle v-else cx="12" cy="12" r="8" stroke-width="1.5"/>
                  </svg>
                  <span>One special symbol (!@#$%^&amp;* etc.)</span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-[12px] font-bold text-slate-600 mb-1.5">
              Confirm New Password <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input 
                v-model="security.confirmPassword" 
                :type="showConfirmPassword ? 'text' : 'password'" 
                @blur="confirmPasswordTouched = true"
                placeholder="Re-enter new password" 
                :class="[
                  'w-full border rounded-xl px-4 py-2.5 pr-10 text-[13px] focus:outline-none transition-colors',
                  confirmPasswordTouched && !passwordsMatch && security.confirmPassword.length > 0
                    ? 'border-rose-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 bg-rose-50/20'
                    : passwordsMatch && isNewPasswordValid
                      ? 'border-emerald-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500'
                      : 'border-slate-200 focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]'
                ]"
              >
              <button 
                type="button" 
                @click="showConfirmPassword = !showConfirmPassword" 
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1 cursor-pointer"
                tabindex="-1"
                title="Toggle password visibility"
              >
                <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
              </button>
            </div>

            <!-- Matching indicator -->
            <div v-if="security.confirmPassword.length > 0" class="mt-1.5 flex items-center gap-1.5 text-[11px] font-bold">
              <span v-if="passwordsMatch" class="text-emerald-600 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                Passwords match
              </span>
              <span v-else class="text-rose-500 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Passwords do not match
              </span>
            </div>
          </div>

          <div class="pt-2">
            <button @click="changePassword" :disabled="isChangingPassword || !isPasswordFormValid" 
              class="w-full flex justify-center items-center gap-2 px-5 py-2.5 text-[13px] font-bold rounded-xl transition-all shadow-sm bg-[#5138ed] hover:bg-indigo-700 text-white shadow-indigo-200 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer active:scale-98">
              <svg v-if="isChangingPassword" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              {{ isChangingPassword ? 'Updating Password...' : 'Update Password' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Scoped styles */
</style>

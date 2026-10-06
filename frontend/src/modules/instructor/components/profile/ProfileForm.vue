<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useProfileStore, type InstructorProfile } from '../../store/profileStore'

const profileStore = useProfileStore()

// ─── 1. Personal Information Form State ───
const form = ref({
  name: '',
  email: '',
  phone: '',
  office: '',
  gender: 'Male',
  address: ''
})

const formErrors = ref<Record<string, string>>({})

// Sync form with profileStore data when loaded
watch(
  () => profileStore.profile,
  (newProfile) => {
    if (newProfile) {
      form.value = {
        name: newProfile.name || '',
        email: newProfile.email || '',
        phone: newProfile.phone || '',
        office: newProfile.office || '',
        gender: newProfile.gender || 'Male',
        address: newProfile.location || newProfile.office || 'Dessie, Wollo, Ethiopia'
      }
    }
  },
  { immediate: true }
)

const handleSaveProfile = async () => {
  formErrors.value = {}
  
  if (!form.value.name.trim()) {
    formErrors.value.name = 'Full name is required'
    return
  }
  if (!form.value.email.trim()) {
    formErrors.value.email = 'Email address is required'
    return
  }

  try {
    await profileStore.updateProfile({
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone,
      office: form.value.office,
      gender: form.value.gender,
      location: form.value.address,
      address: form.value.address
    })
  } catch (err: any) {
    if (err.response?.data?.errors) {
      const errs = err.response.data.errors
      Object.keys(errs).forEach((key) => {
        formErrors.value[key] = Array.isArray(errs[key]) ? errs[key][0] : errs[key]
      })
    }
  }
}

// ─── 2. Password Change Form State ───
const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)
const passwordError = ref('')
const passwordSuccess = ref('')

// Password validation checks
const hasMinLength = computed(() => passwordForm.value.new_password.length >= 8)
const hasUpperCase = computed(() => /[A-Z]/.test(passwordForm.value.new_password))
const hasLowerCase = computed(() => /[a-z]/.test(passwordForm.value.new_password))
const hasNumber = computed(() => /[0-9]/.test(passwordForm.value.new_password))
const hasSpecialChar = computed(() => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(passwordForm.value.new_password))
const passwordsMatch = computed(
  () =>
    passwordForm.value.new_password.length > 0 &&
    passwordForm.value.new_password === passwordForm.value.new_password_confirmation
)

const isPasswordFormValid = computed(
  () =>
    passwordForm.value.current_password.length > 0 &&
    hasMinLength.value &&
    hasUpperCase.value &&
    hasLowerCase.value &&
    hasNumber.value &&
    hasSpecialChar.value &&
    passwordsMatch.value
)

const handleChangePassword = async () => {
  passwordError.value = ''
  passwordSuccess.value = ''

  if (!isPasswordFormValid.value) {
    passwordError.value = 'Please satisfy all password security requirements before saving.'
    return
  }

  try {
    await profileStore.changePassword({
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
      new_password_confirmation: passwordForm.value.new_password_confirmation
    })
    passwordSuccess.value = 'Your password has been changed successfully.'
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: ''
    }
  } catch (err: any) {
    const resp = err.response?.data
    if (resp?.errors) {
      const first = Object.keys(resp.errors)[0]
      passwordError.value = resp.errors[first][0]
    } else {
      passwordError.value = resp?.message || 'Failed to update password.'
    }
  }
}

// ─── 3. Notification Preferences State ───
const toggleNotification = (key: keyof InstructorProfile['notification_preferences']) => {
  if (!profileStore.profile) return
  const currentVal = profileStore.profile.notification_preferences[key]
  profileStore.updateNotificationPreferences({
    [key]: !currentVal
  })
}
</script>

<template>
  <div id="profile-form-section" class="transition-all duration-300">
    
    <!-- ========================================== -->
    <!-- TAB 1: PERSONAL INFORMATION               -->
    <!-- ========================================== -->
    <div v-if="profileStore.activeTab === 'personal'" class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-[15px] font-bold text-slate-800">Personal Information</h2>
          <p class="text-[11px] text-slate-500 mt-0.5">Update your personal contact details and academic office information.</p>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
        
        <!-- Full Name -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Full Name</label>
          <input 
            v-model="form.name"
            type="text" 
            placeholder="e.g. Fitsum Gashaw"
            class="w-full px-4 py-2.5 text-[13px] border rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
            :class="formErrors.name ? 'border-rose-400' : 'border-slate-200'"
          />
          <p v-if="formErrors.name" class="text-[10px] text-rose-500 font-semibold">{{ formErrors.name }}</p>
        </div>

        <!-- Department (Read-only) -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-[11px] font-bold text-slate-700">Department</label>
            <span class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Assigned by Admin
            </span>
          </div>
          <input 
            type="text" 
            :value="profileStore.profile?.department_name || profileStore.profile?.department?.name || 'Computer Science'"
            readonly
            disabled
            class="w-full px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed capitalize font-medium select-none"
          />
        </div>

        <!-- Email Address -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Email Address</label>
          <input 
            v-model="form.email"
            type="email" 
            placeholder="e.g. instructor@wu.edu.et"
            class="w-full px-4 py-2.5 text-[13px] border rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
            :class="formErrors.email ? 'border-rose-400' : 'border-slate-200'"
          />
          <p v-if="formErrors.email" class="text-[10px] text-rose-500 font-semibold">{{ formErrors.email }}</p>
        </div>

        <!-- Position (Read-only) -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-[11px] font-bold text-slate-700">Position / Academic Role</label>
            <span class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              System Role
            </span>
          </div>
          <input 
            type="text" 
            :value="profileStore.profile?.role === 'dept_head' ? 'Department Head' : (profileStore.profile?.role ? profileStore.profile.role.charAt(0).toUpperCase() + profileStore.profile.role.slice(1) : 'Instructor')"
            readonly
            disabled
            class="w-full px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl bg-slate-50 text-slate-500 cursor-not-allowed capitalize font-medium select-none"
          />
        </div>

        <!-- Phone Number -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Phone Number</label>
          <input 
            v-model="form.phone"
            type="tel" 
            placeholder="+251 9X XXX XXXX"
            class="w-full px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
          />
        </div>

        <!-- Office Location -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Office Location</label>
          <input 
            v-model="form.office"
            type="text" 
            placeholder="e.g. CS Building, Room 205"
            class="w-full px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
          />
        </div>

        <!-- Gender -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Gender</label>
          <div class="relative">
            <select 
              v-model="form.gender"
              class="w-full appearance-none px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white text-slate-800 cursor-pointer"
            >
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>

        <!-- Address -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Address / City</label>
          <input 
            v-model="form.address"
            type="text" 
            placeholder="e.g. Dessie, Wollo, Ethiopia"
            class="w-full px-4 py-2.5 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
          />
        </div>

      </div>

      <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-[11px] text-slate-400">All modifications are securely recorded in your audit logs.</p>
        <button 
          @click="handleSaveProfile"
          type="button"
          :disabled="profileStore.isSaving"
          class="px-6 py-2.5 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-sm cursor-pointer disabled:opacity-60 flex items-center gap-2"
        >
          <svg v-if="profileStore.isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
          <span>{{ profileStore.isSaving ? 'Saving Changes...' : 'Save Changes' }}</span>
        </button>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: SECURITY                           -->
    <!-- ========================================== -->
    <div v-else-if="profileStore.activeTab === 'security'" class="space-y-6">
      <div>
        <h2 class="text-[15px] font-bold text-slate-800">Security & Authentication</h2>
        <p class="text-[11px] text-slate-500 mt-0.5">Manage your authentication credentials, session security, and access controls.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        
        <!-- Account Status Card -->
        <div class="p-5 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              </div>
              <div>
                <h3 class="text-[13px] font-bold text-slate-800">Account Status</h3>
                <p class="text-[11px] text-slate-500">Security verification complete</p>
              </div>
            </div>
            <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold text-[10px] rounded-full">
              Verified
            </span>
          </div>
          <p class="text-[11px] text-slate-600 leading-relaxed">
            Your university instructor account is currently active with verified institutional permissions.
          </p>
        </div>

        <!-- Password Status Card -->
        <div class="p-5 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-indigo-100 text-[#5138ed] flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div>
                <h3 class="text-[13px] font-bold text-slate-800">Account Password</h3>
                <p class="text-[11px] text-slate-500">Encrypted with strong hashing</p>
              </div>
            </div>
            <button 
              @click="profileStore.activeTab = 'password'"
              type="button"
              class="text-[11px] font-bold text-[#5138ed] hover:underline cursor-pointer"
            >
              Update
            </button>
          </div>
          <p class="text-[11px] text-slate-600 leading-relaxed">
            Protect your exams and questions by keeping your password updated and unique.
          </p>
        </div>

      </div>

      <!-- Current Session & Device Information -->
      <div class="border border-slate-100 rounded-xl p-5 space-y-4">
        <h3 class="text-[13px] font-bold text-slate-800">Active Authentication Sessions</h3>
        
        <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-100">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="text-[12px] font-bold text-slate-800">Current Web Session (Online Exam Portal)</span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Active Now
                </span>
              </div>
              <p class="text-[11px] text-slate-500 mt-0.5">Wollo University Main Campus • Authenticated via Token</p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 3: NOTIFICATION PREFERENCES           -->
    <!-- ========================================== -->
    <div v-else-if="profileStore.activeTab === 'notifications'" class="space-y-6">
      <div>
        <h2 class="text-[15px] font-bold text-slate-800">Notification Preferences</h2>
        <p class="text-[11px] text-slate-500 mt-0.5">Configure when and how you receive alerts for exam cycles, student submissions, and security updates.</p>
      </div>

      <div class="space-y-4 divide-y divide-slate-100">
        
        <!-- Email Notifications -->
        <div class="pt-4 first:pt-0 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">Email Notifications</h3>
            <p class="text-[11px] text-slate-500">Receive important academic notices and alerts directly at {{ profileStore.profile?.email || 'your email' }}.</p>
          </div>
          <button 
            @click="toggleNotification('email_notifications')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.email_notifications ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.email_notifications ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

        <!-- Exam Notifications -->
        <div class="pt-4 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">Exam Schedules & Deadlines</h3>
            <p class="text-[11px] text-slate-500">Receive alerts when exams are scheduled, published, or when exam start times are approaching.</p>
          </div>
          <button 
            @click="toggleNotification('exam_notifications')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.exam_notifications ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.exam_notifications ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

        <!-- Result Notifications -->
        <div class="pt-4 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">Student Results & Submissions</h3>
            <p class="text-[11px] text-slate-500">Notifications when students complete exams and when grade submissions are ready for review.</p>
          </div>
          <button 
            @click="toggleNotification('result_notifications')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.result_notifications ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.result_notifications ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

        <!-- System Notifications -->
        <div class="pt-4 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">System Maintenance & Status</h3>
            <p class="text-[11px] text-slate-500">Updates regarding scheduled system maintenance windows and examination platform status.</p>
          </div>
          <button 
            @click="toggleNotification('system_notifications')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.system_notifications ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.system_notifications ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

        <!-- Announcements -->
        <div class="pt-4 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">Department & Faculty Announcements</h3>
            <p class="text-[11px] text-slate-500">Important notices issued by the Department Head and Academic Commission.</p>
          </div>
          <button 
            @click="toggleNotification('announcements')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.announcements ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.announcements ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

        <!-- Security Notifications -->
        <div class="pt-4 flex items-start justify-between gap-4">
          <div class="space-y-0.5">
            <h3 class="text-[13px] font-bold text-slate-800">Security Alerts</h3>
            <p class="text-[11px] text-slate-500">Instant alerts for password changes, profile edits, and unusual login attempts.</p>
          </div>
          <button 
            @click="toggleNotification('security_notifications')"
            type="button"
            class="relative w-11 h-6 rounded-full transition-colors focus:outline-none shrink-0 cursor-pointer"
            :class="profileStore.profile?.notification_preferences?.security_notifications ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform mt-1"
              :class="profileStore.profile?.notification_preferences?.security_notifications ? 'translate-x-6' : 'translate-x-1'"
            ></span>
          </button>
        </div>

      </div>

      <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100 flex items-center gap-2.5 text-slate-600 text-[11px]">
        <svg class="w-4 h-4 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Notification preferences are saved automatically upon toggling.</span>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 4: CHANGE PASSWORD                    -->
    <!-- ========================================== -->
    <div v-else-if="profileStore.activeTab === 'password'" class="space-y-6">
      <div>
        <h2 class="text-[15px] font-bold text-slate-800">Change Password</h2>
        <p class="text-[11px] text-slate-500 mt-0.5">Update your password to keep your examination materials and student grades protected.</p>
      </div>

      <!-- Success Alert -->
      <div v-if="passwordSuccess" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-[12px] flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ passwordSuccess }}</span>
      </div>

      <!-- Error Alert -->
      <div v-if="passwordError" class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-[12px] flex items-center gap-2">
        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span>{{ passwordError }}</span>
      </div>

      <div class="space-y-5 max-w-xl">
        
        <!-- Current Password -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Current Password</label>
          <div class="relative">
            <input 
              v-model="passwordForm.current_password"
              :type="showCurrentPassword ? 'text' : 'password'"
              placeholder="Enter current password"
              class="w-full px-4 py-2.5 pr-10 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
            />
            <button 
              @click="showCurrentPassword = !showCurrentPassword"
              type="button" 
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg v-if="!showCurrentPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
            </button>
          </div>
        </div>

        <!-- New Password -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">New Password</label>
          <div class="relative">
            <input 
              v-model="passwordForm.new_password"
              :type="showNewPassword ? 'text' : 'password'"
              placeholder="Enter new strong password"
              class="w-full px-4 py-2.5 pr-10 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
            />
            <button 
              @click="showNewPassword = !showNewPassword"
              type="button" 
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg v-if="!showNewPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
            </button>
          </div>
        </div>

        <!-- Confirm New Password -->
        <div class="space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Confirm New Password</label>
          <div class="relative">
            <input 
              v-model="passwordForm.new_password_confirmation"
              :type="showConfirmPassword ? 'text' : 'password'"
              placeholder="Confirm new password"
              class="w-full px-4 py-2.5 pr-10 text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors bg-white text-slate-800"
            />
            <button 
              @click="showConfirmPassword = !showConfirmPassword"
              type="button" 
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
            >
              <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
            </button>
          </div>
        </div>

        <!-- Password Criteria Checklist -->
        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-2">
          <p class="text-[11px] font-bold text-slate-700">Password Requirements:</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div class="flex items-center gap-2" :class="hasMinLength ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="hasMinLength ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>At least 8 characters</span>
            </div>
            <div class="flex items-center gap-2" :class="hasUpperCase ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="hasUpperCase ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>One uppercase letter (A-Z)</span>
            </div>
            <div class="flex items-center gap-2" :class="hasLowerCase ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="hasLowerCase ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>One lowercase letter (a-z)</span>
            </div>
            <div class="flex items-center gap-2" :class="hasNumber ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="hasNumber ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>One number (0-9)</span>
            </div>
            <div class="flex items-center gap-2" :class="hasSpecialChar ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="hasSpecialChar ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>One special character (!@#$)</span>
            </div>
            <div class="flex items-center gap-2" :class="passwordsMatch ? 'text-emerald-600 font-medium' : 'text-slate-400'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="passwordsMatch ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'"/></svg>
              <span>Passwords match</span>
            </div>
          </div>
        </div>

        <div class="pt-3">
          <button 
            @click="handleChangePassword"
            type="button"
            :disabled="!isPasswordFormValid || profileStore.isChangingPassword"
            class="px-6 py-2.5 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-sm cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="profileStore.isChangingPassword" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span>{{ profileStore.isChangingPassword ? 'Updating Password...' : 'Update Password' }}</span>
          </button>
        </div>

      </div>

    </div>

  </div>
</template>

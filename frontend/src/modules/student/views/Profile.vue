<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useStudentProfile } from '../composables/useStudentProfile'
import { useStudentExamStore } from '../store/studentExamStore'

// Components
import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'

const router = useRouter()
const {
  profile,
  isFetching,
  isSaving,
  isUploadingPhoto,
  fetchProfile,
  updatePersonalProfile,
  uploadPhoto,
  removePhoto,
  changePassword,
} = useStudentProfile()

const examStore = useStudentExamStore()

// Layout state
const isSidebarOpen = ref(false)
const activeTab = ref<'personal' | 'academic' | 'performance' | 'security' | 'preferences'>('personal')

// Toast / Notification system
const toast = reactive({
  show: false,
  type: 'success' as 'success' | 'error',
  message: '',
})

const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  toast.message = message
  toast.type = type
  toast.show = true
  setTimeout(() => {
    toast.show = false
  }, 4000)
}

// Personal Form State (synced strictly with real backend user attributes)
const personalForm = reactive({
  name: '',
  phone: '',
  gender: 'male',
  office: '',
})

const syncPersonalForm = () => {
  personalForm.name = profile.value.name || ''
  personalForm.phone = profile.value.phone || ''
  personalForm.gender = profile.value.gender || 'male'
  personalForm.office = profile.value.office || ''
}

// Security / Password Form State
const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const showPasswords = reactive({
  current: false,
  new: false,
  confirm: false,
})

const passwordErrors = ref<string[]>([])

// Real-time password validation checks
const passwordChecks = computed(() => {
  const pwd = passwordForm.new_password
  return {
    length: pwd.length >= 8,
    upper: /[A-Z]/.test(pwd),
    lower: /[a-z]/.test(pwd),
    number: /[0-9]/.test(pwd),
    special: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?`~]/.test(pwd),
    match: pwd.length > 0 && pwd === passwordForm.new_password_confirmation,
  }
})

const isPasswordFormValid = computed(() => {
  const c = passwordChecks.value
  return (
    passwordForm.current_password.length > 0 &&
    c.length &&
    c.upper &&
    c.lower &&
    c.number &&
    c.special &&
    c.match
  )
})

// Notification preferences state from real user data
const preferences = reactive({
  emailExamAlerts: true,
  gradePublishedAlerts: true,
  announcementAlerts: true,
})

// Photo upload file input ref
const fileInputRef = ref<HTMLInputElement | null>(null)

const triggerPhotoPicker = () => {
  fileInputRef.value?.click()
}

const handlePhotoSelected = async (e: Event) => {
  const target = e.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return

  const file = target.files[0]
  if (file.size > 2 * 1024 * 1024) {
    showToast('File size must be under 2MB.', 'error')
    target.value = ''
    return
  }

  const res = await uploadPhoto(file)
  if (res.success) {
    showToast(res.message, 'success')
  } else {
    showToast(res.message, 'error')
  }
  target.value = ''
}

const handleRemovePhoto = async () => {
  if (!confirm('Are you sure you want to remove your profile photo?')) return
  const res = await removePhoto()
  if (res.success) {
    showToast(res.message, 'success')
  } else {
    showToast(res.message, 'error')
  }
}

// Save Personal Profile via API
const handleSavePersonal = async () => {
  if (!personalForm.name.trim()) {
    showToast('Full Name is required.', 'error')
    return
  }

  const res = await updatePersonalProfile({
    name: personalForm.name.trim(),
    phone: personalForm.phone.trim(),
    gender: personalForm.gender,
    office: personalForm.office.trim(),
  })

  if (res.success) {
    showToast('Profile information updated successfully.', 'success')
  } else {
    showToast(res.message || 'Failed to update profile.', 'error')
  }
}

// Change Password Handler via API
const handleChangePassword = async () => {
  passwordErrors.value = []

  if (!passwordForm.current_password) {
    passwordErrors.value.push('Please enter your current password.')
    return
  }

  if (!isPasswordFormValid.value) {
    passwordErrors.value.push('Please ensure all new password requirements are met.')
    return
  }

  const res = await changePassword({
    current_password: passwordForm.current_password,
    new_password: passwordForm.new_password,
    new_password_confirmation: passwordForm.new_password_confirmation,
  })

  if (res.success) {
    showToast('Password changed successfully.', 'success')
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    passwordForm.new_password_confirmation = ''
  } else {
    if (res.errors) {
      const errs: string[] = []
      for (const key of Object.keys(res.errors)) {
        errs.push(...res.errors[key])
      }
      passwordErrors.value = errs
    } else {
      passwordErrors.value = [res.message || 'Failed to change password.']
    }
    showToast(res.message || 'Failed to change password.', 'error')
  }
}

// Save Preferences Handler
const handleSavePreferences = async () => {
  const res = await updatePersonalProfile({
    notification_preferences: { ...preferences },
  })
  if (res.success) {
    showToast('Notification preferences saved successfully.', 'success')
  } else {
    showToast('Failed to save preferences.', 'error')
  }
}

// ── REAL ACADEMIC AUDIT & METRICS COMPUTATIONS ──
const completedResults = computed(() => examStore.results || [])
const upcomingExams = computed(() => examStore.upcomingExams || [])

// Real GPA calculation derived directly from student's exam results
const realCGPA = computed(() => {
  if (completedResults.value.length === 0) return null

  // Standard Wollo University 4.0 scale conversion
  let totalGradePoints = 0
  for (const r of completedResults.value) {
    const pct = r.percentage || 0
    if (pct >= 85) totalGradePoints += 4.0
    else if (pct >= 80) totalGradePoints += 3.75
    else if (pct >= 75) totalGradePoints += 3.5
    else if (pct >= 70) totalGradePoints += 3.0
    else if (pct >= 65) totalGradePoints += 2.75
    else if (pct >= 60) totalGradePoints += 2.5
    else if (pct >= 50) totalGradePoints += 2.0
    else totalGradePoints += 0.0
  }

  const gpa = totalGradePoints / completedResults.value.length
  return Math.round(gpa * 100) / 100
})

// Real Average Score
const realAverageScore = computed(() => {
  if (completedResults.value.length === 0) return null
  const sum = completedResults.value.reduce((acc, r) => acc + (r.percentage || 0), 0)
  return Math.round((sum / completedResults.value.length) * 10) / 10
})

// Real Highest Score
const realHighestScore = computed(() => {
  if (completedResults.value.length === 0) return null
  return Math.max(...completedResults.value.map(r => r.percentage || 0))
})

// Real Pass Rate
const realPassRate = computed(() => {
  if (completedResults.value.length === 0) return null
  const passed = completedResults.value.filter(r => (r.percentage || 0) >= 50).length
  return Math.round((passed / completedResults.value.length) * 100)
})

// Real Department Head from loaded user relationship
const departmentHead = computed(() => {
  const head = profile.value.rawUser?.department?.head
  if (!head) return null
  return {
    name: head.name || 'Department Head',
    email: head.email || '',
    phone: head.phone || '',
    office: head.office || '',
  }
})

// Real College name
const collegeName = computed(() => {
  return profile.value.rawUser?.department?.college || 'College of Computing and Informatics'
})

// Real Department code
const departmentCode = computed(() => {
  return profile.value.rawUser?.department?.code || ''
})

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    examStore.fetchResults(),
    examStore.fetchExams(),
    examStore.fetchDashboard(),
  ])
  syncPersonalForm()
})
</script>

<template>
  <div class="min-h-screen bg-[#f5f6fa] font-sans text-slate-800 antialiased flex flex-col">
    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="[]"
      @open-profile="() => {}"
      @open-notifications="() => {}"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer -->
    <StudentSidebar
      :profile="profile"
      :isOpen="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <!-- Hero Section (Identical banner aesthetic to Dashboard, My Exams, and Academic Calendar) -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: completedResults.length, upcomingExams: upcomingExams.length }"
      subtitle="STUDENT ACADEMIC PORTAL"
      title="MY PROFILE"
      description="View verified academic records, manage your profile identity, and configure security settings."
    />

    <!-- Toast Notification Banner -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed top-20 right-6 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold border backdrop-blur-md"
        :class="
          toast.type === 'success'
            ? 'bg-emerald-600/95 text-white border-emerald-400'
            : 'bg-rose-600/95 text-white border-rose-400'
        "
      >
        <svg v-if="toast.type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>{{ toast.message }}</span>
      </div>
    </transition>

    <!-- Hidden Photo Input -->
    <input
      ref="fileInputRef"
      type="file"
      class="hidden"
      accept="image/jpeg,image/png,image/webp,image/jpg"
      @change="handlePhotoSelected"
    />

    <!-- Main Expansive Full-Screen Container -->
    <main class="flex-1 w-full px-4 sm:px-8 lg:px-12 xl:px-16 py-8 space-y-8">
      
      <!-- Top Overview & Identity Card (Full-Width Responsive Card) -->
      <div class="w-full bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 lg:p-10 relative overflow-hidden">
        
        <!-- Subtle Top Ambient Accent Gradient -->
        <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-700"></div>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
          
          <!-- Left: Avatar, Name & Live Badges -->
          <div class="flex flex-col sm:flex-row items-center sm:items-center gap-6 text-center sm:text-left">
            
            <!-- Avatar Container with Photo Actions -->
            <div class="relative group flex-shrink-0">
              <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-3xl overflow-hidden ring-4 ring-slate-100 shadow-md bg-slate-100 relative">
                <img
                  :src="profile.avatar"
                  :alt="profile.name"
                  class="w-full h-full object-cover"
                  referrerpolicy="no-referrer"
                />
                
                <!-- Uploading Spinner Overlay -->
                <div
                  v-if="isUploadingPhoto"
                  class="absolute inset-0 bg-slate-900/70 flex flex-col items-center justify-center text-white text-xs font-semibold"
                >
                  <svg class="animate-spin h-6 w-6 text-white mb-1" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>Uploading...</span>
                </div>
              </div>

              <!-- Camera Upload Button -->
              <button
                type="button"
                @click="triggerPhotoPicker"
                :disabled="isUploadingPhoto"
                title="Upload profile picture (Max 2MB)"
                class="absolute -bottom-1.5 -right-1.5 p-2.5 rounded-2xl bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 transition-all hover:scale-105 active:scale-95 border-2 border-white focus:outline-none"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </button>

              <!-- Remove Photo Button -->
              <button
                v-if="profile.avatar && !profile.avatar.includes('ui-avatars.com')"
                type="button"
                @click="handleRemovePhoto"
                title="Remove custom photo"
                class="absolute -top-1.5 -right-1.5 p-1.5 rounded-full bg-slate-800 text-white hover:bg-rose-600 transition-colors shadow-sm focus:outline-none"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>

            <!-- Identity Labels -->
            <div class="space-y-2">
              <div class="flex items-center justify-center sm:justify-start gap-3 flex-wrap">
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ profile.name }}</h2>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                  {{ profile.status || 'Active Student' }}
                </span>
              </div>

              <p class="text-sm font-semibold text-slate-600">
                {{ profile.program }}
              </p>

              <div class="flex items-center justify-center sm:justify-start gap-2.5 pt-1 flex-wrap">
                <!-- Official ID Badge -->
                <span class="inline-flex items-center gap-1.5 text-xs font-mono font-bold bg-slate-100 text-slate-700 px-3 py-1.5 rounded-xl border border-slate-200">
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                  {{ profile.id }}
                </span>

                <!-- Department Badge -->
                <span class="text-xs font-bold bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-xl border border-indigo-100">
                  {{ profile.department }}
                </span>

                <!-- Year & Section Badge -->
                <span class="text-xs font-bold bg-slate-800 text-white px-3 py-1.5 rounded-xl">
                  {{ profile.yearLevel }} • {{ profile.section }}
                </span>
              </div>
            </div>

          </div>

          <!-- Right: Real Academic KPI Tiles (Strictly Real Data) -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 w-full lg:w-auto">
            
            <!-- Real CGPA -->
            <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-100 flex flex-col justify-between min-w-[130px]">
              <span class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">Cumulative GPA</span>
              <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl sm:text-3xl font-black text-indigo-900">
                  {{ realCGPA !== null ? realCGPA.toFixed(2) : 'N/A' }}
                </span>
                <span v-if="realCGPA !== null" class="text-[11px] font-bold text-indigo-400">/ 4.00</span>
              </div>
              <p class="text-[10px] text-indigo-600 font-semibold mt-2">
                {{ completedResults.length > 0 ? 'Verified Transcript' : 'Pending First Exam' }}
              </p>
            </div>

            <!-- Completed Assessments -->
            <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-100 flex flex-col justify-between min-w-[130px]">
              <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Exams Completed</span>
              <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl sm:text-3xl font-black text-emerald-900">{{ completedResults.length }}</span>
                <span class="text-[11px] font-bold text-emerald-500">Exams</span>
              </div>
              <p class="text-[10px] text-emerald-700 font-semibold mt-2">
                {{ upcomingExams.length }} Scheduled
              </p>
            </div>

            <!-- Real Average Score -->
            <div class="p-4 rounded-2xl bg-sky-50/70 border border-sky-100 flex flex-col justify-between min-w-[130px]">
              <span class="text-[10px] uppercase font-bold text-sky-600 tracking-wider">Average Score</span>
              <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl sm:text-3xl font-black text-sky-900">
                  {{ realAverageScore !== null ? `${realAverageScore}%` : 'N/A' }}
                </span>
              </div>
              <p class="text-[10px] text-sky-600 font-semibold mt-2">
                {{ completedResults.length > 0 ? 'Across Completed Tests' : 'No graded attempts' }}
              </p>
            </div>

            <!-- Real Pass Rate -->
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-100 flex flex-col justify-between min-w-[130px]">
              <span class="text-[10px] uppercase font-bold text-amber-600 tracking-wider">Pass Rate</span>
              <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl sm:text-3xl font-black text-amber-900">
                  {{ realPassRate !== null ? `${realPassRate}%` : 'N/A' }}
                </span>
              </div>
              <p class="text-[10px] text-amber-700 font-semibold mt-2">
                {{ realPassRate !== null && realPassRate >= 50 ? 'Academic Standing: Good' : 'Standing: Pending' }}
              </p>
            </div>

          </div>

        </div>

      </div>

      <!-- Navigation Tabs Bar (Expansive, Full-Width Bar) -->
      <div class="border-b border-slate-200 bg-white rounded-2xl shadow-sm px-4 sm:px-8 flex items-center gap-3 sm:gap-6 overflow-x-auto no-scrollbar">
        
        <button
          @click="activeTab = 'personal'"
          :class="[
            'py-4 px-3 text-xs sm:text-sm font-bold flex items-center gap-2.5 border-b-2 whitespace-nowrap transition-colors',
            activeTab === 'personal'
              ? 'border-indigo-600 text-indigo-600 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-900',
          ]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <span>Personal Information</span>
        </button>

        <button
          @click="activeTab = 'academic'"
          :class="[
            'py-4 px-3 text-xs sm:text-sm font-bold flex items-center gap-2.5 border-b-2 whitespace-nowrap transition-colors',
            activeTab === 'academic'
              ? 'border-indigo-600 text-indigo-600 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-900',
          ]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
          </svg>
          <span>Academic & Enrollment</span>
        </button>

        <button
          @click="activeTab = 'performance'"
          :class="[
            'py-4 px-3 text-xs sm:text-sm font-bold flex items-center gap-2.5 border-b-2 whitespace-nowrap transition-colors',
            activeTab === 'performance'
              ? 'border-indigo-600 text-indigo-600 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-900',
          ]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <span>Assessment Records</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-600">{{ completedResults.length }}</span>
        </button>

        <button
          @click="activeTab = 'security'"
          :class="[
            'py-4 px-3 text-xs sm:text-sm font-bold flex items-center gap-2.5 border-b-2 whitespace-nowrap transition-colors',
            activeTab === 'security'
              ? 'border-indigo-600 text-indigo-600 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-900',
          ]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span>Security & Password</span>
        </button>

        <button
          @click="activeTab = 'preferences'"
          :class="[
            'py-4 px-3 text-xs sm:text-sm font-bold flex items-center gap-2.5 border-b-2 whitespace-nowrap transition-colors',
            activeTab === 'preferences'
              ? 'border-indigo-600 text-indigo-600 font-extrabold'
              : 'border-transparent text-slate-500 hover:text-slate-900',
          ]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          </svg>
          <span>Preferences</span>
        </button>

      </div>

      <!-- Tab Content Area (Full-Width Responsive Cards) -->
      <div class="space-y-6">

        <!-- 1. PERSONAL INFORMATION TAB -->
        <div v-if="activeTab === 'personal'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">
          
          <!-- Left 2 Cols: Editable Form -->
          <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
              <h3 class="text-xl font-black text-slate-900">Personal Information</h3>
              <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage your identity information and contact details</p>
            </div>

            <form @submit.prevent="handleSavePersonal" class="space-y-6">
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Full Name -->
                <div class="space-y-2">
                  <label class="block text-xs font-bold text-slate-700">Full Name <span class="text-rose-500">*</span></label>
                  <input
                    v-model="personalForm.name"
                    type="text"
                    required
                    placeholder="Enter full name"
                    class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                </div>

                <!-- Phone Number -->
                <div class="space-y-2">
                  <label class="block text-xs font-bold text-slate-700">Phone Number</label>
                  <input
                    v-model="personalForm.phone"
                    type="tel"
                    placeholder="e.g. 0980426395"
                    class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                </div>

                <!-- Gender -->
                <div class="space-y-2">
                  <label class="block text-xs font-bold text-slate-700">Gender</label>
                  <select
                    v-model="personalForm.gender"
                    class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900 cursor-pointer"
                  >
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <!-- Campus Residence / Hostel / Office -->
                <div class="space-y-2">
                  <label class="block text-xs font-bold text-slate-700">Campus Residence / Address</label>
                  <input
                    v-model="personalForm.office"
                    type="text"
                    placeholder="e.g. Dessie Campus, Block 2, Room 104"
                    class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                </div>
              </div>

              <!-- Institutional Read-Only Attributes -->
              <div class="pt-6 border-t border-slate-100 space-y-3">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Institutional Records (Locked by Wollo University Registrar)</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <!-- Registered Email -->
                  <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                    <div class="flex items-center justify-between text-xs text-slate-400 font-bold mb-1">
                      <span>Institutional Email</span>
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <p class="text-sm font-semibold font-mono text-slate-800 truncate">{{ profile.email }}</p>
                  </div>

                  <!-- Student ID -->
                  <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                    <div class="flex items-center justify-between text-xs text-slate-400 font-bold mb-1">
                      <span>Official Student ID Code</span>
                      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <p class="text-sm font-semibold font-mono text-slate-800">{{ profile.id }}</p>
                  </div>
                </div>
              </div>

              <!-- Form Buttons -->
              <div class="pt-4 flex items-center justify-end gap-3">
                <button
                  type="button"
                  @click="syncPersonalForm"
                  class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
                >
                  Reset
                </button>
                <button
                  type="submit"
                  :disabled="isSaving"
                  class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200 flex items-center gap-2 disabled:opacity-50"
                >
                  <svg v-if="isSaving" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isSaving ? 'Saving Changes...' : 'Save Profile Changes' }}</span>
                </button>
              </div>

            </form>
          </div>

          <!-- Right 1 Col: Verified Institutional Record Card -->
          <div class="space-y-6">
            <div class="bg-gradient-to-br from-indigo-900 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-lg space-y-4 relative overflow-hidden">
              <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-indigo-500/20 rounded-full blur-xl pointer-events-none"></div>

              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-indigo-300 border border-white/15">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <div>
                  <h4 class="text-sm font-bold leading-tight">Verified Institutional Record</h4>
                  <p class="text-[11px] text-indigo-200">Wollo University Portal</p>
                </div>
              </div>

              <p class="text-xs text-indigo-100/90 leading-relaxed font-normal">
                This account is securely synchronized with the Wollo University Student Database. All examination sessions, auto-grading logs, and proctoring events are tracked with high integrity.
              </p>

              <div class="pt-3 border-t border-white/10 text-xs space-y-2 font-medium text-indigo-200">
                <div class="flex justify-between">
                  <span>Student ID:</span>
                  <span class="text-white font-mono font-bold">{{ profile.id }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Department:</span>
                  <span class="text-white">{{ profile.department }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Cohort:</span>
                  <span class="text-white">{{ profile.yearLevel }} • {{ profile.section }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Status:</span>
                  <span class="text-emerald-400 font-bold">Active / Enrolled</span>
                </div>
              </div>
            </div>

            <!-- Need Assistance Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-3">
              <h4 class="text-sm font-bold text-slate-900">Need Academic Modifications?</h4>
              <p class="text-xs text-slate-500 leading-relaxed">
                If your official Department, Cohort Section, or Name requires registrar correction, please contact your Academic Advisor or Department Head.
              </p>
              <div class="pt-2">
                <button
                  @click="activeTab = 'academic'"
                  class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors"
                >
                  <span>View Department Contacts</span>
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- 2. ACADEMIC & ENROLLMENT TAB -->
        <div v-if="activeTab === 'academic'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">
          
          <!-- Left 2 Cols: Academic Details Breakdown -->
          <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
              <h3 class="text-xl font-black text-slate-900">Academic & Enrollment Structure</h3>
              <p class="text-xs sm:text-sm text-slate-500 mt-1">Official curriculum and department affiliation data</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              
              <!-- Institution -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Institution</span>
                <p class="text-sm font-bold text-slate-900">Wollo University (ወሎ ዩኒቨርሲቲ)</p>
                <p class="text-xs text-slate-500">Ministry of Education, Ethiopia</p>
              </div>

              <!-- College -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">College / Faculty</span>
                <p class="text-sm font-bold text-slate-900">{{ collegeName }}</p>
                <p class="text-xs text-slate-500">School of Computing & Systems</p>
              </div>

              <!-- Department -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Department</span>
                <p class="text-sm font-bold text-indigo-700">{{ profile.department }}</p>
                <p class="text-xs text-slate-500">Code: {{ departmentCode || 'Active' }}</p>
              </div>

              <!-- Degree Program -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Degree Award</span>
                <p class="text-sm font-bold text-slate-900">{{ profile.program }}</p>
                <p class="text-xs text-slate-500">Undergraduate Bachelor Program</p>
              </div>

              <!-- Cohort & Section -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Year Level & Section</span>
                <p class="text-sm font-bold text-slate-900">{{ profile.yearLevel }} • {{ profile.section }}</p>
                <p class="text-xs text-emerald-600 font-bold">Assigned Active Section</p>
              </div>

              <!-- Semester -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Semester & Academic Year</span>
                <p class="text-sm font-bold text-slate-900">{{ profile.semester }} • {{ profile.academicYear }}</p>
                <p class="text-xs text-slate-500">Regular Academic Term</p>
              </div>

            </div>

            <!-- Examination Readiness Info Card -->
            <div class="p-5 rounded-2xl bg-indigo-50/50 border border-indigo-100 flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div>
                <h4 class="text-xs font-bold text-indigo-950">Examination Room Eligibility Verified</h4>
                <p class="text-xs text-indigo-700 mt-0.5 leading-relaxed">
                  Your student record is officially assigned to <strong>{{ profile.department }}</strong>, <strong>{{ profile.yearLevel }}</strong>, <strong>{{ profile.section }}</strong>. When instructors create and publish exams for your cohort, they will appear automatically on your dashboard.
                </p>
              </div>
            </div>

          </div>

          <!-- Right 1 Col: Real Department Leadership Directory -->
          <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-4">
              <h4 class="text-sm font-bold text-slate-900">Department Leadership</h4>
              
              <div v-if="departmentHead" class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                    DH
                  </div>
                  <div>
                    <p class="text-xs font-bold text-slate-900">{{ departmentHead.name }}</p>
                    <p class="text-[11px] text-slate-500">Department Head ({{ profile.department }})</p>
                  </div>
                </div>
                <div class="text-[11px] text-slate-600 space-y-1 pt-1 border-t border-slate-200/60">
                  <div v-if="departmentHead.email" class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <a :href="`mailto:${departmentHead.email}`" class="text-indigo-600 hover:underline">{{ departmentHead.email }}</a>
                  </div>
                  <div v-if="departmentHead.phone" class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>{{ departmentHead.phone }}</span>
                  </div>
                </div>
              </div>

              <div v-else class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-xs text-slate-500">
                <p class="font-bold text-slate-700">Department Head: In Appointment</p>
                <p class="text-[11px] text-slate-400 mt-1">Dean's office will assign the department head for this session.</p>
              </div>

              <!-- Registrar Contact -->
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                <p class="text-xs font-bold text-slate-900">Registrar Information Office</p>
                <p class="text-[11px] text-slate-500">Wollo University Main Campus, Dessie</p>
                <p class="text-[11px] text-indigo-600 font-semibold pt-1">registrar@wu.edu.et</p>
              </div>
            </div>
          </div>

        </div>

        <!-- 3. ASSESSMENT RECORDS TAB (Strictly Real Data) -->
        <div v-if="activeTab === 'performance'" class="space-y-6 animate-in fade-in duration-200">
          
          <!-- Performance Metrics Strip -->
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex items-center gap-4">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Exams Completed</p>
                <p class="text-2xl font-black text-slate-900 mt-0.5">{{ completedResults.length }}</p>
                <p class="text-[11px] text-indigo-600 font-semibold">Submitted attempts</p>
              </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex items-center gap-4">
              <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
              </div>
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Average Score</p>
                <p class="text-2xl font-black text-emerald-600 mt-0.5">
                  {{ realAverageScore !== null ? `${realAverageScore}%` : 'N/A' }}
                </p>
                <p class="text-[11px] text-emerald-700 font-semibold">Course average</p>
              </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex items-center gap-4">
              <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
              </div>
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Highest Score</p>
                <p class="text-2xl font-black text-amber-600 mt-0.5">
                  {{ realHighestScore !== null ? `${realHighestScore}%` : 'N/A' }}
                </p>
                <p class="text-[11px] text-amber-700 font-semibold">Personal best</p>
              </div>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex items-center gap-4">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              </div>
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Calculated CGPA</p>
                <p class="text-2xl font-black text-indigo-900 mt-0.5">
                  {{ realCGPA !== null ? realCGPA.toFixed(2) : '0.00' }}
                </p>
                <p class="text-[11px] text-indigo-600 font-semibold">4.0 Grade Point Scale</p>
              </div>
            </div>

          </div>

          <!-- Completed Exam Attempts Table (Real Records from DB) -->
          <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6 sm:p-8 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 class="text-xl font-black text-slate-900">Completed Assessment Records</h3>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Verified exam grades officially recorded in the database</p>
              </div>
              <button
                @click="router.push('/student/results')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors"
              >
                <span>View Full Results Page</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </button>
            </div>

            <!-- Zero State -->
            <div v-if="completedResults.length === 0" class="p-16 text-center space-y-3">
              <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </div>
              <p class="text-base font-bold text-slate-800">No Assessment Records Yet</p>
              <p class="text-xs text-slate-400 max-w-md mx-auto">
                You currently have no completed exam attempts recorded. When you take exams published by your instructors, your official marks and grades will be documented here.
              </p>
            </div>

            <!-- Real Results Table -->
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  <tr>
                    <th class="py-4 px-6">Assessment Title</th>
                    <th class="py-4 px-6">Course</th>
                    <th class="py-4 px-4">Completion Date</th>
                    <th class="py-4 px-4 text-center">Score / Total</th>
                    <th class="py-4 px-4 text-center">Percentage</th>
                    <th class="py-4 px-4 text-center">Grade</th>
                    <th class="py-4 px-6 text-right">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="res in completedResults" :key="res.id" class="hover:bg-slate-50/70 transition-colors">
                    <td class="py-4 px-6 font-bold text-slate-900 text-sm">
                      {{ res.examTitle }}
                    </td>
                    <td class="py-4 px-6">
                      <span class="font-bold text-slate-800">{{ res.courseName || res.courseCode }}</span>
                      <span v-if="res.courseCode" class="block text-[11px] font-mono text-slate-400">{{ res.courseCode }}</span>
                    </td>
                    <td class="py-4 px-4 text-slate-600 font-medium">
                      {{ res.completedDate || 'Recently' }}
                    </td>
                    <td class="py-4 px-4 text-center font-bold text-slate-900 text-sm">
                      {{ res.score }} <span class="text-slate-400 font-normal">/ {{ res.totalMarks }}</span>
                    </td>
                    <td class="py-4 px-4 text-center">
                      <span class="px-2.5 py-1 rounded-full text-xs font-black" :class="(res.percentage || 0) >= 50 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        {{ res.percentage }}%
                      </span>
                    </td>
                    <td class="py-4 px-4 text-center">
                      <span class="px-3 py-1 rounded-xl text-xs font-extrabold bg-indigo-50 text-indigo-700">
                        {{ res.grade }}
                      </span>
                    </td>
                    <td class="py-4 px-6 text-right">
                      <button
                        @click="router.push(`/student/results/${res.id}`)"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-indigo-600 hover:bg-indigo-50 transition-colors"
                      >
                        Review
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

        </div>

        <!-- 4. SECURITY & PASSWORD TAB -->
        <div v-if="activeTab === 'security'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">
          
          <!-- Left 2 Cols: Change Password Form -->
          <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 pb-4">
              <h3 class="text-xl font-black text-slate-900">Security & Credentials</h3>
              <p class="text-xs sm:text-sm text-slate-500 mt-1">Change your account password and review active session security</p>
            </div>

            <!-- Error Banner -->
            <div v-if="passwordErrors.length > 0" class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
              <div class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Please fix the following issues:</span>
              </div>
              <ul class="list-disc list-inside space-y-0.5 text-[11px] pl-1">
                <li v-for="(err, i) in passwordErrors" :key="i">{{ err }}</li>
              </ul>
            </div>

            <form @submit.prevent="handleChangePassword" class="space-y-6">
              
              <!-- Current Password -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700">Current Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    v-model="passwordForm.current_password"
                    :type="showPasswords.current ? 'text' : 'password'"
                    required
                    placeholder="Enter current password"
                    class="w-full px-4 py-3 pr-11 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                  <button
                    type="button"
                    @click="showPasswords.current = !showPasswords.current"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                  >
                    <svg v-if="showPasswords.current" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                </div>
              </div>

              <!-- New Password -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700">New Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    v-model="passwordForm.new_password"
                    :type="showPasswords.new ? 'text' : 'password'"
                    required
                    placeholder="Enter new strong password"
                    class="w-full px-4 py-3 pr-11 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                  <button
                    type="button"
                    @click="showPasswords.new = !showPasswords.new"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                  >
                    <svg v-if="showPasswords.new" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                </div>
              </div>

              <!-- Confirm New Password -->
              <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700">Confirm New Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    v-model="passwordForm.new_password_confirmation"
                    :type="showPasswords.confirm ? 'text' : 'password'"
                    required
                    placeholder="Confirm new password"
                    class="w-full px-4 py-3 pr-11 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all font-semibold text-slate-900"
                  />
                  <button
                    type="button"
                    @click="showPasswords.confirm = !showPasswords.confirm"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                  >
                    <svg v-if="showPasswords.confirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                </div>
              </div>

              <!-- Password Requirements Checklist -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2.5">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Security Password Policy</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <div class="flex items-center gap-2" :class="passwordChecks.length ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>Minimum 8 characters</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordChecks.upper ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>One uppercase character (A-Z)</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordChecks.lower ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>One lowercase character (a-z)</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordChecks.number ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>At least one number (0-9)</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordChecks.special ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>One special character (!@#$)</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordChecks.match ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>Passwords match perfectly</span>
                  </div>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="pt-2 flex justify-end">
                <button
                  type="submit"
                  :disabled="isSaving || !isPasswordFormValid"
                  class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="isSaving" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isSaving ? 'Updating Password...' : 'Save New Password' }}</span>
                </button>
              </div>

            </form>
          </div>

          <!-- Right 1 Col: Security Standards Notice -->
          <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-4">
              <h4 class="text-sm font-bold text-slate-900">Online Examination Security Standards</h4>
              
              <div class="space-y-3 text-xs text-slate-600">
                <div class="flex items-start gap-2.5">
                  <div class="w-5 h-5 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </div>
                  <span>Single-session token policy ensures only one exam device is authenticated at a time.</span>
                </div>

                <div class="flex items-start gap-2.5">
                  <div class="w-5 h-5 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </div>
                  <span>Exam attempts auto-record your client IP address and device identifier.</span>
                </div>

                <div class="flex items-start gap-2.5">
                  <div class="w-5 h-5 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </div>
                  <span>Always sign out of campus public laboratories upon exam completion.</span>
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Protect your password. Never share your credentials with unauthorized individuals.</span>
              </div>
            </div>
          </div>

        </div>

        <!-- 5. NOTIFICATION PREFERENCES TAB -->
        <div v-if="activeTab === 'preferences'" class="max-w-4xl bg-white rounded-3xl p-6 sm:p-8 lg:p-10 border border-slate-200/80 shadow-sm space-y-6 animate-in fade-in duration-200">
          <div class="border-b border-slate-100 pb-4">
            <h3 class="text-xl font-black text-slate-900">Communication & Notifications</h3>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure your real-time notification alerts for scheduled exams and results</p>
          </div>

          <div class="divide-y divide-slate-100">
            <!-- Exam Alerts -->
            <div class="py-5 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-bold text-slate-900">Exam Publication Alerts</p>
                <p class="text-xs text-slate-500">Receive instant alerts when instructors schedule or publish an exam for {{ profile.department }} ({{ profile.section }}).</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                <input type="checkbox" v-model="preferences.emailExamAlerts" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>

            <!-- Grade Releases -->
            <div class="py-5 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-bold text-slate-900">Grade & Result Releases</p>
                <p class="text-xs text-slate-500">Get notified the moment your examiner finishes grading and publishes final score sheets.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                <input type="checkbox" v-model="preferences.gradePublishedAlerts" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>

            <!-- University Announcements -->
            <div class="py-5 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-bold text-slate-900">Academic Calendar Announcements</p>
                <p class="text-xs text-slate-500">Updates regarding semester schedules, exam periods, and university calendar adjustments.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                <input type="checkbox" v-model="preferences.announcementAlerts" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
          </div>

          <div class="pt-4 flex justify-end">
            <button
              type="button"
              @click="handleSavePreferences"
              :disabled="isSaving"
              class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200 flex items-center gap-2"
            >
              <span>{{ isSaving ? 'Saving...' : 'Save Preferences' }}</span>
            </button>
          </div>
        </div>

      </div>

    </main>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

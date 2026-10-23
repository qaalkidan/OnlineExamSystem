<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/authStore'
import wolloLogo from '@/assets/images/logo.png'
import campusBg from '@/assets/images/wollo-campus.png'

const router = useRouter()
const authStore = useAuthStore()

const loginField = ref('')
const password = ref('')
const showPassword = ref(false)
const showSupportModal = ref(false)

// Step 2 Form State (for Instructors)
const selectedDepartment = ref('')
const selectedCourse = ref('')
const selectedSection = ref('')

onMounted(async () => {
  authStore.error = null
  await authStore.checkPublicStatus()
  if (authStore.isMaintenanceActive) {
    router.replace('/maintenance')
  }
})

// Clear error banner when user edits input
watch([loginField, password], () => {
  if (authStore.error) {
    authStore.error = null
  }
})

const handleLogin = async () => {
  if (!loginField.value.trim() || !password.value) return

  try {
    const res = await authStore.login({
      login: loginField.value.trim(),
      password: password.value,
    })

    if (res?.step === 2 && authStore.pendingInstructor) {
      const opts = authStore.pendingInstructor.assignedOptions
      if (opts?.departments?.length) {
        selectedDepartment.value = opts.departments[0].name
      }
      if (opts?.sections?.length) {
        selectedSection.value = opts.sections[0]
      }
    }
  } catch (e) {
    if (authStore.isMaintenanceActive) {
      router.push('/maintenance')
    }
  }
}

// Reactively filter available courses based on selected department for Step 2
const availableCourses = computed(() => {
  if (!authStore.pendingInstructor?.assignedOptions?.courses) return []
  const allCourses = authStore.pendingInstructor.assignedOptions.courses
  if (!selectedDepartment.value) return allCourses

  const dept = authStore.pendingInstructor.assignedOptions.departments?.find(
    (d: any) => d.name === selectedDepartment.value
  )
  if (!dept) return allCourses

  return allCourses.filter(
    (c: any) => c.department_id === dept.id || c.department === selectedDepartment.value
  )
})

watch(
  availableCourses,
  (newCourses) => {
    if (newCourses.length > 0 && !newCourses.some((c: any) => c.title === selectedCourse.value)) {
      selectedCourse.value = newCourses[0].title
    }
  },
  { immediate: true }
)

const handleCompleteSetup = () => {
  if (!selectedDepartment.value || !selectedCourse.value || !selectedSection.value) return
  authStore.completeInstructorSetup({
    department: selectedDepartment.value,
    course: selectedCourse.value,
    section: selectedSection.value,
  })
}

const cancelStep2 = () => {
  authStore.pendingInstructor = null
}
</script>

<template>
  <div class="relative min-h-screen w-full flex flex-col justify-between overflow-x-hidden font-sans select-none">
    
    <!-- ── Full-Screen Campus Background ── -->
    <div class="fixed inset-0 z-0">
      <img
        :src="campusBg"
        alt="Wollo University Campus"
        class="w-full h-full object-cover object-bottom"
      />
      <!-- Subtle dark-blue university gradient overlay -->
      <div class="absolute inset-0 bg-gradient-to-b from-[#071F42]/85 via-[#0B2D5C]/50 to-[#071F42]/80"></div>
    </div>

    <!-- ── Header ── -->
    <header class="relative z-20 w-full px-6 sm:px-12 py-5 sm:py-6 flex items-center justify-between max-w-7xl mx-auto">
      <!-- Left side: University logo + Branding -->
      <div class="flex items-center gap-3 sm:gap-4">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-white/10 p-0.5 border-2 border-white/70 shadow-lg shrink-0 flex items-center justify-center overflow-hidden">
          <img
            :src="wolloLogo"
            alt="Wollo University Logo"
            class="w-full h-full object-contain rounded-full"
          />
        </div>
        <div>
          <h1 class="text-base sm:text-lg font-bold text-white tracking-tight leading-tight">
            Wollo University
          </h1>
          <p class="text-[11px] sm:text-xs text-blue-100/90 font-medium tracking-wide">
            Online Exam System
          </p>
        </div>
      </div>

      <!-- Right side: University Slogan -->
      <div class="hidden sm:block text-right">
        <span class="text-xs sm:text-sm font-medium text-white/90 tracking-wider">
          Better Learning <span class="text-white/40 mx-1.5">|</span> Brighter Future
        </span>
      </div>
    </header>

    <!-- ── Main Hero Content ── -->
    <main class="relative z-20 flex-1 flex items-center max-w-7xl mx-auto w-full px-6 sm:px-12 py-8 sm:py-12">
      <div class="w-full flex flex-col lg:flex-row items-center lg:items-start justify-between gap-10 lg:gap-14">
        
        <!-- Left Column: Hero Text & Feature Highlights -->
        <div class="flex-1 max-w-xl text-left">
          
          <!-- Welcome Badge -->
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#1769E0]/40 border border-blue-400/40 text-white text-xs font-semibold backdrop-blur-xs mb-5 shadow-xs">
            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 3L1 9L12 15L21 10.09V17H23V9M5 13.18V17.18C5 19.94 8.13 22 12 22C15.87 22 19 19.94 19 17.18V13.18L12 17L5 13.18Z" />
            </svg>
            <span>Welcome to Wollo University</span>
          </div>

          <!-- Main Heading -->
          <h2 class="text-4xl sm:text-5xl lg:text-[3.5rem] font-extrabold text-white tracking-tight leading-[1.1] mb-5">
            Online <span class="text-[#3b82f6]">Exam</span> System
          </h2>

          <!-- Description -->
          <p class="text-sm sm:text-base text-blue-100/90 leading-relaxed mb-8 sm:mb-10 max-w-lg">
            Secure, reliable and easy way to take your exams. Access your exams anytime, anywhere.
          </p>

          <!-- 3 Compact Feature Highlights -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 pt-2">
            <!-- Feature 1: Secure -->
            <div class="flex flex-col items-start">
              <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-white mb-2.5 backdrop-blur-xs shadow-xs">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-white mb-1">Secure</h3>
              <p class="text-[11px] text-blue-100/80 leading-snug">Your data is protected with advanced security.</p>
            </div>

            <!-- Feature 2: Fast & Reliable -->
            <div class="flex flex-col items-start">
              <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-white mb-2.5 backdrop-blur-xs shadow-xs">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-white mb-1">Fast & Reliable</h3>
              <p class="text-[11px] text-blue-100/80 leading-snug">A smooth and stable exam experience.</p>
            </div>

            <!-- Feature 3: Any Device -->
            <div class="flex flex-col items-start">
              <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-white mb-2.5 backdrop-blur-xs shadow-xs">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 class="text-sm font-bold text-white mb-1">Any Device</h3>
              <p class="text-[11px] text-blue-100/80 leading-snug">Access from computer, tablet or mobile.</p>
            </div>
          </div>
        </div>

        <!-- Right Column: Login Card -->
        <div class="w-full lg:w-[420px] shrink-0">
          <div class="bg-white rounded-3xl p-7 sm:p-9 shadow-2xl border border-white/40 transition-all text-slate-800">
            
            <!-- ── STEP 1: LOGIN FORM ── -->
            <template v-if="!authStore.pendingInstructor">
              <!-- Card Header -->
              <div class="mb-6">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Welcome Back</h2>
                <p class="text-xs text-slate-500 font-medium">Log in to your account to continue</p>
              </div>

              <!-- Error Banner -->
              <div
                v-if="authStore.error"
                class="mb-5 p-3 bg-rose-50 border border-rose-200 text-rose-600 text-xs rounded-xl flex items-center gap-2"
              >
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">{{ authStore.error }}</span>
              </div>

              <!-- Form -->
              <form @submit.prevent="handleLogin" class="space-y-4">
                
                <!-- Username or Student ID -->
                <div>
                  <label for="username-input" class="sr-only">Username or Student ID</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <input
                      id="username-input"
                      type="text"
                      v-model="loginField"
                      placeholder="Username or Student ID"
                      autocomplete="username"
                      required
                      class="block w-full pl-10 pr-4 py-3 bg-slate-50/80 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#1769E0] focus:ring-2 focus:ring-[#1769E0]/20 transition-all font-medium"
                    />
                  </div>
                </div>

                <!-- Password -->
                <div>
                  <label for="password-input" class="sr-only">Password</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                      </svg>
                    </div>
                    <input
                      id="password-input"
                      :type="showPassword ? 'text' : 'password'"
                      v-model="password"
                      placeholder="Password"
                      autocomplete="current-password"
                      required
                      class="block w-full pl-10 pr-10 py-3 bg-slate-50/80 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-[#1769E0] focus:ring-2 focus:ring-[#1769E0]/20 transition-all font-medium"
                    />
                    <!-- Toggle Password Visibility -->
                    <button
                      type="button"
                      @click="showPassword = !showPassword"
                      class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                      aria-label="Toggle password visibility"
                    >
                      <svg v-if="!showPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                      <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-1">
                  <button
                    type="submit"
                    :disabled="authStore.isLoading"
                    class="w-full py-3.5 px-6 rounded-xl bg-[#1769E0] hover:bg-[#1258be] active:bg-[#0e489e] text-white font-bold text-sm shadow-md shadow-blue-600/30 transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
                  >
                    <svg v-if="!authStore.isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <svg v-else class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ authStore.isLoading ? 'Logging In...' : 'Log In' }}</span>
                  </button>
                </div>
              </form>

              <!-- Divider: Need Help? -->
              <div class="relative flex items-center justify-center my-6">
                <div class="border-t border-slate-200 w-full"></div>
                <span class="bg-white px-3 text-[11px] text-slate-400 font-medium">Need Help?</span>
              </div>

              <!-- Contact Support Link -->
              <div class="text-center">
                <button
                  type="button"
                  @click="showSupportModal = true"
                  class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#1769E0] transition-colors cursor-pointer"
                >
                  <svg class="w-4 h-4 text-[#1769E0]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Contact Support</span>
                </button>
              </div>
            </template>

            <!-- ── STEP 2: INSTRUCTOR COURSE ASSIGNMENT SELECTION ── -->
            <template v-else>
              <div class="mb-5">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-[#1769E0] text-xs font-semibold rounded-full mb-2">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <span>Instructor Assignment</span>
                </div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">Select Teaching Context</h2>
                <p class="text-xs text-slate-500 mt-0.5">Please confirm your assigned department, course, and section.</p>
              </div>

              <div class="space-y-4">
                <!-- Department Selection -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Department</label>
                  <select
                    v-model="selectedDepartment"
                    class="block w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#1769E0]"
                  >
                    <option
                      v-for="dept in authStore.pendingInstructor?.assignedOptions?.departments || []"
                      :key="dept.id"
                      :value="dept.name"
                    >
                      {{ dept.name }}
                    </option>
                  </select>
                </div>

                <!-- Course Selection -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Course</label>
                  <select
                    v-model="selectedCourse"
                    class="block w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#1769E0]"
                  >
                    <option
                      v-for="course in availableCourses"
                      :key="course.id"
                      :value="course.title"
                    >
                      {{ course.title }} ({{ course.code }})
                    </option>
                  </select>
                </div>

                <!-- Section Selection -->
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-1">Section</label>
                  <select
                    v-model="selectedSection"
                    class="block w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#1769E0]"
                  >
                    <option
                      v-for="sec in authStore.pendingInstructor?.assignedOptions?.sections || []"
                      :key="sec"
                      :value="sec"
                    >
                      {{ sec }}
                    </option>
                  </select>
                </div>

                <!-- Step 2 Actions -->
                <div class="flex gap-2.5 pt-2">
                  <button
                    type="button"
                    @click="cancelStep2"
                    class="flex-1 py-2.5 px-4 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
                  >
                    Back
                  </button>
                  <button
                    type="button"
                    @click="handleCompleteSetup"
                    class="flex-2 py-2.5 px-4 bg-[#1769E0] hover:bg-[#1258be] text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center justify-center gap-1.5"
                  >
                    <span>Enter Portal</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                  </button>
                </div>
              </div>
            </template>

          </div>
        </div>

      </div>
    </main>

    <!-- ── Contact Support Modal ── -->
    <Transition name="fade">
      <div
        v-if="showSupportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs"
        @click.self="showSupportModal = false"
      >
        <div class="bg-white rounded-3xl w-full max-w-md p-6 sm:p-8 shadow-2xl relative border border-slate-100 text-slate-800">
          <!-- Close button -->
          <button
            @click="showSupportModal = false"
            class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
            aria-label="Close support modal"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Modal Header -->
          <div class="flex items-center gap-3 mb-5">
            <div class="w-11 h-11 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-[#1769E0] shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-slate-900 leading-tight">Wollo University ICT Support</h3>
              <p class="text-xs text-slate-500">Online Examination Help Desk</p>
            </div>
          </div>

          <!-- Support Details -->
          <div class="space-y-3.5 mb-6 text-xs">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <svg class="w-4 h-4 text-[#1769E0] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <div>
                <p class="font-bold text-slate-800">Support Email</p>
                <a href="mailto:support@wollo.edu.et" class="text-[#1769E0] hover:underline font-mono">support@wollo.edu.et</a>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <svg class="w-4 h-4 text-[#1769E0] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <div>
                <p class="font-bold text-slate-800">Direct Helpline</p>
                <p class="text-slate-600 font-mono">+251-33-311-5246 / +251-33-311-5247</p>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <svg class="w-4 h-4 text-[#1769E0] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <div>
                <p class="font-bold text-slate-800">Physical Office</p>
                <p class="text-slate-600">Main Campus, ICT Directorate Building, 2nd Floor</p>
              </div>
            </div>

            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100">
              <svg class="w-4 h-4 text-[#1769E0] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <p class="font-bold text-slate-800">Working Hours</p>
                <p class="text-slate-600">Monday – Friday: 8:30 AM – 5:30 PM (EAT)</p>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="showSupportModal = false"
            class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

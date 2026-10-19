<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/authStore'

const router = useRouter()
const authStore = useAuthStore()

onMounted(async () => {
  const notice = localStorage.getItem('semester_locked_notice') || sessionStorage.getItem('semester_locked_notice')
  if (notice) {
    // Navigate directly to dedicated Access Locked page instead of displaying an inline notification
    router.replace('/access-locked')
    return
  }

  await authStore.checkPublicStatus()
  if (authStore.isMaintenanceActive) {
    router.replace('/maintenance')
  }
})

const loginField = ref('')
const password = ref('')
const selectedRole = ref('student')
const rememberMe = ref(false)
const showPassword = ref(false)

// Step 2 Form State
const selectedDepartment = ref('')
const selectedCourse = ref('')
const selectedSection = ref('')

const handleLogin = async () => {
  try {
    const res = await authStore.login({
      login: loginField.value,
      password: password.value,
      role: selectedRole.value,
      remember: rememberMe.value
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

// Reactively filter available courses based on selected department
const availableCourses = computed(() => {
  if (!authStore.pendingInstructor?.assignedOptions?.courses) return []
  const allCourses = authStore.pendingInstructor.assignedOptions.courses
  if (!selectedDepartment.value) return allCourses

  const dept = authStore.pendingInstructor.assignedOptions.departments?.find(
    (d: any) => d.name === selectedDepartment.value
  )
  if (!dept) return allCourses

  return allCourses.filter((c: any) => c.department_id === dept.id || c.department === selectedDepartment.value)
})

watch(availableCourses, (newCourses) => {
  if (newCourses.length > 0 && !newCourses.some((c: any) => c.title === selectedCourse.value)) {
    selectedCourse.value = newCourses[0].title
  }
}, { immediate: true })

const handleCompleteSetup = () => {
  if (!selectedDepartment.value || !selectedCourse.value || !selectedSection.value) return
  authStore.completeInstructorSetup({
    department: selectedDepartment.value,
    course: selectedCourse.value,
    section: selectedSection.value
  })
}

const cancelStep2 = () => {
  authStore.pendingInstructor = null
}
</script>

<template>
  <div class="min-h-screen w-full bg-white flex items-center justify-center p-4 sm:p-6 font-sans">
    
    <!-- Main Card Container -->
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row border border-slate-200/60">
      
      <!-- Left Side (Branding & Image) -->
      <div class="hidden md:flex flex-col w-1/2 bg-[#b9e2f0] text-white p-8 relative overflow-hidden justify-between min-h-[460px]">
        
        <!-- Background Image with Gradient Overlay -->
        <div class="absolute inset-0 z-0 ">
          <img 
            src="../../../assets/images/imagep.png" 
            alt="Wollo University" 
              class="w-full h-full object-cover object-center"
          />
          
        </div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col h-full justify-between">
          <div>
            <!-- Logo & Title -->
            <div class="flex items-center gap-3 mb-8">
              <div class="w-12 h-12 bg-#A2D7E9 rounded-full flex items-center justify-center shadow-lg p-0.5 shrink-0">
                <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-full h-full object-contain rounded-full" />
              </div>
              <div>
                <h2 class="text-lg font-bold text-black tracking-wide">Wollo University</h2>
                <p class="text-xs text-black font-medium">Online Examination System</p>
              </div>
            </div>

            <!-- Hero Text -->
            <div class="mt-4">
              <h1 class="text-2xl font-extrabold text-black leading-tight mb-4">
                Excellence in Education,<br/>
                Innovation in Assessment
              </h1>
              <div class="w-12 h-1 bg-blue-400 mb-4 rounded-full"></div>
             
            </div>
          </div>

          <!-- Glassmorphism Badge at Bottom -->
          <div class="pt-6">
            <div class="bg-white/10 backdrop-blur-md border border-blue/20 rounded-xl p-3 flex items-center gap-3">
              <div class="bg-white/20 p-2 rounded-full shrink-0">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-black text-[13px]">Secure & Protected</h4>
                <p class="text-[11px] text-black">Your data is safe with enterprise grade security</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Side (Forms) -->
      <div class="w-full md:w-1/2 p-6 md:p-8 flex flex-col justify-center bg-white">
        
        <!-- STEP 1: INITIAL LOGIN FORM -->
        <template v-if="!authStore.pendingInstructor">
          <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-1">Welcome Back 👋</h2>
            <p class="text-xs text-slate-500">Sign in to continue to your account</p>
          </div>

          <!-- Error Banner -->
          <div v-if="authStore.error" class="mb-5 p-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ authStore.error }}</span>
          </div>

          <form @submit.prevent="handleLogin" class="space-y-4">
            
            <!-- Email or Username Input -->
            <div>
              <label for="login" class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address or Username</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <input 
                  id="login" 
                  type="text" 
                  v-model="loginField"
                  placeholder="Enter your email or username" 
                  class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all"
                  required
                />
              </div>
            </div>

            <!-- Role Selection Dropdown -->
            <div>
              <label for="role" class="block text-xs font-semibold text-slate-700 mb-1.5">Login As</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <select 
                  id="role" 
                  v-model="selectedRole"
                  class="block w-full pl-10 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all appearance-none cursor-pointer"
                  required
                >
                  <option value="student">Student</option>
                  <option value="instructor">Instructor</option>
                  <option value="staff">Staff</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Password Input -->
            <div>
              <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <input 
                  id="password" 
                  :type="showPassword ? 'text' : 'password'" 
                  v-model="password"
                  placeholder="Enter your password" 
                  class="block w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:bg-white transition-all font-medium"
                  required
                />
                <button 
                  type="button" 
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                  <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </button>
              </div>
              <div class="mt-1.5 text-right">
                <a href="#" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700">Forgot Password?</a>
              </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center pb-1">
              <input 
                id="remember-me" 
                type="checkbox" 
                v-model="rememberMe"
                class="h-3.5 w-3.5 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded cursor-pointer"
              />
              <label for="remember-me" class="ml-2 block text-xs text-slate-600 cursor-pointer">
                Remember me
              </label>
            </div>

            <!-- Sign In Button -->
            <div>
              <button 
                type="submit" 
                :disabled="authStore.isLoading"
                class="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-xl shadow-md text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
              >
                <span v-if="authStore.isLoading">Signing In...</span>
                <span v-else>Sign In</span>
                
                <svg v-if="!authStore.isLoading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
                <svg v-else class="animate-spin -ml-1 mr-3 h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </button>
            </div>
          </form>

          <div class="mt-6 text-center text-xs text-slate-500">
            Don't have an account? 
            <a href="#" class="font-semibold text-indigo-600 hover:text-indigo-700">Contact your administrator</a>
          </div>
        </template>

        <!-- STEP 2: INSTRUCTOR ASSIGNMENT SELECTION FORM -->
        <template v-else>
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full mb-3">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
              Step 2: Select Teaching Assignment
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-1">Select Active Context 🎓</h2>
            <p class="text-xs text-slate-500">Choose your assigned department, course, and section to access your instructor workspace.</p>
          </div>

          <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4 mb-6 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
              {{ authStore.pendingInstructor?.user?.name ? authStore.pendingInstructor.user.name.slice(0, 2).toUpperCase() : 'IN' }}
            </div>
            <div class="min-w-0 flex-1">
              <h4 class="text-sm font-bold text-slate-800 truncate">{{ authStore.pendingInstructor?.user?.name }}</h4>
              <p class="text-xs text-slate-500 truncate">{{ authStore.pendingInstructor?.user?.email }}</p>
            </div>
          </div>

          <form @submit.prevent="handleCompleteSetup" class="space-y-4">
            
            <!-- Department Dropdown -->
            <div>
              <label for="select-department" class="block text-xs font-semibold text-slate-700 mb-1.5">Assigned Department</label>
              <div class="relative">
                <select 
                  id="select-department" 
                  v-model="selectedDepartment"
                  class="block w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition-all cursor-pointer"
                  required
                >
                  <option value="" disabled>Select Department...</option>
                  <option 
                    v-for="dept in authStore.pendingInstructor?.assignedOptions?.departments || []" 
                    :key="dept.id" 
                    :value="dept.name"
                  >
                    {{ dept.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Course Dropdown -->
            <div>
              <label for="select-course" class="block text-xs font-semibold text-slate-700 mb-1.5">Assigned Course</label>
              <div class="relative">
                <select 
                  id="select-course" 
                  v-model="selectedCourse"
                  class="block w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition-all cursor-pointer"
                  required
                >
                  <option value="" disabled>Select Course...</option>
                  <option 
                    v-for="course in availableCourses" 
                    :key="course.id || course.code" 
                    :value="course.title"
                  >
                    {{ course.code ? `[${course.code}] ` : '' }}{{ course.title }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Section Dropdown -->
            <div>
              <label for="select-section" class="block text-xs font-semibold text-slate-700 mb-1.5">Assigned Section</label>
              <div class="relative">
                <select 
                  id="select-section" 
                  v-model="selectedSection"
                  class="block w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 transition-all cursor-pointer"
                  required
                >
                  <option value="" disabled>Select Section...</option>
                  <option 
                    v-for="sec in authStore.pendingInstructor?.assignedOptions?.sections || []" 
                    :key="sec" 
                    :value="sec"
                  >
                    {{ sec }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 pt-2">
              <button 
                type="button" 
                @click="cancelStep2"
                class="w-1/3 py-2.5 px-4 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 bg-white hover:bg-slate-50 transition-colors"
              >
                Back to Login
              </button>

              <button 
                type="submit" 
                class="w-2/3 flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-xl shadow-md text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition-all"
              >
                <span>Verify & Proceed to Dashboard</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
              </button>
            </div>
          </form>
        </template>

      </div>
    </div>

    <!-- Instructor Not Assigned Confirmation Modal Popup -->
    <Teleport to="body">
      <div 
        v-if="authStore.notAssignedModal.show" 
        @click.self="authStore.closeNotAssignedModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
      >
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full text-center shadow-2xl flex flex-col items-center transform transition-all animate-in fade-in zoom-in-95 duration-200 border border-slate-100">
          
          <!-- Warning Icon Badge -->
          <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mb-4 border border-amber-100 shadow-sm">
            <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>

          <!-- Modal Title -->
          <h3 class="text-xl font-black text-slate-800 mb-2">No Course Assigned</h3>

          <!-- Instructor Identity Box -->
          <div v-if="authStore.notAssignedModal.instructor" class="w-full bg-slate-50 border border-slate-100 rounded-xl p-3.5 mb-4 text-left">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-bold text-slate-800">{{ authStore.notAssignedModal.instructor.name }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-[#4338ca] uppercase">Instructor</span>
            </div>
            <div class="text-[11px] text-slate-500 space-y-0.5">
              <p class="truncate">{{ authStore.notAssignedModal.instructor.email }}</p>
              <p v-if="authStore.notAssignedModal.instructor.department" class="text-slate-600 font-medium">Department: {{ authStore.notAssignedModal.instructor.department }}</p>
            </div>
          </div>

          <!-- Reassuring Message -->
          <p class="text-[13px] text-slate-600 leading-relaxed mb-6">
            Your login credentials are valid, but your account is not yet assigned to any teaching courses. Please contact the <strong>Department Head</strong> or <strong>System Administrator</strong> to assign your course(s). Once assigned, you will be able to log in.
          </p>

          <!-- Dismiss Button -->
          <button 
            type="button" 
            @click="authStore.closeNotAssignedModal()"
            class="w-full py-3 px-5 bg-[#4338ca] hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-100 flex items-center justify-center gap-2 cursor-pointer"
          >
            <span>Understood</span>
          </button>
        </div>
      </div>
    </Teleport>

  </div>
</template>


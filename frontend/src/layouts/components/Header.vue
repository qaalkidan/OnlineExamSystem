<script setup lang="ts">
import { useAuthStore } from '../../modules/auth/store/authStore'
import { useSettingsStore } from '../../store/settingsStore'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../core/api/apiClient'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const route = useRoute()
const router = useRouter()

const instructorData = ref<any>(null)
const isProfileDropdownOpen = ref(false)
const profileDropdownRef = ref<HTMLElement | null>(null)

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
}

const closeDropdown = () => {
  isProfileDropdownOpen.value = false
}

const navigateTo = (path: string) => {
  closeDropdown()
  router.push(path)
}

const handleLogout = async () => {
  closeDropdown()
  await authStore.logout()
}

const handleClickOutside = (e: MouseEvent) => {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(e.target as Node)) {
    isProfileDropdownOpen.value = false
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    isProfileDropdownOpen.value = false
  }
}

onMounted(async () => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)

  // Only fetch if the user is an instructor
  if (authStore.user?.role === 'instructor' || authStore.user?.role === 'dept_head') {
    try {
      const res = await apiClient.get('/instructor/me')
      instructorData.value = res.data.data
    } catch (e) {
      console.error('Failed to fetch instructor info', e)
    }
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})

const instructorRoleText = computed(() => {
  if (!instructorData.value) return 'Instructor'
  
  let deptName = instructorData.value.department || ''
  
  // Abbreviate department name (e.g. Computer Science -> cs, Information System -> is)
  if (deptName) {
    const knownAbbreviations: Record<string, string> = {
      'computer science': 'cs',
      'information system': 'is',
      'information systems': 'is',
      'software engineering': 'se',
      'information technology': 'it'
    }
    
    const lowerDept = deptName.toLowerCase().trim()
    if (knownAbbreviations[lowerDept]) {
      deptName = knownAbbreviations[lowerDept]
    } else {
      // Fallback: create acronym from words
      deptName = lowerDept.split(' ')
        .map((w: string) => w[0])
        .join('')
        .toLowerCase()
    }
  }

  const dept = deptName ? `${deptName}` : ''
  const year = instructorData.value.year_level ? `${dept ? ', ' : ''}${instructorData.value.year_level}` : ''
  const section = instructorData.value.section ? `${(dept || year) ? ', ' : ''}${instructorData.value.section}` : ''
  
  // Clean up formatting: "is, 3rd year, section A, instructor"
  let str = `${dept}${year}${section}${(dept || year || section) ? ', ' : ''}instructor`
  return str.toLowerCase()
})

const isCreateExam = computed(() => route.path === '/instructor/exams/create')
const createExamStep = computed(() => Number(route.query.step) || 1)

// Map routes to dynamic titles
const pageTitle = computed(() => {
  if (isCreateExam.value) {
    if (createExamStep.value === 4) return 'Review & Publish'
    if (createExamStep.value === 2) return 'Add Questions'
    return 'Create New Exam'
  }
  if (route.path.includes('/dashboard')) return 'Instructor Dashboard'
  if (route.path.includes('/question-banks')) return 'Question Banks'
  if (route.path.includes('/exams')) return 'Exams Management'
  if (route.path.includes('/students')) return 'Student Management'
  if (route.path.includes('/results')) return 'Results'
  if (route.path.includes('/reports')) return 'Reports'
  if (route.path.includes('/semester-submission')) return 'Semester Submission'
  if (route.path.includes('/profile')) return 'My Profile'
  if (route.path.includes('/settings')) return 'Settings'
  return 'Dashboard'
})
</script>

<template>
  <header class="h-24 bg-white/80 backdrop-blur-md border-b border-slate-100 flex items-center justify-between px-8 sticky top-0 z-30">
    
    <!-- Left Side: Title & Menu Toggle (Mobile) -->
    <div class="flex items-center gap-4">
      <button class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
      <div class="hidden lg:flex items-center justify-center w-10 h-10 rounded-xl bg-slate-50 text-slate-500 border border-slate-100 mr-2">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"></path></svg>
      </div>
      
      <div class="flex flex-col">
        <h1 class="text-xl font-bold text-slate-800">{{ pageTitle }}</h1>
        <div v-if="isCreateExam" class="flex items-center gap-2 mt-0.5 text-[12px] font-medium text-slate-500">
          <router-link to="/instructor/exams" class="hover:text-[#5138ed] transition-colors">Exams</router-link>
          <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <router-link to="/instructor/exams/create" class="hover:text-[#5138ed] transition-colors" :class="{'text-slate-700': createExamStep === 1 || createExamStep === 3 || createExamStep === 4}">Create New Exam</router-link>
          
          <template v-if="createExamStep === 2">
            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-slate-700">Add Questions</span>
          </template>
        </div>
        <div v-else-if="route.path.includes('/results')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          View and analyze exam results and performance.
        </div>
        <div v-else-if="route.path.includes('/reports')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          View and analyze exam, student and performance reports.
        </div>
        <div v-else-if="route.path.includes('/profile')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          View and manage your account information and preferences.
        </div>
        <div v-else-if="route.path.includes('/settings')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Manage system settings and preferences.
        </div>
        <div v-else-if="route.path.includes('/semester-submission')" class="mt-0.5 text-[12px] font-medium text-slate-500">
          Submit finalized semester examination archive.
        </div>
      </div>
    </div>

    <!-- Center: Semester/Year Badge -->
    <div class="absolute left-1/2 -translate-x-1/2 hidden md:flex items-center">
      <span class="text-[13px] font-bold text-[#5138ed] bg-indigo-50 px-5 py-1.5 rounded-full border border-indigo-100 shadow-sm whitespace-nowrap">
        {{ settingsStore.formattedAcademicTerm }}
      </span>
    </div>

    <!-- Right Side: User Profile & Dropdown (Notification icon removed as requested) -->
    <div class="flex items-center">
      <div class="relative" ref="profileDropdownRef">
        <!-- Trigger Button -->
        <button
          type="button"
          @click="toggleProfileDropdown"
          class="flex items-center gap-3 px-3 py-2 rounded-2xl hover:bg-slate-100/80 transition-all border border-transparent hover:border-slate-200 cursor-pointer group focus:outline-none"
          :class="{ 'bg-slate-100/90 border-slate-200 shadow-sm': isProfileDropdownOpen }"
        >
          <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden border-2 border-transparent group-hover:border-[#5138ed] transition-all flex items-center justify-center shadow-sm">
            <img src="https://i.pravatar.cc/150?u=a042581f4e29026704d" alt="Profile" class="w-full h-full object-cover" />
          </div>
          <div class="hidden md:flex flex-col text-left">
            <span class="text-sm font-bold text-slate-800 group-hover:text-slate-900 leading-tight">
              {{ instructorData?.name || authStore.user?.name || 'Instructor' }}
            </span>
            <span class="text-[11px] font-medium text-slate-500 leading-snug max-w-[200px] truncate capitalize" :title="instructorRoleText">
              {{ instructorRoleText }}
            </span>
          </div>
          <svg
            class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-transform duration-200 ml-1"
            :class="{ 'rotate-180 text-[#5138ed]': isProfileDropdownOpen }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>

        <!-- Modern Instructor Dropdown Menu -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="transform scale-95 opacity-0 -translate-y-1"
          enter-to-class="transform scale-100 opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="transform scale-100 opacity-100 translate-y-0"
          leave-to-class="transform scale-95 opacity-0 -translate-y-1"
        >
          <div
            v-if="isProfileDropdownOpen"
            class="absolute right-0 top-full mt-2 w-72 bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-100 shadow-[0_15px_50px_-10px_rgba(0,0,0,0.15)] py-2 z-50 select-none overflow-hidden"
          >
            <!-- User Info Header -->
            <div class="px-4 py-3 bg-gradient-to-br from-indigo-50/60 via-slate-50/50 to-white border-b border-slate-100 flex items-center gap-3">
              <div class="relative w-11 h-11 rounded-full overflow-hidden border-2 border-indigo-400/60 shadow-sm shrink-0">
                <img src="https://i.pravatar.cc/150?u=a042581f4e29026704d" alt="Profile" class="w-full h-full object-cover" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-slate-900 truncate">
                  {{ instructorData?.name || authStore.user?.name || 'Instructor' }}
                </p>
                <p class="text-[11px] text-slate-500 truncate">
                  {{ instructorData?.email || authStore.user?.email || 'instructor@wollo.edu.et' }}
                </p>
                <div class="mt-1 flex items-center gap-1.5">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100/80 text-indigo-700 border border-indigo-200/60 capitalize truncate max-w-[140px]">
                    {{ instructorRoleText }}
                  </span>
                  <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Action Links -->
            <div class="p-1.5 space-y-0.5">
              <!-- My Profile -->
              <button
                type="button"
                @click="navigateTo('/instructor/profile')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">My Profile</div>
                  <div class="text-[10px] text-slate-400 font-normal">View & update instructor info</div>
                </div>
              </button>

              <!-- Question Banks -->
              <button
                type="button"
                @click="navigateTo('/instructor/question-banks')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Question Banks</div>
                  <div class="text-[10px] text-slate-400 font-normal">Manage reusable exam questions</div>
                </div>
              </button>

              <!-- Instructor Settings -->
              <button
                type="button"
                @click="navigateTo('/instructor/settings')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Account Settings</div>
                  <div class="text-[10px] text-slate-400 font-normal">Security & notification config</div>
                </div>
              </button>

              <!-- Semester Submission -->
              <button
                type="button"
                @click="navigateTo('/instructor/semester-submission')"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-indigo-50/70 hover:text-[#5138ed] transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-800 group-hover:text-[#5138ed]">Semester Submission</div>
                  <div class="text-[10px] text-slate-400 font-normal">Final course exam archive</div>
                </div>
              </button>
            </div>

            <!-- Divider -->
            <div class="h-px bg-slate-100 my-1 mx-2"></div>

            <!-- Logout Option -->
            <div class="p-1.5 pt-0.5">
              <button
                type="button"
                @click="handleLogout"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors group"
              >
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:bg-rose-100 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-rose-600">Sign Out</div>
                  <div class="text-[10px] text-rose-400 font-normal">End instructor session</div>
                </div>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

  </header>
</template>

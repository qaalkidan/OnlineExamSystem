<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()

// Stored lock context
const lockMessage = ref<string>('')
const courseTitle = ref<string>('')
const academicTerm = ref<string>('')
const instructorName = ref<string>('')

// Reopen check state
const isCheckingStatus = ref(false)
const isReopened = ref(false)
const statusCheckMessage = ref<string | null>(null)
let autoCheckTimer: any = null

onMounted(() => {
  const storedMsg = localStorage.getItem('semester_locked_notice') || sessionStorage.getItem('semester_locked_notice')
  const storedCourse = localStorage.getItem('semester_locked_course') || sessionStorage.getItem('semester_locked_course')
  const storedTerm = localStorage.getItem('semester_locked_term') || sessionStorage.getItem('semester_locked_term')
  const storedUser = localStorage.getItem('auth_user')

  if (storedMsg) lockMessage.value = storedMsg
  if (storedCourse) courseTitle.value = storedCourse
  if (storedTerm) academicTerm.value = storedTerm

  if (storedUser) {
    try {
      const u = JSON.parse(storedUser)
      instructorName.value = u.name || ''
    } catch (_) {}
  }

  // Fallback defaults if accessed directly
  if (!lockMessage.value) {
    lockMessage.value = 'The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
  }

  // Periodic check if the semester was reopened by the Department Head
  autoCheckTimer = setInterval(checkIfUnlocked, 8000)
})

onUnmounted(() => {
  if (autoCheckTimer) clearInterval(autoCheckTimer)
})

const goHome = () => {
  router.push('/')
}

const goToLogin = () => {
  router.push('/login')
}

// Check if Department Head has unlocked/reopened the workspace
const checkIfUnlocked = async () => {
  isCheckingStatus.value = true
  try {
    const res = await apiClient.get('/system/public-status')
    // If the backend has an active submission check or general public status
    const token = localStorage.getItem('auth_token')
    if (token) {
      const lockRes = await apiClient.get('/instructor/semester-lock-status')
      if (lockRes.data?.data && !lockRes.data.data.is_locked) {
        isReopened.value = true
        statusCheckMessage.value = 'Semester access has been restored! Redirecting to dashboard...'
        if (autoCheckTimer) clearInterval(autoCheckTimer)
        setTimeout(() => {
          router.push('/instructor/dashboard')
        }, 1500)
        return
      }
    }
  } catch (err) {
    // Expected while unauthenticated
  } finally {
    isCheckingStatus.value = false
  }
}

const manualCheck = async () => {
  statusCheckMessage.value = 'Checking with academic server...'
  await checkIfUnlocked()
  if (!isReopened.value) {
    setTimeout(() => {
      statusCheckMessage.value = 'This semester workspace remains locked by the Department Head. Please check back later or contact your Department Head.'
    }, 400)
  }
}
</script>

<template>
  <div class="min-h-screen w-full bg-[#f8fafc] flex flex-col justify-between text-slate-800 font-sans antialiased selection:bg-amber-100 selection:text-amber-900">
    
    <!-- ── TOP HEADER ── -->
    <header class="w-full px-6 sm:px-12 md:px-16 pt-6 pb-2">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        
        <!-- Identity: Logo & Title -->
        <div class="flex items-center gap-3 sm:gap-3.5 group cursor-pointer" @click="goHome">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white shadow-xs border border-slate-200 p-0.5 flex items-center justify-center shrink-0">
            <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-full h-full object-contain rounded-full" />
          </div>
          <div class="flex flex-col">
            <span class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight leading-none">
              Wollo <span class="text-[#5138ed]">University</span>
            </span>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium tracking-wide mt-1">
              Online Examination &amp; Academic Management Portal
            </span>
          </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex items-center gap-2 sm:gap-3">
          <router-link
            to="/maintenance"
            class="inline-flex items-center gap-1.5 text-slate-600 hover:text-amber-700 transition-colors text-xs sm:text-sm font-semibold py-1.5 px-3 rounded-lg hover:bg-slate-100 cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="hidden sm:inline">System Maintenance</span>
          </router-link>
          <button
            type="button"
            @click="goHome"
            class="inline-flex items-center gap-1.5 text-slate-600 hover:text-slate-900 transition-colors text-xs sm:text-sm font-semibold py-1.5 px-3 rounded-lg hover:bg-slate-100 cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="hidden sm:inline">Landing Page</span>
          </button>
          <button
            type="button"
            @click="goToLogin"
            class="inline-flex items-center gap-1.5 bg-[#5138ed] hover:bg-[#432dd4] text-white text-xs sm:text-sm font-bold py-2 px-4 rounded-xl shadow-xs transition-colors cursor-pointer"
          >
            <span>Sign In</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </div>

      </div>
    </header>

    <!-- ── CENTER CONTENT (Access Locked Presentation) ── -->
    <main class="w-full max-w-3xl mx-auto px-4 py-8 sm:py-12 my-auto flex flex-col items-center justify-center text-center">
      
      <!-- ── Vector Illustration: Padlock on Secure Workspace ── -->
      <div class="w-full max-w-[420px] aspect-[420/260] relative flex items-center justify-center mb-4">
        <svg viewBox="0 0 420 260" class="w-full h-full overflow-visible" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="lockGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#F59E0B" />
              <stop offset="100%" stop-color="#D97706" />
            </linearGradient>
            <linearGradient id="shieldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#FEF3C7" />
              <stop offset="100%" stop-color="#FDE68A" />
            </linearGradient>
            <linearGradient id="shackleGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#94A3B8" />
              <stop offset="100%" stop-color="#64748B" />
            </linearGradient>
          </defs>

          <!-- Soft Shadow Floor -->
          <ellipse cx="210" cy="235" rx="150" ry="14" fill="#E2E8F0" opacity="0.7" />

          <!-- Soft Golden Circular Glow -->
          <circle cx="210" cy="135" r="95" fill="url(#shieldGrad)" opacity="0.45" />

          <!-- Background Screen / Monitor Silhouette -->
          <rect x="110" y="55" width="200" height="135" rx="14" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="3" />
          <path d="M 110 75 L 310 75" stroke="#E2E8F0" stroke-width="2" />
          <circle cx="125" cy="65" r="3" fill="#EF4444" />
          <circle cx="135" cy="65" r="3" fill="#F59E0B" />
          <circle cx="145" cy="65" r="3" fill="#10B981" />

          <!-- Screen Stand -->
          <path d="M 195 190 L 195 215 L 225 215 L 225 190 Z" fill="#94A3B8" />
          <rect x="175" y="215" width="70" height="6" rx="3" fill="#64748B" />

          <!-- ── Big Center 3D Padlock ── -->
          <!-- Lock Shackle (Heavy curved loop) -->
          <path 
            d="M 185 110 L 185 85 C 185 65, 235 65, 235 85 L 235 110" 
            fill="none" 
            stroke="url(#shackleGrad)" 
            stroke-width="14" 
            stroke-linecap="round" 
          />

          <!-- Padlock Body -->
          <rect x="170" y="105" width="80" height="70" rx="14" fill="url(#lockGrad)" stroke="#B45309" stroke-width="2" />
          
          <!-- Glossy Top Highlight -->
          <rect x="175" y="110" width="70" height="8" rx="4" fill="#FDE68A" opacity="0.6" />

          <!-- Keyhole Outer Circle -->
          <circle cx="210" cy="138" r="9" fill="#78350F" />
          <!-- Keyhole Bottom Slot -->
          <polygon points="206,140 214,140 212,154 208,154" fill="#78350F" />

          <!-- Orbiting Protection Badge -->
          <g transform="translate(255, 65)">
            <circle cx="16" cy="16" r="16" fill="#EF4444" />
            <path d="M 11 16 L 21 16" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" />
          </g>

          <g transform="translate(135, 150)">
            <circle cx="14" cy="14" r="14" fill="#10B981" opacity="0.9" />
            <path d="M 9 14 L 13 18 L 19 10" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </g>
        </svg>
      </div>

      <!-- ── Status Badge ── -->
      <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold mb-4 shadow-2xs">
        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
        <span>Semester Workspace Access Locked</span>
      </div>

      <!-- ── Main Heading ── -->
      <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-slate-900 tracking-tight mb-3">
        Access <span class="text-amber-600">Locked</span>
      </h1>

      <!-- ── Primary Description ── -->
      <p class="text-slate-600 text-sm sm:text-base leading-relaxed max-w-xl mx-auto mb-5">
        The Department Head has closed access to this semester workspace. Your continuous assessment records, exam question banks, and student marks are currently finalized and restricted from modification.
      </p>

      <!-- ── Decorative Divider (── 🔒 ──) ── -->
      <div class="flex items-center justify-center gap-3 w-48 sm:w-56 mx-auto mb-6">
        <div class="h-[1.5px] flex-1 bg-amber-300 rounded-full"></div>
        <div class="p-1 rounded-full text-amber-600 bg-amber-50 border border-amber-200">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <div class="h-[1.5px] flex-1 bg-amber-300 rounded-full"></div>
      </div>

      <!-- ── Information Card with Specific Context ── -->
      <div class="w-full max-w-lg bg-white rounded-2xl border border-amber-200 p-4 sm:p-5 text-left mb-6 shadow-xs">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </div>
          <div class="min-w-0 flex-1 space-y-1.5">
            <h3 class="text-sm font-bold text-slate-800">Workspace Restriction Notice</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
              {{ lockMessage }}
            </p>
            <div v-if="courseTitle || academicTerm" class="pt-2 border-t border-slate-100 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-500">
              <span v-if="courseTitle" class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md">
                Course: {{ courseTitle }}
              </span>
              <span v-if="academicTerm" class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md">
                Term: {{ academicTerm }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Reopened Alert if Reopened -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div 
          v-if="isReopened" 
          class="mb-6 px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs sm:text-sm font-bold flex items-center gap-2 shadow-xs"
        >
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
          <span>{{ statusCheckMessage || 'Semester workspace has been reopened! Redirecting...' }}</span>
        </div>
      </Transition>

      <!-- ── Action Buttons ── -->
      <div class="flex flex-col sm:flex-row items-center gap-3 w-full max-w-md justify-center">
        <button
          type="button"
          @click="goToLogin"
          class="w-full sm:w-auto flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#5138ed] hover:bg-[#432dd4] text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors min-h-[44px] cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
          <span>Return to Sign In</span>
        </button>

        <button
          type="button"
          @click="manualCheck"
          :disabled="isCheckingStatus"
          class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs sm:text-sm font-semibold transition-colors min-h-[44px] cursor-pointer disabled:opacity-50"
        >
          <svg v-if="isCheckingStatus" class="w-4 h-4 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          <svg v-else class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Check Status</span>
        </button>
      </div>

      <!-- Feedback note if manual check conducted -->
      <p v-if="statusCheckMessage && !isReopened" class="mt-3 text-[11px] text-slate-500 font-medium">
        {{ statusCheckMessage }}
      </p>

      <!-- ── Link Under Maintenance / Operational Status ── -->
      <div class="mt-8 pt-6 border-t border-slate-200/80 flex flex-wrap items-center justify-center gap-4 text-xs font-semibold text-slate-500">
        <router-link 
          to="/maintenance" 
          class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-700 hover:underline transition-colors cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span>System Maintenance &bull; Check Operational Status</span>
        </router-link>
        <span class="text-slate-300">&bull;</span>
        <button 
          type="button" 
          @click="goHome" 
          class="hover:text-slate-800 transition-colors cursor-pointer"
        >
          Wollo University Portal Home
        </button>
      </div>

    </main>

    <!-- ── FOOTER: Links including System Maintenance ── -->
    <footer class="w-full py-4 px-6 text-center text-slate-400 text-xs flex flex-wrap items-center justify-center gap-3">
      <router-link to="/maintenance" class="text-slate-500 hover:text-blue-600 transition-colors font-medium">
        System Maintenance Page
      </router-link>
      <span class="text-slate-300">&bull;</span>
      <router-link to="/login" class="text-slate-500 hover:text-blue-600 transition-colors">
        Sign In
      </router-link>
      <span class="text-slate-300">&bull;</span>
      <span>&copy; {{ new Date().getFullYear() }} Wollo University Online Examination System. All rights reserved.</span>
    </footer>

  </div>
</template>

<style scoped>
/* Scoped styles */
</style>

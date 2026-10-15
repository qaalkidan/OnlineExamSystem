<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/authStore'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()
const authStore = useAuthStore()

// State for auto-recovery & status
const isLifted = ref(false)
let statusPollingTimer: any = null

// Administrator modal state
const showAdminModal = ref(false)
const adminLogin = ref('')
const adminPassword = ref('')
const isSubmittingAdmin = ref(false)
const adminError = ref<string | null>(null)
const showPassword = ref(false)

// Navigate to Home / Landing
const goHome = () => {
  router.push('/')
}

// Check maintenance status silently
const checkStatusSilently = async () => {
  try {
    const res = await apiClient.get('/system/public-status')
    if (res.data?.maintenanceMode === false) {
      isLifted.value = true
      if (statusPollingTimer) clearInterval(statusPollingTimer)
      setTimeout(() => {
        router.push('/login')
      }, 1200)
    }
  } catch (err) {
    // Keep polling silently
  }
}

// Handle Administrator sign in from maintenance mode
const handleAdminLogin = async () => {
  if (!adminLogin.value || !adminPassword.value) {
    adminError.value = 'Please enter administrator username or email and password.'
    return
  }
  isSubmittingAdmin.value = true
  adminError.value = null
  try {
    await authStore.login({
      login: adminLogin.value,
      password: adminPassword.value,
      role: 'staff'
    })
    showAdminModal.value = false
    // AuthStore login will redirect appropriately (e.g., to /admin/dashboard or /admin/settings)
  } catch (err: any) {
    const resp = err.response?.data
    adminError.value = resp?.errors?.login?.[0] || resp?.message || 'Authentication failed. Please verify administrator credentials.'
  } finally {
    isSubmittingAdmin.value = false
  }
}

// Keyboard shortcut (Alt+A or Ctrl+Shift+A) to open Admin login
const handleKeydown = (e: KeyboardEvent) => {
  if ((e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'a') || (e.altKey && e.key.toLowerCase() === 'a')) {
    e.preventDefault()
    showAdminModal.value = true
  }
}

onMounted(() => {
  checkStatusSilently()
  statusPollingTimer = setInterval(checkStatusSilently, 10000)
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  if (statusPollingTimer) clearInterval(statusPollingTimer)
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="min-h-screen w-full bg-white flex flex-col justify-between text-slate-800 font-sans select-none antialiased">
    
    <!-- ── TOP HEADER ── -->
    <header class="w-full px-6 sm:px-12 md:px-16 pt-6 pb-2">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        
        <!-- Left: Logo & Identity -->
        <div class="flex items-center gap-3 sm:gap-3.5 group cursor-pointer" @click="goHome">
          <!-- Wollo University Logo & Cap Emblem -->
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-blue-50/80 p-0.5 border border-blue-100 flex items-center justify-center shrink-0 shadow-2xs">
            <img src="@/assets/images/logo.png" alt="Wollo University" class="w-full h-full object-contain rounded-full" />
          </div>
          <div class="flex flex-col">
            <div class="flex items-center gap-2">
              <span class="text-lg sm:text-xl font-black text-slate-900 tracking-tight leading-none">
                Exam<span class="text-blue-600">Portal</span>
              </span>
            </div>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium tracking-wide mt-0.5">
              Learn &bull; Take Exam &bull; Succeed
            </span>
          </div>
        </div>

        <!-- Right: Back to Home -->
        <button
          type="button"
          @click="goHome"
          class="inline-flex items-center gap-2 text-slate-600 hover:text-blue-600 transition-colors text-xs sm:text-sm font-medium py-1.5 px-2.5 rounded-lg hover:bg-slate-50 cursor-pointer"
        >
          <!-- Outline House Icon -->
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>Back to Home</span>
        </button>

      </div>
    </header>

    <!-- ── CENTER CONTENT (Pure White, No Cards) ── -->
    <main class="w-full max-w-4xl mx-auto px-4 py-6 sm:py-8 my-auto flex flex-col items-center justify-center text-center">
      
      <!-- ── Vector Illustration Matching Reference ── -->
      <div class="w-full max-w-[520px] sm:max-w-[560px] aspect-[600/360] relative flex items-center justify-center">
        <svg 
          viewBox="0 0 600 360" 
          class="w-full h-full overflow-visible"
          xmlns="http://www.w3.org/2000/svg"
        >
          <defs>
            <!-- Hazard stripes pattern for construction barricade -->
            <pattern id="hazardStripe" width="16" height="16" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
              <rect width="8" height="16" fill="#FACC15" />
              <rect x="8" width="8" height="16" fill="#1E293B" />
            </pattern>

            <!-- Plant leaf gradients -->
            <linearGradient id="leafGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#4ADE80" />
              <stop offset="100%" stop-color="#16A34A" />
            </linearGradient>
            <linearGradient id="leafGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#22C55E" />
              <stop offset="100%" stop-color="#15803D" />
            </linearGradient>

            <!-- Helmet gradient -->
            <linearGradient id="helmetGrad" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stop-color="#FDE047" />
              <stop offset="50%" stop-color="#FACC15" />
              <stop offset="100%" stop-color="#EAB308" />
            </linearGradient>
          </defs>

          <!-- ── 1. Soft Background Blob ── -->
          <!-- Ground puddle / soft floor shadow -->
          <ellipse cx="295" cy="272" rx="205" ry="18" fill="#EEF5FE" />

          <!-- Organic soft cloud shape behind window -->
          <path 
            d="M 195 210
               C 170 170, 185 110, 240 95
               C 275 85, 335 70, 390 90
               C 435 105, 455 145, 435 195
               C 475 220, 460 265, 400 270
               L 205 270
               C 175 260, 175 225, 195 210 Z"
            fill="#EEF5FE" 
          />

          <!-- 3x3 Dot Grid (Top-Left) -->
          <g fill="#93C5FD">
            <circle cx="196" cy="118" r="2.5" />
            <circle cx="210" cy="118" r="2.5" />
            <circle cx="224" cy="118" r="2.5" />
            <circle cx="196" cy="132" r="2.5" />
            <circle cx="210" cy="132" r="2.5" />
            <circle cx="224" cy="132" r="2.5" />
            <circle cx="196" cy="146" r="2.5" />
            <circle cx="210" cy="146" r="2.5" />
            <circle cx="224" cy="146" r="2.5" />
          </g>

          <!-- Floating Plus Signs (+) -->
          <g stroke="#60A5FA" stroke-width="2" stroke-linecap="round">
            <!-- Top-right plus -->
            <line x1="368" y1="88" x2="368" y2="98" />
            <line x1="363" y1="93" x2="373" y2="93" />
            <!-- Mid-right plus -->
            <line x1="418" y1="135" x2="418" y2="145" />
            <line x1="413" y1="140" x2="423" y2="140" />
            <!-- Lower-left dot -->
            <circle cx="186" cy="242" r="1.5" fill="#93C5FD" stroke="none" />
          </g>

          <!-- ── 2. Potted Plant (Behind window right edge) ── -->
          <g id="plant">
            <!-- Center tall leaf -->
            <path d="M 400 235 C 395 185, 410 145, 402 120 C 420 150, 418 200, 403 235 Z" fill="url(#leafGrad1)" />
            <!-- Right large leaf -->
            <path d="M 404 225 C 418 190, 442 165, 435 155 C 420 175, 415 210, 405 230 Z" fill="url(#leafGrad2)" />
            <!-- Left broad leaf -->
            <path d="M 398 225 C 382 195, 368 180, 368 175 C 385 185, 395 210, 399 230 Z" fill="url(#leafGrad2)" />
            <!-- Small lower right leaf -->
            <path d="M 405 232 C 418 215, 428 205, 426 200 C 415 212, 410 225, 406 235 Z" fill="url(#leafGrad1)" />

            <!-- Flower Pot -->
            <!-- Pot Rim -->
            <rect x="382" y="235" width="40" height="6" rx="2" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="1.5" />
            <!-- Pot Base (Tapered) -->
            <path d="M 385 241 L 390 268 C 390 270, 414 270, 414 268 L 419 241 Z" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1.5" />
          </g>

          <!-- ── 3. Browser Window (Center) ── -->
          <!-- Window Body & Royal Blue Border -->
          <rect x="224" y="118" width="152" height="136" rx="14" fill="#FFFFFF" stroke="#437EF7" stroke-width="6" />

          <!-- Window Header Bar (Blue top) -->
          <path d="M 224 132 A 14 14 0 0 1 238 118 L 362 118 A 14 14 0 0 1 376 132 L 376 136 L 224 136 Z" fill="#437EF7" />

          <!-- 3 White Window Control Dots -->
          <circle cx="236" cy="127" r="3" fill="#FFFFFF" />
          <circle cx="245" cy="127" r="3" fill="#FFFFFF" />
          <circle cx="254" cy="127" r="3" fill="#FFFFFF" />

          <!-- Center Rotating Gear -->
          <g class="spin-gear">
            <!-- 8-tooth gear path centered at (300, 168) -->
            <path 
              d="M 295 145 L 305 145 L 306 150 A 17 17 0 0 1 311 153 L 316 150 L 322 156 L 319 161 A 17 17 0 0 1 321 166 L 326 168 L 326 177 L 321 179 A 17 17 0 0 1 319 184 L 322 189 L 316 195 L 311 192 A 17 17 0 0 1 306 195 L 305 200 L 295 200 L 294 195 A 17 17 0 0 1 289 192 L 284 195 L 278 189 L 281 184 A 17 17 0 0 1 279 179 L 274 177 L 274 168 L 279 166 A 17 17 0 0 1 281 161 L 278 156 L 284 150 L 289 153 A 17 17 0 0 1 294 150 Z"
              fill="#3B82F6" 
            />
            <!-- Center hole in gear -->
            <circle cx="300" cy="172.5" r="7.5" fill="#FFFFFF" />
          </g>

          <!-- Progress Bar (below gear inside window) -->
          <!-- Track -->
          <rect x="254" y="206" width="92" height="10" rx="5" fill="#DBEAFE" />
          <!-- Active Pill with subtle shimmer animation -->
          <rect x="254" y="206" width="48" height="10" rx="5" fill="#2563EB" class="progress-pill" />

          <!-- ── 4. Construction Barricade (Left Foreground) ── -->
          <g id="barricade">
            <!-- Left Leg (A-Frame) -->
            <path d="M 200 206 L 191 254 L 198 254 L 203 226 L 208 254 L 215 254 L 206 206 Z" fill="#1E293B" />
            <rect x="194" y="235" width="18" height="4" fill="#1E293B" />

            <!-- Right Leg (A-Frame) -->
            <path d="M 238 206 L 229 254 L 236 254 L 241 226 L 246 254 L 253 254 L 244 206 Z" fill="#1E293B" />
            <rect x="232" y="235" width="18" height="4" fill="#1E293B" />

            <!-- Main Hazard Striped Beam -->
            <rect x="188" y="212" width="62" height="22" rx="4" fill="url(#hazardStripe)" stroke="#1E293B" stroke-width="1.5" />
          </g>

          <!-- ── 5. Yellow Construction Helmet / Hard Hat (Right Foreground) ── -->
          <g id="helmet">
            <!-- Helmet Dome -->
            <path d="M 338 250 C 336 222, 386 220, 388 250 Z" fill="url(#helmetGrad)" />
            <!-- Center Ridge -->
            <path d="M 358 223 C 358 221, 366 221, 366 223 L 364 250 L 360 250 Z" fill="#FEF08A" />
            <!-- Helmet Brim -->
            <ellipse cx="363" cy="251" rx="27" ry="5" fill="#EAB308" />
            <ellipse cx="363" cy="250" rx="26" ry="4" fill="#FACC15" />
          </g>
        </svg>
      </div>

      <!-- ── Typography matching Reference Image ── -->
      <!-- "Under Maintenance" Heading -->
      <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-slate-900 tracking-tight mt-6 sm:mt-8 mb-3">
        Under <span class="text-blue-600">Maintenance</span>
      </h1>

      <!-- Subtitle -->
      <p class="text-slate-500 text-sm sm:text-base md:text-lg font-normal leading-relaxed max-w-md mx-auto">
        The Exam Portal is currently under maintenance.<br />
        We'll be back soon!
      </p>

      <!-- ── Decorative Bottom Divider (── ⚙ ──) ── -->
      <div class="flex items-center justify-center gap-3 w-44 sm:w-52 mx-auto mt-7 sm:mt-8">
        <div class="h-[1.5px] flex-1 bg-blue-300/80 rounded-full"></div>
        
        <!-- Discreet administrator trigger gear -->
        <button 
          type="button"
          @click="showAdminModal = true" 
          class="p-1 rounded-full text-blue-600 hover:text-blue-700 hover:bg-blue-50 transition-all cursor-pointer group"
          title="Administrator Access"
        >
          <svg class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z" />
          </svg>
        </button>

        <div class="h-[1.5px] flex-1 bg-blue-300/80 rounded-full"></div>
      </div>

      <!-- Auto recovery toast notification if maintenance mode ends -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div 
          v-if="isLifted" 
          class="mt-6 px-4 py-2.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-xs"
        >
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
          <span>Maintenance complete! Redirecting back to the exam portal...</span>
        </div>
      </Transition>

    </main>

    <!-- Bottom Spacing / Subtle System Info -->
    <footer class="w-full py-4 text-center text-xs text-slate-400">
      <!-- Empty/clean to keep pristine white look like the reference image -->
    </footer>

    <!-- ── MODAL: ADMINISTRATOR LOGIN ── -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showAdminModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/30 backdrop-blur-xs"
        @click.self="showAdminModal = false"
      >
        <div class="bg-white text-slate-900 rounded-2xl w-full max-w-md p-6 sm:p-8 shadow-xl relative border border-slate-200">
          
          <!-- Close button -->
          <button 
            type="button" 
            @click="showAdminModal = false"
            class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Modal Header -->
          <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900 leading-tight">Administrator Access</h3>
              <p class="text-xs text-slate-500">Sign in to manage system operations & settings</p>
            </div>
          </div>

          <!-- Error Alert -->
          <div v-if="adminError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ adminError }}</span>
          </div>

          <!-- Form -->
          <form @submit.prevent="handleAdminLogin" class="space-y-4">
            <div>
              <label for="admin-login" class="block text-xs font-semibold text-slate-700 mb-1.5">Admin Email or Username</label>
              <input
                id="admin-login"
                type="text"
                v-model="adminLogin"
                placeholder="admin@wollo.edu.et"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                required
              />
            </div>

            <div>
              <label for="admin-password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
              <div class="relative">
                <input
                  id="admin-password"
                  :type="showPassword ? 'text' : 'password'"
                  v-model="adminPassword"
                  placeholder="Enter administrator password"
                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white pr-10"
                  required
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                >
                  <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                  </svg>
                </button>
              </div>
            </div>

            <div class="pt-2">
              <button
                type="submit"
                :disabled="isSubmittingAdmin"
                class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-sm shadow-xs transition-colors disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
              >
                <span v-if="isSubmittingAdmin" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>{{ isSubmittingAdmin ? 'Signing In...' : 'Sign In as Administrator' }}</span>
              </button>
            </div>
          </form>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
@keyframes spin-clockwise {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.spin-gear {
  transform-origin: 300px 172.5px;
  animation: spin-clockwise 10s linear infinite;
}

@keyframes progress-shimmer {
  0% {
    width: 28px;
    opacity: 0.9;
  }
  50% {
    width: 68px;
    opacity: 1;
  }
  100% {
    width: 28px;
    opacity: 0.9;
  }
}

.progress-pill {
  animation: progress-shimmer 3.2s ease-in-out infinite;
}
</style>

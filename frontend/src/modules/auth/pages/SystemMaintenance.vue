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
  statusPollingTimer = setInterval(checkStatusSilently, 8000)
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
    <header class="w-full px-6 sm:px-12 md:px-16 pt-7 pb-2">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        
        <!-- Left: Logo & Identity Matching Reference Image -->
        <div class="flex items-center gap-3 sm:gap-3.5 group cursor-pointer" @click="goHome">
          <!-- 3D Isometric Blue Graduation Cap -->
          <svg class="w-10 h-10 sm:w-11 sm:h-11 shrink-0" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Mortarboard Diamond Top -->
            <path d="M 22 6 L 40 14.5 L 22 23 L 4 14.5 Z" fill="#2563EB" />
            <!-- Bottom Edges of Diamond (3D depth) -->
            <path d="M 4 14.5 L 22 23 L 22 24.5 L 4 16 Z" fill="#1D4ED8" />
            <path d="M 22 23 L 40 14.5 L 40 16 L 22 24.5 Z" fill="#1E40AF" />
            <!-- Skullcap (Round Dome) -->
            <path d="M 12 19 L 12 26 C 12 32.5 32 32.5 32 26 L 32 19 Z" fill="#1E3A8A" />
            <!-- Skullcap bottom lip -->
            <ellipse cx="22" cy="26" rx="10" ry="2.5" fill="#172554" />
            <!-- Center Button & Tassel -->
            <ellipse cx="22" cy="14.5" rx="2" ry="1.2" fill="#60A5FA" />
            <!-- Tassel String -->
            <path d="M 22 14.5 Q 16 16.5 10 24" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" fill="none" />
            <!-- Hanging Tassel Band -->
            <path d="M 8.5 24 L 11.5 24 L 12 31.5 L 8 31.5 Z" fill="#60A5FA" />
            <circle cx="10" cy="24" r="1.5" fill="#3B82F6" />
          </svg>
          <div class="flex flex-col">
            <span class="text-xl sm:text-22 font-extrabold text-slate-900 tracking-tight leading-none">
              Exam<span class="text-blue-600">Portal</span>
            </span>
            <span class="text-[11px] sm:text-xs text-slate-400 font-medium tracking-wide mt-1">
              Learn &bull; Take Exam &bull; Succeed
            </span>
          </div>
        </div>

        <!-- Right: Back to Home -->
        <button
          type="button"
          @click="goHome"
          class="inline-flex items-center gap-2 text-slate-600 hover:text-blue-600 transition-colors text-xs sm:text-sm font-semibold py-1.5 px-2.5 rounded-lg hover:bg-slate-50 cursor-pointer"
        >
          <!-- Outline House Icon -->
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span>Back to Home</span>
        </button>

      </div>
    </header>

    <!-- ── CENTER CONTENT (Pure White, Identical to Reference Image) ── -->
    <main class="w-full max-w-4xl mx-auto px-4 py-4 sm:py-6 my-auto flex flex-col items-center justify-center text-center">
      
      <!-- ── Vector Illustration Matching Reference ── -->
      <div class="w-full max-w-[620px] sm:max-w-[660px] aspect-[600/360] relative flex items-center justify-center">
        <svg 
          viewBox="0 0 600 360" 
          class="w-full h-full overflow-visible"
          xmlns="http://www.w3.org/2000/svg"
        >
          <defs>
            <!-- Barricade Hazard Stripes Pattern -->
            <pattern id="hazardStripe" width="18" height="18" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
              <rect width="9" height="18" fill="#FACC15" />
              <rect x="9" width="9" height="18" fill="#1E293B" />
            </pattern>

            <!-- Plant Leaf Gradients -->
            <linearGradient id="leafGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#4ADE80" />
              <stop offset="100%" stop-color="#16A34A" />
            </linearGradient>
            <linearGradient id="leafGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#22C55E" />
              <stop offset="100%" stop-color="#15803D" />
            </linearGradient>
          </defs>

          <!-- ── 1. Soft Background Blob ── -->
          <!-- Ground puddle / floor shadow -->
          <ellipse cx="295" cy="276" rx="225" ry="18" fill="#EEF5FE" />

          <!-- Organic soft cloud shape behind window -->
          <path 
            d="M 180 205
               C 150 155, 175 100, 235 85
               C 275 75, 345 65, 405 85
               C 455 102, 475 145, 455 195
               C 485 225, 470 268, 410 274
               L 190 274
               C 155 262, 155 225, 180 205 Z"
            fill="#EEF5FE" 
          />

          <!-- 3x3 Dot Matrix (Top-Left of Cloud) -->
          <g fill="#93C5FD">
            <circle cx="198" cy="112" r="3" />
            <circle cx="212" cy="112" r="3" />
            <circle cx="226" cy="112" r="3" />
            <circle cx="198" cy="126" r="3" />
            <circle cx="212" cy="126" r="3" />
            <circle cx="226" cy="126" r="3" />
            <circle cx="198" cy="140" r="3" />
            <circle cx="212" cy="140" r="3" />
            <circle cx="226" cy="140" r="3" />
          </g>

          <!-- Floating Plus Signs (+) -->
          <g stroke="#60A5FA" stroke-width="2.5" stroke-linecap="round">
            <!-- Top-right plus -->
            <line x1="368" y1="84" x2="368" y2="94" />
            <line x1="363" y1="89" x2="373" y2="89" />
            <!-- Mid-right plus -->
            <line x1="418" y1="130" x2="418" y2="140" />
            <line x1="413" y1="135" x2="423" y2="135" />
            <!-- Lower-left dot -->
            <circle cx="186" cy="245" r="2" fill="#93C5FD" stroke="none" />
          </g>

          <!-- ── 2. Potted Plant (Behind window right edge) ── -->
          <g id="plant">
            <!-- Tall center leaf -->
            <path d="M 404 235 C 398 175, 416 130, 404 105 C 428 140, 424 195, 408 235 Z" fill="url(#leafGrad1)" />
            <!-- Big right leaf -->
            <path d="M 408 222 C 426 182, 454 152, 446 142 C 428 165, 422 205, 410 228 Z" fill="url(#leafGrad2)" />
            <!-- Left spreading leaf -->
            <path d="M 398 222 C 378 188, 362 170, 362 165 C 382 178, 395 205, 400 228 Z" fill="url(#leafGrad2)" />
            <!-- Low accent leaf -->
            <path d="M 408 230 C 424 210, 436 198, 434 192 C 422 206, 415 220, 410 234 Z" fill="url(#leafGrad1)" />

            <!-- White Flower Pot -->
            <!-- Top Rim -->
            <rect x="382" y="235" width="44" height="7" rx="2" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5" />
            <!-- Pot Body -->
            <path d="M 385 242 L 390 270 C 390 272, 418 272, 418 270 L 423 242 Z" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.5" />
          </g>

          <!-- ── 3. Browser Window (Center) ── -->
          <!-- Window Body & Royal Blue Border -->
          <rect x="224" y="108" width="154" height="144" rx="16" fill="#FFFFFF" stroke="#437EF7" stroke-width="7" />

          <!-- Window Header Bar (Solid Blue Top) -->
          <path d="M 224 124 A 16 16 0 0 1 240 108 L 362 108 A 16 16 0 0 1 378 124 L 378 128 L 224 128 Z" fill="#437EF7" />

          <!-- 3 White Header Dots -->
          <circle cx="238" cy="118" r="3" fill="#FFFFFF" />
          <circle cx="247" cy="118" r="3" fill="#FFFFFF" />
          <circle cx="256" cy="118" r="3" fill="#FFFFFF" />

          <!-- Center Rotating Gear -->
          <g class="spin-gear">
            <!-- 8-tooth Royal Blue Gear centered at (301, 158) -->
            <path 
              d="M 295 136 L 307 136 L 308 141 A 18 18 0 0 1 314 144 L 319 141 L 326 148 L 323 153 A 18 18 0 0 1 326 159 L 331 161 L 331 171 L 326 173 A 18 18 0 0 1 323 179 L 326 184 L 319 191 L 314 188 A 18 18 0 0 1 308 191 L 307 196 L 295 196 L 294 191 A 18 18 0 0 1 288 188 L 283 191 L 276 184 L 279 179 A 18 18 0 0 1 276 173 L 271 171 L 271 161 L 276 159 A 18 18 0 0 1 279 153 L 276 148 L 283 141 L 288 144 A 18 18 0 0 1 294 141 Z"
              fill="#3B82F6" 
            />
            <!-- Hollow Gear Center Circle -->
            <circle cx="301" cy="166" r="8" fill="#FFFFFF" />
          </g>

          <!-- Progress Bar (below gear inside window) -->
          <!-- Track -->
          <rect x="250" y="202" width="102" height="11" rx="5.5" fill="#DBEAFE" />
          <!-- Filled Active Portion with smooth shimmer -->
          <rect x="250" y="202" width="54" height="11" rx="5.5" fill="#2563EB" class="progress-pill" />

          <!-- ── 4. Construction Barricade (Left Foreground) ── -->
          <g id="barricade">
            <!-- Left A-Frame Leg -->
            <path d="M 201 198 L 192 258 L 200 258 L 205 224 L 210 258 L 218 258 L 208 198 Z" fill="#1E293B" />
            <rect x="196" y="234" width="18" height="5" fill="#1E293B" />

            <!-- Right A-Frame Leg -->
            <path d="M 241 198 L 232 258 L 240 258 L 245 224 L 250 258 L 258 258 L 248 198 Z" fill="#1E293B" />
            <rect x="236" y="234" width="18" height="5" fill="#1E293B" />

            <!-- Hazard Striped Horizontal Beam -->
            <rect x="186" y="206" width="68" height="24" rx="4" fill="url(#hazardStripe)" stroke="#1E293B" stroke-width="1.8" />
          </g>

          <!-- ── 5. Construction Hard Hat / Helmet (Right Foreground) ── -->
          <g id="helmet">
            <!-- Main Rounded Dome -->
            <path d="M 335 252 C 332 216, 394 214, 396 252 Z" fill="#FACC15" />
            <path d="M 337 250 C 335 220, 392 218, 394 250 Z" fill="#FDE047" />
            <!-- Center Top Ridge -->
            <path d="M 360 218 C 360 216, 370 216, 370 218 L 368 252 L 362 252 Z" fill="#FEF08A" />
            <!-- Curved Brim -->
            <path d="M 328 252 C 332 247, 400 247, 404 252 C 398 258, 334 258, 328 252 Z" fill="#EAB308" />
            <path d="M 330 251 C 334 248, 398 248, 402 251 C 397 255, 335 255, 330 251 Z" fill="#FACC15" />
          </g>
        </svg>
      </div>

      <!-- ── Typography matching Reference Image ── -->
      <!-- "Under Maintenance" Heading -->
      <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-6 sm:mt-8 mb-3">
        Under <span class="text-blue-600">Maintenance</span>
      </h1>

      <!-- Subtitle -->
      <p class="text-slate-500 text-base sm:text-lg font-normal leading-relaxed max-w-md mx-auto">
        The Exam Portal is currently under maintenance.<br />
        We'll be back soon!
      </p>

      <!-- ── Decorative Bottom Divider (── ⚙ ──) ── -->
      <div class="flex items-center justify-center gap-3 w-48 sm:w-56 mx-auto mt-7 sm:mt-8">
        <div class="h-[1.5px] flex-1 bg-blue-300 rounded-full"></div>
        
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

        <div class="h-[1.5px] flex-1 bg-blue-300 rounded-full"></div>
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

    <!-- Bottom Spacing / Clean look matching reference -->
    <footer class="w-full h-10"></footer>

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
  transform-origin: 301px 166px;
  animation: spin-clockwise 9s linear infinite;
}

@keyframes progress-shimmer {
  0% {
    width: 32px;
    opacity: 0.9;
  }
  50% {
    width: 76px;
    opacity: 1;
  }
  100% {
    width: 32px;
    opacity: 0.9;
  }
}

.progress-pill {
  animation: progress-shimmer 3s ease-in-out infinite;
}
</style>

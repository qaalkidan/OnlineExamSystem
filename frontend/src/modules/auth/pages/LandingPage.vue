<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../store/authStore'
import wolloLogo from '@/assets/images/logo.png'
import campusBg from '@/assets/images/wollo-campus.png'

const router = useRouter()
const authStore = useAuthStore()

const showSupportModal = ref(false)

onMounted(async () => {
  await authStore.checkPublicStatus()
  if (authStore.isMaintenanceActive) {
    router.replace('/maintenance')
  }
})

const goToLogin = () => {
  router.push('/login')
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
      <!-- Directional navy backdrop on the left for maximum text contrast, smoothly fading to transparent in the center to preserve full sunlight on campus building -->
      <div class="absolute inset-0 bg-gradient-to-r from-[#071F42]/90 via-[#071F42]/60 to-transparent"></div>
      <div class="absolute inset-0 bg-gradient-to-b from-blue-950/25 via-transparent to-blue-950/30"></div>
    </div>

    <!-- ── Header ── -->
    <header class="relative z-20 w-full px-6 sm:px-12 lg:px-16 py-5 sm:py-6 flex items-center justify-between max-w-7xl mx-auto">
      <!-- Left side: University logo + Branding -->
      <div class="flex items-center gap-3 sm:gap-4">
        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-white/10 p-0.5 border border-white/70 shadow-md shrink-0 flex items-center justify-center overflow-hidden">
          <img
            :src="wolloLogo"
            alt="Wollo University Logo"
            class="w-full h-full object-contain rounded-full"
          />
        </div>
        <div>
          <h1 class="text-base sm:text-lg font-bold text-white tracking-tight leading-tight drop-shadow-md">
            Wollo University
          </h1>
          <p class="text-[11px] sm:text-xs text-white/90 font-medium tracking-wide drop-shadow-sm">
            Online Exam System
          </p>
        </div>
      </div>

      <!-- Right side: Slogan + Top Right Login Button -->
      <div class="flex items-center gap-4 sm:gap-6">
        <span class="hidden md:inline-block text-xs sm:text-sm font-medium text-white/90 tracking-wider drop-shadow-md">
          Better Learning <span class="text-white/40 mx-1.5">|</span> Brighter Future
        </span>
        <button
          type="button"
          @click="goToLogin"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1769E0] hover:bg-[#1258be] active:bg-[#0e489e] text-white font-semibold text-xs sm:text-sm shadow-lg shadow-blue-900/30 transition-all hover:scale-[1.02] cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
          </svg>
          <span>Log In</span>
        </button>
      </div>
    </header>

    <!-- ── Main Hero Content ── -->
    <main class="relative z-20 flex-1 flex items-center max-w-7xl mx-auto w-full px-6 sm:px-12 lg:px-16 py-8 sm:py-14">
      <div class="w-full max-w-2xl text-left">
        
        <!-- Welcome Badge -->
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-600/50 border border-blue-400/50 text-white text-xs font-semibold backdrop-blur-md mb-4 shadow-sm">
          <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 3L1 9L12 15L21 10.09V17H23V9M5 13.18V17.18C5 19.94 8.13 22 12 22C15.87 22 19 19.94 19 17.18V13.18L12 17L5 13.18Z" />
          </svg>
          <span>Welcome to Wollo University</span>
        </div>

        <!-- Main Heading: High-contrast, vibrant, and razor-sharp -->
        <h2 class="text-3xl sm:text-5xl lg:text-[3.5rem] font-extrabold text-white tracking-tight leading-[1.12] mb-5 drop-shadow-[0_2px_8px_rgba(0,0,0,0.85)]">
          Online <span class="text-amber-400 drop-shadow-[0_2px_10px_rgba(245,158,11,0.5)]">Exam</span> System
        </h2>

        <!-- Description: High visibility -->
        <p class="text-sm sm:text-base lg:text-lg text-white/95 leading-relaxed mb-8 max-w-xl font-normal drop-shadow-[0_1px_4px_rgba(0,0,0,0.8)]">
          Secure, reliable and easy way to take your examinations. Access scheduled assessments anytime, anywhere across university departments.
        </p>

        <!-- Call to Action Buttons -->
        <div class="flex flex-wrap items-center gap-4 mb-10 sm:mb-12">
          <button
            type="button"
            @click="goToLogin"
            class="inline-flex items-center gap-2.5 px-6 py-3.5 rounded-xl bg-[#1769E0] hover:bg-[#1258be] active:bg-[#0e489e] text-white font-bold text-sm shadow-xl shadow-blue-900/40 transition-all hover:scale-[1.02] cursor-pointer"
          >
            <span>Enter Examination Portal</span>
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>

          <button
            type="button"
            @click="showSupportModal = true"
            class="inline-flex items-center gap-2 px-5 py-3.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/25 backdrop-blur-md text-white font-semibold text-sm transition-all cursor-pointer"
          >
            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Need Help?</span>
          </button>
        </div>

        <!-- 3 Feature Highlights: Frosted glass panels for 100% clarity -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4 max-w-2xl">
          <!-- Feature 1: Secure -->
          <div class="flex flex-col items-start bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md rounded-2xl p-4 shadow-lg transition-all">
            <div class="w-10 h-10 rounded-xl bg-blue-500/25 border border-blue-400/40 flex items-center justify-center text-white mb-2.5 shadow-xs">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-sm font-bold text-white mb-0.5 drop-shadow-sm">Secure</h3>
            <p class="text-xs text-white/90 leading-snug">Your data is protected with enterprise grade security.</p>
          </div>

          <!-- Feature 2: Fast & Reliable -->
          <div class="flex flex-col items-start bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md rounded-2xl p-4 shadow-lg transition-all">
            <div class="w-10 h-10 rounded-xl bg-blue-500/25 border border-blue-400/40 flex items-center justify-center text-white mb-2.5 shadow-xs">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-sm font-bold text-white mb-0.5 drop-shadow-sm">Fast & Reliable</h3>
            <p class="text-xs text-white/90 leading-snug">A smooth and stable examination experience.</p>
          </div>

          <!-- Feature 3: Any Device -->
          <div class="flex flex-col items-start bg-white/10 hover:bg-white/15 border border-white/20 backdrop-blur-md rounded-2xl p-4 shadow-lg transition-all">
            <div class="w-10 h-10 rounded-xl bg-blue-500/25 border border-blue-400/40 flex items-center justify-center text-white mb-2.5 shadow-xs">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-sm font-bold text-white mb-0.5 drop-shadow-sm">Any Device</h3>
            <p class="text-xs text-white/90 leading-snug">Access from desktop, laptop, tablet, or mobile.</p>
          </div>
        </div>

      </div>
    </main>

    <!-- ── Footer ── -->
    <footer class="relative z-20 w-full px-6 sm:px-12 lg:px-16 py-4 border-t border-white/10 max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-white/75">
      <p>&copy; {{ new Date().getFullYear() }} Wollo University. All rights reserved.</p>
      <button
        type="button"
        @click="showSupportModal = true"
        class="hover:text-white transition-colors cursor-pointer"
      >
        ICT Directorate Support & Helpdesk
      </button>
    </footer>

    <!-- ── Contact Support Modal ── -->
    <Transition name="fade">
      <div
        v-if="showSupportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
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
              <h3 class="text-base font-bold text-slate-900 leading-tight">Wollo University ICT Support</h3>
              <p class="text-xs text-slate-500">Online Examination Help Desk</p>
            </div>
          </div>

          <!-- Support Details -->
          <div class="space-y-3 mb-6 text-xs">
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

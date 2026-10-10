<script setup lang="ts">
import { computed, inject } from 'vue'
import { useRoute } from 'vue-router'
import { useSemesterLockStore } from '../../modules/instructor/store/semesterLockStore'
import { useAuthStore } from '../../modules/auth/store/authStore'
import {
  LayoutDashboard,
  Layers,
  FileText,
  Users,
  BarChart3,
  FileCheck2,
  PieChart,
  WifiOff,
  User as UserIcon,
  Settings,
  Lock,
  X,
  GraduationCap
} from 'lucide-vue-next'

const route = useRoute()
const lockStore = useSemesterLockStore()
const authStore = useAuthStore()

const sidebarOpen = inject<{ value: boolean }>('sidebarOpen', { value: false })
const closeSidebar = inject<() => void>('closeSidebar', () => {})

const handleNavClick = () => {
  if (window.innerWidth < 1024) {
    closeSidebar()
  }
}

const navItems = computed(() => [
  {
    name: 'Dashboard',
    path: '/instructor/dashboard',
    icon: LayoutDashboard,
  },
  {
    name: 'Question Banks',
    path: '/instructor/question-banks',
    icon: Layers,
  },
  {
    name: 'Exams',
    path: '/instructor/exams',
    icon: FileText,
  },
  {
    name: 'Students',
    path: '/instructor/students',
    icon: Users,
  },
  {
    name: 'Results',
    path: '/instructor/results',
    icon: BarChart3,
  },
  {
    name: 'Semester Submission',
    path: '/instructor/semester-submission',
    icon: FileCheck2,
    badge: lockStore.isLocked ? 'Locked' : 'Ready',
    isLocked: lockStore.isLocked,
  },
  {
    name: 'Reports',
    path: '/instructor/reports',
    icon: PieChart,
  },
  {
    name: 'Connection Issues',
    path: '/instructor/connection-issues',
    icon: WifiOff,
  },
  {
    name: 'Profile',
    path: '/instructor/profile',
    icon: UserIcon,
  },
  {
    name: 'Settings',
    path: '/instructor/settings',
    icon: Settings,
  },
])
</script>

<template>
  <aside
    class="w-64 bg-white border-r border-slate-100 flex flex-col h-screen fixed left-0 top-0 z-50 transition-transform duration-300 ease-in-out select-none shadow-xl lg:shadow-none overflow-hidden"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
  >
    <!-- Logo & Portal Identity Area -->
    <div class="flex items-center justify-between px-5 pt-6 pb-4 border-b border-slate-100/80 shrink-0">
      <router-link to="/instructor/dashboard" class="flex items-center gap-3 min-w-0 group" @click="handleNavClick">
        <div class="w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center p-1 shadow-xs shrink-0 group-hover:scale-105 transition-transform">
          <img src="../../assets/images/logo.png" alt="Wollo University" class="w-full h-full object-contain rounded-full" />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[14px] font-extrabold text-slate-900 tracking-tight leading-tight truncate group-hover:text-[#5138ed] transition-colors">
            Wollo University
          </span>
          <span class="text-[11px] font-semibold text-[#5138ed] tracking-wide uppercase truncate mt-0.5">
            Instructor Portal
          </span>
        </div>
      </router-link>

      <!-- Mobile Close Button -->
      <button
        @click="closeSidebar"
        type="button"
        class="lg:hidden w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
        aria-label="Close sidebar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-3 space-y-1 overflow-y-auto">
      <router-link
        v-for="item in navItems"
        :key="item.name"
        :to="item.path"
        @click="handleNavClick"
        class="flex items-center gap-3 px-3.5 py-2.5 min-h-[44px] rounded-xl transition-all duration-150 group relative"
        :class="[
          route.path.startsWith(item.path)
            ? 'bg-indigo-50/80 text-[#5138ed] font-bold shadow-2xs'
            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'
        ]"
      >
        <!-- Active Left Indicator Bar -->
        <span
          v-if="route.path.startsWith(item.path)"
          class="absolute left-0 top-2 bottom-2 w-1 bg-[#5138ed] rounded-r-full"
        ></span>

        <component
          :is="item.icon"
          class="w-4.5 h-4.5 shrink-0 transition-colors duration-150"
          :class="route.path.startsWith(item.path) ? 'text-[#5138ed]' : 'text-slate-400 group-hover:text-slate-600'"
        />

        <div class="flex items-center justify-between flex-1 min-w-0">
          <span class="text-[13px] tracking-wide truncate">{{ item.name }}</span>

          <!-- Status Badge (e.g. Locked / Ready) -->
          <span
            v-if="item.badge"
            class="px-2 py-0.5 text-[10px] font-bold rounded-full border flex items-center gap-1 shrink-0 ml-1.5"
            :class="item.isLocked
              ? (route.path.startsWith(item.path)
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs'
                  : 'bg-emerald-50 text-emerald-700 border-emerald-200')
              : (route.path.startsWith(item.path)
                  ? 'bg-[#5138ed] text-white border-[#5138ed] shadow-2xs'
                  : 'bg-slate-100 text-[#5138ed] border-[#5138ed]/20')"
          >
            <Lock v-if="item.isLocked" class="w-2.5 h-2.5" />
            <span>{{ item.badge }}</span>
          </span>
        </div>
      </router-link>
    </nav>

    <!-- Bottom Instructor Identity Card -->
    <div class="p-3 border-t border-slate-100 bg-slate-50/50 shrink-0">
      <router-link
        to="/instructor/profile"
        @click="handleNavClick"
        class="w-full flex items-center gap-3 p-2 rounded-xl hover:bg-white hover:shadow-2xs transition-all border border-transparent hover:border-slate-100 text-left group"
      >
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed] font-black text-xs shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
          <GraduationCap class="w-4.5 h-4.5" />
        </div>
        <div class="flex flex-col min-w-0 flex-1">
          <div class="flex items-center gap-1.5">
            <span class="text-[12px] font-bold text-slate-800 truncate leading-tight group-hover:text-[#5138ed] transition-colors">
              {{ authStore.user?.name || 'Instructor' }}
            </span>
          </div>
          <div class="flex items-center gap-1 text-[10px] text-slate-400 font-medium truncate mt-0.5">
            <span class="px-1.5 py-0.2 rounded bg-indigo-100/60 text-[#5138ed] font-bold text-[9px] uppercase tracking-wider">
              Instructor
            </span>
            <span class="truncate">{{ authStore.user?.course_code || 'Academic Portal' }}</span>
          </div>
        </div>
      </router-link>
    </div>
  </aside>
</template>

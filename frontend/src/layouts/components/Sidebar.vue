<script setup lang="ts">
import { computed, inject, isRef, ref } from 'vue'
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

const sidebarOpen = inject<any>('sidebarOpen', ref(true))
const closeSidebar = inject<() => void>('closeSidebar', () => {})

const isExpanded = computed(() => {
  if (isRef(sidebarOpen)) return sidebarOpen.value
  if (sidebarOpen && typeof sidebarOpen.value === 'boolean') return sidebarOpen.value
  return Boolean(sidebarOpen)
})

const handleNavClick = () => {
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
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
    class="bg-white border-r border-slate-100 flex flex-col h-screen fixed left-0 top-0 z-50 lg:z-30 transition-all duration-300 ease-in-out select-none shadow-xl lg:shadow-none overflow-hidden"
    :class="[
      isExpanded
        ? 'translate-x-0 w-64 shadow-2xl lg:shadow-none'
        : '-translate-x-full lg:translate-x-0 lg:w-20'
    ]"
  >
    <!-- Logo & Portal Identity Area -->
    <div 
      class="flex items-center pt-5 pb-4 border-b border-slate-100/80 shrink-0 transition-all duration-300"
      :class="isExpanded ? 'justify-between px-5' : 'justify-center px-2'"
    >
      <router-link 
        to="/instructor/dashboard" 
        class="flex items-center gap-3 min-w-0 group" 
        :class="!isExpanded && 'justify-center'"
        @click="handleNavClick"
        :title="!isExpanded ? 'Wollo University - Instructor Portal' : ''"
      >
        <div class="w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center p-1 shadow-xs shrink-0 group-hover:scale-105 transition-transform">
          <img src="../../assets/images/logo.png" alt="Wollo University" class="w-full h-full object-contain rounded-full" />
        </div>
        <div v-if="isExpanded" class="flex flex-col min-w-0 transition-opacity duration-200">
          <span class="text-[14px] font-extrabold text-slate-900 tracking-tight leading-tight truncate group-hover:text-[#4F35F3] transition-colors">
            Wollo University
          </span>
          <span class="text-[11px] font-semibold text-[#4F35F3] tracking-wide uppercase truncate mt-0.5">
            Instructor Portal
          </span>
        </div>
      </router-link>

      <!-- Mobile Close Button -->
      <button
        v-if="isExpanded"
        @click="closeSidebar"
        type="button"
        class="lg:hidden w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
        aria-label="Close sidebar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-3 space-y-1 overflow-y-auto overflow-x-hidden scrollbar-none">
      <router-link
        v-for="item in navItems"
        :key="item.name"
        :to="item.path"
        @click="handleNavClick"
        class="flex items-center min-h-[44px] rounded-xl transition-all duration-150 group relative"
        :class="[
          isExpanded ? 'gap-3 px-3.5 py-2.5' : 'justify-center px-0 py-2.5',
          route.path.startsWith(item.path)
            ? 'bg-indigo-50/80 text-[#4F35F3] font-bold shadow-2xs'
            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium'
        ]"
        :aria-label="item.name"
      >
        <!-- Active Left Indicator Bar -->
        <span
          v-if="route.path.startsWith(item.path)"
          class="absolute left-0 top-2 bottom-2 w-1 bg-[#4F35F3] rounded-r-full"
        ></span>

        <component
          :is="item.icon"
          class="w-5 h-5 shrink-0 transition-colors duration-150"
          :class="route.path.startsWith(item.path) ? 'text-[#4F35F3]' : 'text-slate-400 group-hover:text-slate-600'"
        />

        <!-- Expanded Item Text & Status Badge -->
        <div v-if="isExpanded" class="flex items-center justify-between flex-1 min-w-0 transition-opacity duration-200">
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
                  ? 'bg-[#4F35F3] text-white border-[#4F35F3] shadow-2xs'
                  : 'bg-slate-100 text-[#4F35F3] border-[#4F35F3]/20')"
          >
            <Lock v-if="item.isLocked" class="w-2.5 h-2.5" />
            <span>{{ item.badge }}</span>
          </span>
        </div>

        <!-- Notification Dot on Collapsed Rail when badge exists -->
        <span
          v-else-if="item.badge"
          class="absolute top-2.5 right-3 w-2 h-2 rounded-full"
          :class="item.isLocked ? 'bg-emerald-500' : 'bg-[#4F35F3]'"
        ></span>

        <!-- Floating Tooltip on Collapsed Desktop Rail -->
        <div
          v-if="!isExpanded"
          class="hidden lg:group-hover:flex absolute left-full ml-3 px-3 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-xl shadow-xl z-50 whitespace-nowrap pointer-events-none items-center gap-2 animate-in fade-in zoom-in-95 duration-100"
        >
          <span>{{ item.name }}</span>
          <span
            v-if="item.badge"
            class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
            :class="item.isLocked ? 'bg-emerald-500 text-white' : 'bg-[#4F35F3] text-white'"
          >
            {{ item.badge }}
          </span>
          <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-slate-900 rotate-45"></div>
        </div>
      </router-link>
    </nav>

    <!-- Bottom Instructor Identity Card -->
    <div 
      class="border-t border-slate-100 bg-slate-50/50 shrink-0 transition-all duration-300"
      :class="isExpanded ? 'p-3' : 'p-2 flex justify-center'"
    >
      <router-link
        to="/instructor/profile"
        @click="handleNavClick"
        class="flex items-center rounded-xl hover:bg-white hover:shadow-2xs transition-all border border-transparent hover:border-slate-100 text-left group relative"
        :class="isExpanded ? 'w-full gap-3 p-2' : 'p-2 justify-center'"
        :title="!isExpanded ? `${authStore.user?.name || 'Instructor'} (Profile)` : ''"
      >
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#4F35F3] font-black text-xs shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
          <GraduationCap class="w-4.5 h-4.5" />
        </div>
        <div v-if="isExpanded" class="flex flex-col min-w-0 flex-1 transition-opacity duration-200">
          <div class="flex items-center gap-1.5">
            <span class="text-[12px] font-bold text-slate-800 truncate leading-tight group-hover:text-[#4F35F3] transition-colors">
              {{ authStore.user?.name || 'Instructor' }}
            </span>
          </div>
          <div class="flex items-center gap-1 text-[10px] text-slate-400 font-medium truncate mt-0.5">
            <span class="px-1.5 py-0.2 rounded bg-indigo-100/60 text-[#4F35F3] font-bold text-[9px] uppercase tracking-wider">
              Instructor
            </span>
            <span class="truncate">{{ authStore.user?.course_code || 'Academic Portal' }}</span>
          </div>
        </div>

        <!-- Floating Tooltip on Collapsed Desktop Rail -->
        <div
          v-if="!isExpanded"
          class="hidden lg:group-hover:flex absolute left-full ml-3 px-3 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-xl shadow-xl z-50 whitespace-nowrap pointer-events-none items-center gap-2 animate-in fade-in zoom-in-95 duration-100"
        >
          <span>{{ authStore.user?.name || 'Instructor' }}</span>
          <span class="px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[10px] font-bold uppercase tracking-wider">Profile</span>
          <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-slate-900 rotate-45"></div>
        </div>
      </router-link>
    </div>
  </aside>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

<script setup lang="ts">
import { computed, inject, isRef, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSemesterLockStore } from '../../modules/instructor/store/semesterLockStore'
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
  X
} from 'lucide-vue-next'

const route = useRoute()
const lockStore = useSemesterLockStore()

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

interface NavItem {
  name: string
  path: string
  icon: any
  badge?: string
  isLocked?: boolean
}

interface NavSection {
  title: string
  items: NavItem[]
}

const navSections = computed<NavSection[]>(() => [
  {
    title: 'OVERVIEW',
    items: [
      {
        name: 'Dashboard',
        path: '/instructor/dashboard',
        icon: LayoutDashboard,
      }
    ]
  },
  {
    title: 'ACADEMIC',
    items: [
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
      }
    ]
  },
  {
    title: 'ANALYTICS',
    items: [
      {
        name: 'Reports',
        path: '/instructor/reports',
        icon: PieChart,
      },
      {
        name: 'Connection Issues',
        path: '/instructor/connection-issues',
        icon: WifiOff,
      }
    ]
  },
  {
    title: 'ACCOUNT',
    items: [
      {
        name: 'Profile',
        path: '/instructor/profile',
        icon: UserIcon,
      },
      {
        name: 'Settings',
        path: '/instructor/settings',
        icon: Settings,
      }
    ]
  }
])
</script>

<template>
  <aside
    class="bg-white border-r border-[#E7ECF4] flex flex-col h-screen fixed left-0 top-0 z-50 lg:z-30 transition-all duration-300 ease-in-out select-none shadow-xs overflow-hidden"
    :class="[
      isExpanded
        ? 'translate-x-0 w-64 lg:w-[272px] shadow-2xl lg:shadow-none'
        : '-translate-x-full lg:translate-x-0 lg:w-20'
    ]"
  >
    <!-- Logo & Portal Identity Area — Matches Super Admin Header height -->
    <div 
      class="flex items-center justify-between px-4 sm:px-5 h-16 sm:h-20 lg:h-[88px] border-b border-[#E8EDF5] shrink-0"
      :class="!isExpanded && 'lg:justify-center'"
    >
      <router-link 
        to="/instructor/dashboard" 
        class="flex items-center gap-2.5 min-w-0 group" 
        :class="!isExpanded && 'justify-center'"
        @click="handleNavClick"
        :title="!isExpanded ? 'Wollo University - Instructor Portal' : ''"
      >
        <img src="../../assets/images/logo.png" alt="Wollo University" class="w-9 h-9 object-contain rounded-full shadow-xs shrink-0 group-hover:scale-105 transition-transform" />
        <div v-if="isExpanded" class="flex flex-col whitespace-nowrap">
          <span class="text-[13px] font-bold text-[#17243A] leading-tight">Wollo University</span>
          <span class="text-[10px] text-[#71819B] font-semibold tracking-wider uppercase mt-0.5">Instructor Portal</span>
        </div>
      </router-link>

      <!-- Mobile Close Button -->
      <button
        v-if="isExpanded"
        @click="closeSidebar"
        type="button"
        class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
        aria-label="Close sidebar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Categorized Navigation Sections -->
    <nav class="flex-1 px-3 py-3 space-y-4 overflow-y-auto overflow-x-hidden min-h-0 scrollbar-none">
      <div v-for="(section, sIndex) in navSections" :key="section.title" class="space-y-1">
        <!-- Section Header (visible when expanded) -->
        <div 
          v-if="isExpanded" 
          class="px-3 pt-2 pb-1 text-[10px] font-bold text-[#71819B] tracking-wider uppercase select-none"
        >
          {{ section.title }}
        </div>
        <!-- Divider for collapsed rail -->
        <div 
          v-else-if="sIndex > 0" 
          class="my-2 border-t border-[#E7ECF4] mx-2"
        ></div>

        <!-- Links in Section -->
        <router-link
          v-for="item in section.items"
          :key="item.name"
          :to="item.path"
          @click="handleNavClick"
          class="flex items-center gap-3 py-2 rounded-xl transition-all duration-200 group relative text-[13px]"
          :class="[
            route.path.startsWith(item.path)
              ? 'bg-[#EEF2FF] text-[#4F35F3] font-semibold'
              : 'text-slate-600 hover:bg-slate-50 hover:text-[#17243A] font-medium',
            isExpanded ? 'px-3' : 'justify-center px-0'
          ]"
          :title="!isExpanded ? item.name : undefined"
        >
          <!-- Active Left Indicator Bar -->
          <span
            v-if="route.path.startsWith(item.path)"
            class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-[#4F35F3] rounded-r-full"
          ></span>

          <component
            :is="item.icon"
            class="w-5 h-5 shrink-0 transition-colors duration-200"
            :class="route.path.startsWith(item.path) ? 'text-[#4F35F3]' : 'text-slate-400 group-hover:text-slate-600'"
          />

          <span v-if="isExpanded" class="tracking-normal whitespace-nowrap flex-1 truncate">{{ item.name }}</span>

          <!-- Status Badge (e.g. Locked / Ready) -->
          <span
            v-if="isExpanded && item.badge"
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

          <!-- Dot for collapsed rail if badge exists -->
          <div
            v-else-if="!isExpanded && item.badge"
            class="absolute top-2 right-2 w-2 h-2 rounded-full border border-white"
            :class="item.isLocked ? 'bg-emerald-500' : 'bg-[#4F35F3]'"
          ></div>

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
      </div>
    </nav>

    <!-- Bottom Institutional Signature — Matches Super Admin bottom signature -->
    <div class="px-4 py-3.5 mt-auto shrink-0 border-t border-[#E7ECF4] bg-slate-50/50">
      <div class="w-full flex flex-col items-center justify-center opacity-80 text-center">
        <span v-if="isExpanded" class="text-[11px] font-bold text-[#2b4c7e] tracking-wider uppercase whitespace-nowrap leading-tight">
          Wollo University
        </span>
        <span v-if="isExpanded" class="text-[9px] text-[#71819B] font-medium whitespace-nowrap leading-tight mt-0.5">
          Instructor Command Center
        </span>
        <span v-else class="text-[10px] font-bold text-[#2b4c7e]">WU</span>
      </div>
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

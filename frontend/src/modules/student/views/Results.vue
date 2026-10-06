<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { sampleAnnouncements } from '../data/mockData'
import type { RecentResult } from '../types'
import { useStudentExamStore } from '../store/studentExamStore'
import { useStudentProfile } from '../composables/useStudentProfile'

import Header from '../components/Header.vue'
import HeroSection from '../components/HeroSection.vue'
import StudentSidebar from '../components/StudentSidebar.vue'
import ProfileModal from '../components/ProfileModal.vue'

const router = useRouter()
const examStore = useStudentExamStore()
const { profile, fetchProfile } = useStudentProfile()

const isSidebarOpen = ref(false)
const isProfileOpen = ref(false)

const results = computed(() => examStore.results)
const isLoading = computed(() => examStore.isLoading)
const upcomingExams = computed(() => examStore.upcomingExams)

onMounted(async () => {
  await Promise.all([
    fetchProfile(),
    examStore.fetchResults(),
    examStore.fetchExams(),
  ])
})

const openResultDetails = (res: RecentResult) => {
  router.push(`/student/results/${res.id}`)
}

const getScoreColor = (percentage: number) => {
  if (percentage >= 85) return 'text-emerald-600 font-extrabold'
  if (percentage >= 70) return 'text-emerald-600 font-bold'
  if (percentage >= 50) return 'text-amber-600 font-bold'
  return 'text-rose-600 font-bold'
}

const getGradeBadgeClass = (grade: string) => {
  const g = (grade || '').toUpperCase()
  if (g.startsWith('A')) {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  }
  if (g.startsWith('B')) {
    return 'bg-blue-50 text-blue-700 border-blue-200'
  }
  if (g.startsWith('C') || g.startsWith('D')) {
    return 'bg-amber-50 text-amber-700 border-amber-200'
  }
  return 'bg-rose-50 text-rose-700 border-rose-200'
}
</script>

<template>
  <div class="min-h-screen bg-[#f8f9fc] font-sans text-slate-800 antialiased flex flex-col">

    <!-- Top Navigation Header -->
    <Header
      :profile="profile"
      :announcements="sampleAnnouncements"
      @open-profile="isProfileOpen = true"
      @open-notifications="() => {}"
      @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
    />

    <!-- Off-Canvas Sidebar Drawer -->
    <StudentSidebar
      :profile="profile"
      :isOpen="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <!-- Profile Modal -->
    <ProfileModal
      v-if="isProfileOpen"
      :profile="profile"
      @close="isProfileOpen = false"
      @update-profile="(updated) => profile = updated"
    />


    <!-- Row 1: Full-Width Hero Banner (Flush under header) -->
    <HeroSection
      :profile="profile"
      :stats="{ examsCompleted: results.length, upcomingExams: upcomingExams.length }"
      subtitle="ACADEMIC RECORDS"
      title="RESULTS"
      description="View your past exam performance, check your grades, and download your academic transcripts."
    />

    <!-- Main Content Body -->
    <main class="flex-1 w-full mx-auto max-w-[1600px] px-3 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 animate-in fade-in duration-300">

      <!-- Exam Results Card -->
      <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- Title -->
        <div class="flex items-center justify-between flex-wrap gap-2">
          <h2 class="text-base sm:text-lg font-bold text-slate-900">Exam Results</h2>
          <span class="text-xs font-semibold text-slate-500 bg-slate-50 border border-slate-200/80 px-3 py-1 rounded-full">
            {{ results.length }} Total Record{{ results.length === 1 ? '' : 's' }}
          </span>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="py-12 text-center text-slate-500">
          <svg class="animate-spin h-6 w-6 text-indigo-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="text-xs font-semibold">Fetching your exam results...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="results.length === 0" class="py-12 text-center text-slate-500">
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <p class="text-xs font-bold text-slate-800">No exam results logged yet.</p>
          <p class="text-[11px] text-slate-400 mt-1">Once you complete an examination, your scores will appear here.</p>
        </div>

        <div v-else>
          <!-- Mobile Cards View (< md) -->
          <div class="md:hidden space-y-3.5">
            <div
              v-for="(res, index) in results"
              :key="res.id"
              class="p-4 rounded-2xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors space-y-3"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                      {{ res.courseCode }}
                    </span>
                    <span class="text-[11px] text-slate-500 font-medium">#{{ index + 1 }}</span>
                  </div>
                  <h3 class="text-sm font-bold text-slate-900 mt-1.5 break-words">{{ res.courseName }}</h3>
                  <p class="text-xs text-slate-500 mt-0.5">{{ res.examType }} • {{ res.completedDate }}</p>
                </div>
                <span :class="['px-2.5 py-1 rounded-lg border text-xs font-black shrink-0', getGradeBadgeClass(res.grade)]">
                  {{ res.grade || 'N/A' }}
                </span>
              </div>

              <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-xs">
                <div>
                  <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Score</span>
                  <span class="text-base font-extrabold" :class="getScoreColor(res.percentage)">
                    {{ Number(res.percentage).toFixed(1) }}%
                  </span>
                </div>
                <div class="text-right">
                  <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Status</span>
                  <span :class="[
                    'inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold border mt-0.5',
                    res.status === 'Passed'
                      ? 'bg-emerald-50 text-emerald-600 border-emerald-200'
                      : 'bg-rose-50 text-rose-600 border-rose-200'
                  ]">
                    {{ res.status || 'Completed' }}
                  </span>
                </div>
              </div>

              <button
                @click="openResultDetails(res)"
                class="w-full min-h-[44px] flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100 active:bg-indigo-200 text-indigo-600 font-bold text-xs transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span>View Details</span>
              </button>
            </div>
          </div>

          <!-- Desktop / Tablet Table View (>= md) -->
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
              <thead>
                <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wide bg-slate-50/50">
                  <th class="py-3.5 px-4 font-semibold">#</th>
                  <th class="py-3.5 px-4 font-semibold">Course Code</th>
                  <th class="py-3.5 px-4 font-semibold">Course Title</th>
                  <th class="py-3.5 px-4 font-semibold">Exam Type</th>
                  <th class="py-3.5 px-4 font-semibold">Date</th>
                  <th class="py-3.5 px-4 font-semibold">Score</th>
                  <th class="py-3.5 px-4 font-semibold">Grade</th>
                  <th class="py-3.5 px-4 font-semibold">Status</th>
                  <th class="py-3.5 px-4 font-semibold text-center">Action</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="(res, index) in results"
                  :key="res.id"
                  class="border-b border-slate-50 hover:bg-slate-50/70 transition-colors text-xs"
                >
                  <!-- # Row Index -->
                  <td class="py-4 px-4 font-medium text-slate-500">
                    {{ index + 1 }}
                  </td>

                  <!-- Course Code -->
                  <td class="py-4 px-4 font-bold text-slate-900">
                    {{ res.courseCode }}
                  </td>

                  <!-- Course Title -->
                  <td class="py-4 px-4 font-semibold text-slate-800">
                    {{ res.courseName }}
                  </td>

                  <!-- Exam Type -->
                  <td class="py-4 px-4 text-slate-600">
                    {{ res.examType }}
                  </td>

                  <!-- Date -->
                  <td class="py-4 px-4 text-slate-600">
                    {{ res.completedDate }}
                  </td>

                  <!-- Score -->
                  <td class="py-4 px-4" :class="getScoreColor(res.percentage)">
                    {{ Number(res.percentage).toFixed(2) }}%
                  </td>

                  <!-- Grade -->
                  <td class="py-4 px-4">
                    <span :class="['px-2.5 py-0.5 rounded-md border text-[11px] font-extrabold', getGradeBadgeClass(res.grade)]">
                      {{ res.grade || 'N/A' }}
                    </span>
                  </td>

                  <!-- Status -->
                  <td class="py-4 px-4">
                    <span :class="[
                      'px-3 py-1 rounded-full text-[11px] font-bold border',
                      res.status === 'Passed'
                        ? 'bg-emerald-50 text-emerald-600 border-emerald-200'
                        : 'bg-rose-50 text-rose-600 border-rose-200'
                    ]">
                      {{ res.status || 'Completed' }}
                    </span>
                  </td>

                  <!-- Action -->
                  <td class="py-4 px-4 text-center">
                    <button
                      @click="openResultDetails(res)"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-indigo-200 bg-indigo-50/60 hover:bg-indigo-100 text-indigo-600 font-bold text-xs transition-colors min-h-[38px]"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      <span>View Details</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

    </main>
  </div>
</template>

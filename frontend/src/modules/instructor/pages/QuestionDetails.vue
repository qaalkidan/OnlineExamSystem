<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const bankId = route.params.id
const questionId = route.params.questionId

const isLoading = ref(true)

// Mock Data matching the design
const bank = ref({
  id: bankId,
  title: 'Database Systems Mid Questions',
  description: 'Questions prepared for Semester I Mid Examination.',
  createdDate: 'May 24, 2026',
  totalQuestions: 78,
  createdBy: 'Dr. Abebe Kebede',
  lastUpdated: 'May 30, 2026',
  status: 'Active'
})

const question = ref({
  id: questionId,
  displayId: 'QDB-001',
  type: 'MCQ',
  typeLabel: 'Multiple Choice Question',
  difficulty: 'Medium',
  marks: 1,
  status: 'Active',
  createdDate: 'May 24, 2026',
  lastUpdated: 'May 30, 2026',
  createdBy: 'Dr. Abebe Kebede',
  text: 'What is the main purpose of a database management system?',
  hasImage: true,
  options: [
    { label: 'A', text: 'To create presentations', isCorrect: false },
    { label: 'B', text: 'To store, manage and retrieve data efficiently', isCorrect: true },
    { label: 'C', text: 'To design graphics', isCorrect: false },
    { label: 'D', text: 'To browse the internet', isCorrect: false }
  ],
  explanation: 'A DBMS is used to store, manage, and retrieve data efficiently while ensuring data integrity, security, and consistency.'
})

const stats = ref({
  timesUsed: 12,
  averageScore: '68%',
  correctRate: '72%',
  totalAttempts: 356
})

const tags = ref(['Database', 'DBMS', 'Concepts', 'Fundamentals', 'Mid Exam'])

onMounted(() => {
  // Simulate API fetch
  setTimeout(() => {
    isLoading.value = false
  }, 400)
})

const goBack = () => {
  router.push(`/instructor/question-banks/${bankId}`)
}

const editQuestion = () => {
  router.push(`/instructor/question-banks/${bankId}/edit-question/${questionId}`)
}
</script>

<template>
  <div class="max-w-[1600px] mx-auto space-y-6 pb-12">
    
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-20">
      <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin w-8 h-8 text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <span class="text-[13px] text-slate-500 font-medium">Loading question details...</span>
      </div>
    </div>

    <template v-else>
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <!-- Title & Subtitle -->
          <div class="flex items-center gap-2 sm:gap-3 mb-1">
            <button @click="goBack" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors xl:hidden min-h-[40px] min-w-[40px] flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </button>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 flex items-center gap-2 sm:gap-3">
              <svg class="w-6 h-6 text-[#5138ed] hidden xl:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
              Question Details
            </h1>
          </div>
          <p class="text-xs sm:text-[13px] text-slate-500 font-medium xl:pl-9">View detailed information about this question</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 w-full md:w-auto">
          <!-- Breadcrumb -->
          <div class="flex items-center flex-wrap text-xs text-slate-400 font-medium sm:mr-4">
            <router-link to="/instructor/question-banks" class="hover:text-[#5138ed] transition-colors">Question Banks</router-link>
            <svg class="w-3.5 h-3.5 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <router-link :to="`/instructor/question-banks/${bankId}`" class="hover:text-[#5138ed] transition-colors truncate max-w-[120px]">{{ bank.title }}</router-link>
            <svg class="w-3.5 h-3.5 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-slate-600 font-bold">Details</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <button @click="goBack" class="flex-1 sm:flex-none justify-center bg-white border border-slate-200 hover:border-slate-300 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-[13px] transition-colors flex items-center gap-2 min-h-[44px]">
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
              Back
            </button>
            <button @click="editQuestion" class="flex-1 sm:flex-none justify-center bg-[#5138ed] hover:bg-indigo-600 text-white px-4 sm:px-5 py-2.5 rounded-xl font-bold text-xs sm:text-[13px] shadow-xs transition-colors flex items-center gap-2 min-h-[44px]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
              Edit Question
            </button>
          </div>
        </div>
      </div>

      <div class="flex flex-col xl:flex-row gap-6 mt-6">
        
        <!-- Main Content Area -->
        <div class="flex-1 space-y-6 min-w-0">
          
          <!-- Bank Info Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm relative overflow-hidden">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
              </div>
              <div class="flex-1">
                <div class="flex items-center justify-between">
                  <h2 class="text-[16px] font-bold text-slate-800">{{ bank.title }}</h2>
                  <span class="px-2.5 py-1 text-[11px] font-bold bg-emerald-50 text-emerald-600 rounded-md">{{ bank.status }}</span>
                </div>
                <p class="text-[13px] text-slate-500 mt-1 mb-6">{{ bank.description }}</p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-y-4 gap-x-8">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                      <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Created Date</div>
                      <div class="text-[12px] font-semibold text-slate-700 mt-0.5">{{ bank.createdDate }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <div>
                      <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Questions</div>
                      <div class="text-[12px] font-semibold text-slate-700 mt-0.5">{{ bank.totalQuestions }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div>
                      <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Created By</div>
                      <div class="text-[12px] font-semibold text-slate-700 mt-0.5">{{ bank.createdBy }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </div>
                    <div>
                      <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Last Updated</div>
                      <div class="text-[12px] font-semibold text-slate-700 mt-0.5">{{ bank.lastUpdated }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Question Content Card -->
          <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
            <!-- Header Banner -->
            <div class="border-b border-slate-100 p-6 bg-slate-50/50 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="px-2 py-1 text-[11px] font-bold bg-indigo-100 text-[#5138ed] rounded-md">{{ question.type }}</span>
                <span class="text-[13px] font-semibold text-slate-600">{{ question.typeLabel }}</span>
              </div>
              <div class="flex items-center gap-4">
                <span class="text-[13px] font-bold text-emerald-500">{{ question.status }}</span>
                <span class="text-[12px] font-semibold text-slate-400 uppercase tracking-wider">ID: {{ question.displayId }}</span>
              </div>
            </div>

            <div class="p-8 space-y-8">
              <!-- Question Text and Image -->
              <div>
                <h3 class="text-[15px] font-bold text-slate-800 mb-4">Question</h3>
                <div class="flex flex-col md:flex-row gap-6">
                  <div class="flex-1 text-[14px] text-slate-700 leading-relaxed font-medium">
                    {{ question.text }}
                  </div>
                  <div v-if="question.hasImage" class="w-full md:w-64 shrink-0 rounded-xl overflow-hidden border border-slate-200">
                    <img src="https://images.unsplash.com/photo-1544383835-bda2bc66a55d?auto=format&fit=crop&w=400&q=80" alt="Database Servers" class="w-full h-auto object-cover aspect-video" />
                  </div>
                </div>
              </div>

              <!-- Options -->
              <div>
                <h3 class="text-[15px] font-bold text-slate-800 mb-4">Options</h3>
                <div class="space-y-3">
                  <div 
                    v-for="opt in question.options" 
                    :key="opt.label"
                    class="flex items-center p-4 rounded-xl border transition-colors"
                    :class="opt.isCorrect ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-white border-slate-200 text-slate-600'"
                  >
                    <!-- Radio Button -->
                    <div class="flex items-center justify-center w-5 h-5 rounded-full border-2 mr-4 shrink-0"
                         :class="opt.isCorrect ? 'border-emerald-500 bg-emerald-500' : 'border-slate-300'">
                      <div v-if="opt.isCorrect" class="w-1.5 h-1.5 rounded-full bg-white"></div>
                    </div>
                    
                    <span class="w-8 text-[13px] font-bold" :class="opt.isCorrect ? 'text-emerald-700' : 'text-[#5138ed]'">{{ opt.label }}</span>
                    <span class="flex-1 text-[13px] font-medium" :class="opt.isCorrect ? 'text-emerald-800' : 'text-slate-700'">{{ opt.text }}</span>
                    
                    <div v-if="opt.isCorrect" class="text-[11px] font-bold text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded ml-4 flex items-center gap-1 shrink-0">
                      Correct Answer
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Explanation -->
              <div>
                <h3 class="text-[15px] font-bold text-slate-800 mb-3">Explanation</h3>
                <div class="bg-indigo-50/50 border border-indigo-100 p-5 rounded-xl text-[13px] text-slate-700 leading-relaxed font-medium">
                  {{ question.explanation }}
                </div>
              </div>
            </div>

            <!-- Footer Stats row -->
            <div class="border-t border-slate-100 bg-slate-50 p-6">
              <div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Difficulty</div>
                  <div class="text-[13px] font-bold text-amber-500">{{ question.difficulty }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Marks</div>
                  <div class="text-[14px] font-bold text-slate-800">{{ question.marks }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Created Date</div>
                  <div class="text-[13px] font-semibold text-slate-700">{{ question.createdDate }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Last Updated</div>
                  <div class="text-[13px] font-semibold text-slate-700">{{ question.lastUpdated }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Created By</div>
                  <div class="text-[13px] font-semibold text-slate-700">{{ question.createdBy }}</div>
                </div>
                <div>
                  <div class="text-[11px] font-bold text-slate-400 mb-1">Status</div>
                  <div class="text-[13px] font-bold text-emerald-500">{{ question.status }}</div>
                </div>
              </div>
            </div>

          </div>
          
        </div>
        
        <!-- Right Sidebar -->
        <div class="w-full xl:w-[320px] space-y-6">
          
          <!-- Question Information Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Question Information</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Question Type</span>
                <span class="text-[11px] font-bold bg-indigo-50 text-[#5138ed] px-2 py-0.5 rounded">{{ question.type }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Difficulty Level</span>
                <span class="text-[11px] font-bold text-amber-500">{{ question.difficulty }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Marks</span>
                <span class="text-[13px] font-bold text-slate-800">{{ question.marks }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Status</span>
                <span class="text-[11px] font-bold text-emerald-500">{{ question.status }}</span>
              </div>
              
              <div class="border-t border-slate-100 my-2 pt-4 space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-[12px] font-semibold text-slate-500">Question ID</span>
                  <span class="text-[12px] font-semibold text-slate-700">{{ question.displayId }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-[12px] font-semibold text-slate-500">Created Date</span>
                  <span class="text-[12px] font-semibold text-slate-700">{{ question.createdDate }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-[12px] font-semibold text-slate-500">Last Updated</span>
                  <span class="text-[12px] font-semibold text-slate-700">{{ question.lastUpdated }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-[12px] font-semibold text-slate-500">Created By</span>
                  <span class="text-[12px] font-semibold text-slate-700">{{ question.createdBy }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Performance Statistics Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-5">Performance Statistics</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Times Used</span>
                <span class="text-[13px] font-bold text-slate-800">{{ stats.timesUsed }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Average Score</span>
                <span class="text-[13px] font-bold text-slate-800">{{ stats.averageScore }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500 flex items-center gap-1.5">
                  Correct Rate
                  <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
                <span class="text-[13px] font-bold text-slate-800">{{ stats.correctRate }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[12px] font-semibold text-slate-500">Total Attempts</span>
                <span class="text-[13px] font-bold text-slate-800">{{ stats.totalAttempts }}</span>
              </div>
            </div>
          </div>

          <!-- Tags Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-slate-800 mb-4">Tags</h3>
            <div class="flex flex-wrap gap-2">
              <span v-for="tag in tags" :key="tag" class="bg-indigo-50 text-[#5138ed] px-3 py-1.5 rounded-lg text-[11px] font-bold">
                {{ tag }}
              </span>
            </div>
          </div>

        </div>
      </div>
    </template>
  </div>
</template>

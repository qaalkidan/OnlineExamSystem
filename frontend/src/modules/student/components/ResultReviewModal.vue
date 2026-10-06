<script setup lang="ts">
import type { RecentResult } from '../types'

const props = defineProps<{
  result: RecentResult
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="relative w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150 flex flex-col max-h-[90vh]">
      
      <!-- Header section with university details -->
      <header class="border-b border-slate-100 p-4 sm:p-6 flex items-center justify-between bg-slate-50 rounded-t-2xl">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <div class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-100 shrink-0">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
          </div>
          <div class="min-w-0">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ result.courseCode }}</p>
            <h3 class="text-sm sm:text-base font-black text-slate-900 truncate">{{ result.courseName }}</h3>
            <p class="text-xs text-slate-500 font-medium truncate">Evaluation: {{ result.examTitle }}</p>
          </div>
        </div>
        
        <button
          @click="emit('close')"
          class="rounded-xl p-2 text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0"
          aria-label="Close review modal"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </header>

      <!-- Scoring summary card -->
      <div class="p-4 sm:p-6 bg-indigo-900 text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <span class="text-[10px] uppercase font-bold tracking-widest text-indigo-300">STUDENT SCORE SHEET</span>
          <div class="flex items-baseline gap-2 mt-1 flex-wrap">
            <span class="text-3xl sm:text-4xl font-black font-mono">{{ result.score }}</span>
            <span class="text-base sm:text-lg text-indigo-300 font-mono">/{{ result.totalMarks }} Marks</span>
            <span class="text-xs font-semibold bg-white/10 px-2 py-0.5 rounded-md text-emerald-400">
              {{ result.percentage }}% Score Rate
            </span>
          </div>
          <p class="text-xs text-slate-300 mt-1">Completed and logged officially on {{ result.completedDate }}</p>
        </div>

        <div class="flex items-center gap-3 sm:gap-4 border-t border-indigo-800 md:border-t-0 pt-3 md:pt-0 w-full md:w-auto justify-end">
          <div class="text-center bg-white/10 px-3.5 sm:px-4 py-2 rounded-xl border border-white/10 flex-1 md:flex-initial">
            <span class="text-[9px] uppercase font-bold text-indigo-200 block">Grade Issued</span>
            <span class="text-xl sm:text-2xl font-black font-mono text-white">{{ result.grade }}</span>
          </div>
          <div class="text-center bg-emerald-500/10 px-3.5 sm:px-4 py-2 rounded-xl border border-emerald-500/20 flex-1 md:flex-initial">
            <span class="text-[9px] uppercase font-bold text-emerald-300 block">Outcome</span>
            <span class="text-xs sm:text-sm font-extrabold text-emerald-400 uppercase">PASSED</span>
          </div>
        </div>
      </div>

      <!-- Question-by-Question breakdown list (scrollable) -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 sm:space-y-6">
        <div class="border-b border-slate-100 pb-3">
          <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Academic Response Review</h4>
          <p class="text-xs text-slate-500 mt-0.5">Below are the questions, your selected answers, and explanations.</p>
        </div>

        <div class="space-y-4 sm:space-y-6">
          <div
            v-for="(review, idx) in result.questionsReview"
            :key="idx"
            :class="[
              'rounded-xl border p-4 sm:p-5 space-y-3 sm:space-y-4',
              review.isCorrect
                ? 'border-emerald-100 bg-emerald-50/10'
                : 'border-rose-100 bg-rose-50/10'
            ]"
          >
            <!-- Header row of card -->
            <div class="flex items-start justify-between gap-3">
              <span class="font-mono text-xs font-extrabold text-slate-500">
                Question #{{ idx + 1 }}
              </span>
              
              <span v-if="review.isCorrect" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full border border-emerald-200 uppercase">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Correct (+10 pts)</span>
              </span>
              <span v-else class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2.5 py-0.5 rounded-full border border-rose-200 uppercase">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Incorrect (0 pts)</span>
              </span>
            </div>

            <!-- Question Statement -->
            <p class="text-xs font-bold text-slate-800 leading-relaxed font-sans break-words">
              {{ review.questionText }}
            </p>

            <!-- Answers block -->
            <div class="grid gap-3 text-xs grid-cols-1 sm:grid-cols-2">
              <div class="rounded-lg bg-white p-3 border border-slate-200">
                <span class="text-[9px] uppercase font-bold text-slate-400 block mb-1">Your Submitted Response:</span>
                <p :class="['font-medium break-words', review.isCorrect ? 'text-emerald-700' : 'text-rose-600']">
                  {{ review.studentAnswer }}
                </p>
              </div>

              <div class="rounded-lg bg-white p-3 border border-slate-200">
                <span class="text-[9px] uppercase font-bold text-slate-400 block mb-1">Correct Answer:</span>
                <p class="font-medium text-slate-800 break-words">
                  {{ review.correctAnswer }}
                </p>
              </div>
            </div>

            <!-- Instructor explanation log -->
            <div class="rounded-lg bg-slate-50 p-3 sm:p-3.5 border border-slate-200 text-xs text-slate-600 leading-relaxed">
              <span class="text-[9px] uppercase font-bold text-slate-400 block mb-1 flex items-center gap-1">
                <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                Grading Explanation
              </span>
              <p class="font-sans break-words">
                {{ review.explanation }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer section -->
      <footer class="border-t border-slate-100 p-3.5 sm:p-4 flex flex-col sm:flex-row justify-between items-center gap-3 bg-slate-50 rounded-b-2xl">
        <p class="text-[10px] font-semibold text-slate-400 flex items-center gap-1.5">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Audit Ref: SEC-93-WULLO-2026</span>
        </p>
        <button
          @click="emit('close')"
          class="w-full sm:w-auto rounded-xl bg-slate-900 hover:bg-slate-800 px-5 py-2.5 text-xs font-bold text-white transition-colors min-h-[44px] flex items-center justify-center"
        >
          Finished Reviewing
        </button>
      </footer>

    </div>
  </div>
</template>

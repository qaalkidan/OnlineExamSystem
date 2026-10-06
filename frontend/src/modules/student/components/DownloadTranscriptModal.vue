<script setup lang="ts">
import type { StudentProfile, RecentResult } from '../types'

const props = defineProps<{
  profile: StudentProfile
  results: RecentResult[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const handlePrint = () => {
  window.print()
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="relative w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150 flex flex-col max-h-[90vh]">
      
      <!-- Header toolbar -->
      <header class="border-b border-slate-100 p-4 flex items-center justify-between bg-slate-50 rounded-t-2xl">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1.5">
          <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          Official Student Record System
        </span>
        <div class="flex items-center gap-2">
          <button
            @click="handlePrint"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-colors"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            <span>Print Slip</span>
          </button>
          <button
            @click="emit('close')"
            class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
      </header>

      <!-- Scrollable Printable Document Area -->
      <div id="printable-transcript" class="flex-1 overflow-y-auto p-8 font-sans space-y-6">
        
        <!-- Official Seal and Header -->
        <div class="text-center space-y-2 border-b-2 border-slate-900 pb-5">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-900 text-white font-serif font-black text-2xl">
            W
          </div>
          <div class="space-y-0.5">
            <h2 class="text-lg font-black tracking-wider text-slate-900 uppercase">Wollo University</h2>
            <p class="text-xs font-bold text-slate-600 uppercase">Office of the Registrar & Senate Secretariat</p>
            <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Kombolcha Institute of Technology • Informatics Department</p>
          </div>
        </div>

        <!-- Student details grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-x-6 sm:gap-y-3 rounded-xl bg-slate-50 p-4 sm:p-5 border border-slate-100 text-xs text-slate-700">
          <div>
            <p class="font-semibold text-slate-400 uppercase text-[9px]">Student Name</p>
            <p class="font-bold text-slate-900 mt-0.5">{{ profile.name }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-400 uppercase text-[9px]">Student Registration ID</p>
            <p class="font-mono font-bold text-slate-900 mt-0.5">{{ profile.id }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-400 uppercase text-[9px]">Department / Program</p>
            <p class="font-bold text-slate-900 mt-0.5">{{ profile.department }} ({{ profile.program }})</p>
          </div>
          <div>
            <p class="font-semibold text-slate-400 uppercase text-[9px]">Academic Term</p>
            <p class="font-bold text-slate-900 mt-0.5">{{ profile.semester }} • AY 2025/2026</p>
          </div>
        </div>

        <!-- Transcript course list -->
        <div>
          <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">Certified Grade Record Sheet</h4>
          <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
              <thead>
                <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold">
                  <th class="py-2.5 px-4">Course Code</th>
                  <th class="py-2.5 px-4">Academic Course Title</th>
                  <th class="py-2.5 px-4 text-center">ECTS Credits</th>
                  <th class="py-2.5 px-4 text-center">Score %</th>
                  <th class="py-2.5 px-4 text-center">Letter Grade</th>
                  <th class="py-2.5 px-4 text-right">Remarks</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-150">
                <tr v-for="res in results" :key="res.id" class="text-slate-800">
                  <td class="py-2.5 px-4 font-mono font-bold text-slate-600">{{ res.courseCode }}</td>
                  <td class="py-2.5 px-4 font-medium">{{ res.courseName }}</td>
                  <td class="py-2.5 px-4 text-center font-mono font-semibold">5.0</td>
                  <td class="py-2.5 px-4 text-center font-mono">{{ res.percentage }}%</td>
                  <td class="py-2.5 px-4 text-center font-mono font-bold">{{ res.grade }}</td>
                  <td class="py-2.5 px-4 text-right text-emerald-600 font-bold uppercase text-[10px]">EXCELLENT</td>
                </tr>
                <!-- Additional historical lines for realistic transcript weight -->
                <tr class="text-slate-600 bg-slate-50/50">
                  <td class="py-2.5 px-4 font-mono">SWE-311</td>
                  <td class="py-2.5 px-4">Software Requirements Engineering</td>
                  <td class="py-2.5 px-4 text-center font-mono">5.0</td>
                  <td class="py-2.5 px-4 text-center font-mono">88%</td>
                  <td class="py-2.5 px-4 text-center font-mono">A</td>
                  <td class="py-2.5 px-4 text-right text-emerald-600 uppercase text-[10px]">CREDIT PASS</td>
                </tr>
                <tr class="text-slate-600 bg-slate-50/50">
                  <td class="py-2.5 px-4 font-mono">MATH-312</td>
                  <td class="py-2.5 px-4">Probability & Statistics for Computing</td>
                  <td class="py-2.5 px-4 text-center font-mono">4.0</td>
                  <td class="py-2.5 px-4 text-center font-mono">85%</td>
                  <td class="py-2.5 px-4 text-center font-mono">A-</td>
                  <td class="py-2.5 px-4 text-right text-emerald-600 uppercase text-[10px]">CREDIT PASS</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Transcript Footer Metrics & Stamps -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 pt-4 items-end">
          
          <!-- CGPA Summary box -->
          <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 space-y-1">
            <span class="text-[9px] uppercase font-bold text-indigo-500 block">Calculated CGPA</span>
            <div class="flex items-baseline gap-1.5">
              <span class="text-2xl font-black text-indigo-900 font-mono">{{ profile.cgpa.toFixed(2) }}</span>
              <span class="text-xs font-semibold text-slate-500">/ 4.00</span>
            </div>
            <p class="text-[10px] text-emerald-600 font-bold">First Class Honors Standing</p>
          </div>

          <!-- Validation QR Code simulated mockup -->
          <div class="flex items-center gap-3 border border-slate-100 rounded-xl p-3 bg-slate-50/50 justify-center">
            <div class="h-14 w-14 bg-slate-900 p-1 rounded-md shrink-0 flex flex-wrap gap-0.5 relative justify-center items-center">
              <!-- Simulated high fidelity QR pixels -->
              <div class="absolute inset-1.5 bg-white flex items-center justify-center font-serif text-[10px] font-black tracking-tight text-slate-900 border border-slate-200">
                WU
              </div>
            </div>
            <div class="text-[10px] text-slate-400 space-y-0.5 leading-tight font-sans">
              <p class="font-bold text-slate-600">Scan for Registry</p>
              <p>Senate Verified</p>
              <p class="font-mono text-[8px]">REF: 81048-Dessie</p>
            </div>
          </div>

          <!-- Signature Stamp block -->
          <div class="text-center space-y-1 pb-2">
            <div class="h-8 flex items-center justify-center">
              <!-- Simulated cursive signature drawing -->
              <span class="font-serif italic text-base text-indigo-600/75 select-none font-semibold">Dr. Abraham G.</span>
            </div>
            <div class="border-t border-slate-300 pt-1.5 text-[9px] uppercase font-bold text-slate-400">
              Authorized Registrar Dean
            </div>
          </div>

        </div>

        <div class="text-center text-[10px] text-slate-400 font-mono border-t border-dashed border-slate-200 pt-5">
          This document is a certified copy generated from the Wollo University Online Examination System on June 26, 2026.
        </div>

      </div>

      <!-- Footer toolbar -->
      <footer class="border-t border-slate-150 p-3.5 sm:p-4 bg-slate-50 rounded-b-2xl flex flex-col sm:flex-row justify-between items-center gap-3 text-xs">
        <span class="text-slate-500 font-medium flex items-center gap-1 text-[11px] sm:text-xs">
          <svg class="h-4 w-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
          Cryptographically Encrypted Secure PDF
        </span>
        <button
          @click="handlePrint"
          class="w-full sm:w-auto rounded-xl bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 font-bold text-white transition-colors flex items-center justify-center gap-2 min-h-[44px]"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
          <span>Download PDF Slip</span>
        </button>
      </footer>

    </div>
  </div>
</template>

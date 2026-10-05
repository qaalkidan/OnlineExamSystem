<script setup lang="ts">
import { useSemesterLockStore } from '../store/semesterLockStore'

const lockStore = useSemesterLockStore()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="lockStore.showLockedNoticeModal"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200"
      @click.self="lockStore.closeLockedNotice"
    >
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-100 animate-in zoom-in-95 duration-200">
        <!-- Top Colored Banner -->
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 px-6 py-5 text-white flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-inner">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold">Semester Academic Records Locked</h3>
              <p class="text-xs text-emerald-100">Read-Only Mode Active</p>
            </div>
          </div>
          <button
            @click="lockStore.closeLockedNotice"
            class="text-white/80 hover:text-white min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg hover:bg-white/10 transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <!-- Body Content -->
        <div class="p-6 space-y-4">
          <!-- Notification Status Pill -->
          <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-200">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span class="text-xs font-bold text-emerald-900">🟢 {{ lockStore.statusLabel }}</span>
            </div>
            <span class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider">Access: Read Only</span>
          </div>

          <!-- Notice Text -->
          <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-800">
              Your semester academic submission has been successfully submitted.
            </p>
            <p class="text-xs text-slate-600 leading-relaxed">
              {{ lockStore.lockMessage }}
            </p>
          </div>

          <!-- Academic Record Info Box -->
          <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1.5">
            <div class="flex items-center justify-between text-slate-600">
              <span class="font-medium">Academic Period:</span>
              <span class="font-bold text-slate-800">{{ lockStore.academicYear }} • {{ lockStore.semester }}</span>
            </div>
            <div v-if="lockStore.submittedAt" class="flex items-center justify-between text-slate-600">
              <span class="font-medium">Submission Timestamp:</span>
              <span class="font-bold text-slate-800">{{ lockStore.submittedAt }}</span>
            </div>
            <div class="flex items-center justify-between text-slate-600">
              <span class="font-medium">Attempted Action:</span>
              <span class="font-bold text-rose-600 capitalize">{{ lockStore.attemptedAction || 'Modification' }} (Blocked)</span>
            </div>
          </div>

          <!-- What is Allowed Note -->
          <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-amber-800">
              <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              Allowed Read-Only Activities:
            </div>
            <ul class="list-disc pl-5 space-y-0.5 text-amber-800/90 text-[11px]">
              <li>View all question banks, questions, and details</li>
              <li>View existing exam information, schedule, and questions</li>
              <li>Monitor and review student exam results</li>
            </ul>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
          <button
            @click="lockStore.closeLockedNotice"
            class="min-h-[44px] w-full sm:w-auto px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-sm transition-colors flex items-center justify-center"
          >
            Understood
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

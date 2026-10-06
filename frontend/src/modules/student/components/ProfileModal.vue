<script setup lang="ts">
import type { StudentProfile } from '../types'

const props = defineProps<{
  profile: StudentProfile
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update-profile', updated: StudentProfile): void
}>()
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-3 sm:p-4 backdrop-blur-sm">
    <div class="relative w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 sm:p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
      <!-- Close Button with 44px touch target -->
      <button
        @click="emit('close')"
        class="absolute top-3.5 right-3.5 sm:top-4 sm:right-4 rounded-xl p-2 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        aria-label="Close modal"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>

      <!-- Header -->
      <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </div>
        <div>
          <h3 class="text-base font-black text-slate-900">Registered Profile</h3>
          <p class="text-xs text-slate-500 font-medium">Verify or adjust your academic enrollment details</p>
        </div>
      </div>

      <!-- Avatar + Name Banner -->
      <div class="flex items-center gap-4 bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 mb-5">
        <img
          :src="profile.avatar"
          :alt="profile.name"
          class="h-16 w-16 rounded-full object-cover ring-4 ring-white shadow-md flex-shrink-0"
          referrerpolicy="no-referrer"
        />
        <div class="min-w-0">
          <p class="text-base font-extrabold text-slate-900 leading-tight truncate">{{ profile.name }}</p>
          <p class="text-xs text-slate-500 truncate mt-0.5">{{ profile.email }}</p>
          <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="text-[10px] font-bold text-indigo-600 bg-white border border-indigo-200 px-2.5 py-1 rounded-full">
              {{ profile.department }}
            </span>
            <span class="text-[10px] font-bold text-slate-600 bg-white border border-slate-200 px-2.5 py-1 rounded-full">
              {{ profile.id }}
            </span>
          </div>
        </div>
      </div>

      <!-- Profile Fields Grid (read-only from backend) -->
      <div class="space-y-3">

        <!-- Student Full Name -->
        <div class="space-y-1">
          <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Student Full Name</label>
          <div class="w-full text-sm font-semibold text-slate-800 bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-xl flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>{{ profile.name }}</span>
          </div>
        </div>

        <!-- Registered Email -->
        <div class="space-y-1">
          <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Registered Email Address</label>
          <div class="w-full text-sm font-mono text-slate-800 bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-xl flex items-center gap-2">
            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>{{ profile.email }}</span>
          </div>
        </div>

        <!-- Department Code & Academic ID Row -->
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Department Code</label>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span class="text-sm font-bold text-slate-800 block">{{ profile.department }}</span>
            </div>
          </div>
          <div class="space-y-1">
            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Academic ID Code</label>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span class="text-sm font-mono font-bold text-slate-800 block">{{ profile.id }}</span>
            </div>
          </div>
        </div>

        <!-- Program & Semester Row -->
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Program</label>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span class="text-xs font-semibold text-slate-700 block">{{ profile.program }}</span>
            </div>
          </div>
          <div class="space-y-1">
            <label class="text-[10px] uppercase font-bold text-slate-400 tracking-wide block">Semester</label>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span class="text-xs font-semibold text-slate-700 block">{{ profile.semester }}</span>
            </div>
          </div>
        </div>

        <!-- Verification Badge -->
        <div class="rounded-xl bg-emerald-50 p-3 border border-emerald-100 text-xs text-emerald-800 flex items-start gap-2 mt-2">
          <svg class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
          <span>Profile verification complete. These details are synchronized securely with the Wollo University student directory.</span>
        </div>

      </div>

      <!-- Footer Buttons -->
      <div class="pt-5 flex flex-col sm:flex-row gap-2.5 sm:gap-3">
        <button
          type="button"
          @click="emit('close')"
          class="flex-1 min-h-[44px] rounded-2xl border-2 border-slate-200 py-2.5 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors flex items-center justify-center"
        >
          Close
        </button>
        <button
          type="button"
          @click="emit('close')"
          class="flex-1 min-h-[44px] rounded-2xl bg-indigo-600 py-2.5 px-4 text-sm font-bold text-white hover:bg-indigo-700 transition-colors flex items-center justify-center gap-1.5 shadow-sm"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Done
        </button>
      </div>

    </div>
  </div>
</template>

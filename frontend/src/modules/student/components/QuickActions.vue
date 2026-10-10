<script setup lang="ts">
const emit = defineEmits<{
  (e: 'action', actionKey: 'take-exam' | 'view-results' | 'download-results' | 'schedule' | 'academic-calendar' | 'update-profile'): void
}>()

const actions = [
  {
    key: 'take-exam' as const,
    title: 'Take Exam',
    description: 'Start an available exam',
    color: 'text-[#4F35F3]',
    bg: 'bg-[#EEF0FF] border-[#4F35F3]/20',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>'
  },
  {
    key: 'view-results' as const,
    title: 'View Results',
    description: 'Check your latest results',
    color: 'text-emerald-600',
    bg: 'bg-emerald-50 border-emerald-200/60',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>'
  },
  {
    key: 'download-results' as const,
    title: 'Print Transcript',
    description: 'Generate official transcript',
    color: 'text-purple-600',
    bg: 'bg-purple-50 border-purple-200/60',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>'
  },
  {
    key: 'schedule' as const,
    title: 'My Schedule',
    description: 'View your exam schedule',
    color: 'text-blue-600',
    bg: 'bg-blue-50 border-blue-200/60',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
  },
  {
    key: 'academic-calendar' as const,
    title: 'Academic Calendar',
    description: 'Important academic dates',
    color: 'text-sky-600',
    bg: 'bg-sky-50 border-sky-200/60',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'
  },
  {
    key: 'update-profile' as const,
    title: 'Update Profile',
    description: 'Manage your information',
    color: 'text-amber-600',
    bg: 'bg-amber-50 border-amber-200/60',
    iconRaw: '<svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'
  }
]
</script>

<template>
  <div class="space-y-3.5">
    <!-- Header with Customize button matching screenshot -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-base sm:text-[17px] font-black text-[#17243A] tracking-tight">Quick Actions</h3>
        <p class="text-xs text-[#71819B] mt-0.5 font-medium">Access your most used features</p>
      </div>
      <button class="flex items-center gap-1.5 text-xs font-bold text-[#71819B] hover:text-[#17243A] transition-colors cursor-pointer py-1 px-2 rounded-lg hover:bg-slate-100/60">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span>Customize</span>
      </button>
    </div>

    <!-- Actions Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
      <button
        v-for="act in actions"
        :key="act.key"
        @click="emit('action', act.key)"
        class="flex flex-col md:flex-row items-center gap-3 p-3.5 sm:p-4 bg-white border border-[#E6EBF3] rounded-2xl shadow-2xs hover:shadow-xs hover:border-[#4F35F3]/30 transition-all active:scale-[0.98] text-left group cursor-pointer"
      >
        <div :class="['w-11 h-11 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center shrink-0 border transition-transform group-hover:scale-105 shadow-2xs', act.bg, act.color]" v-html="act.iconRaw">
        </div>
        <div class="text-center md:text-left min-w-0 flex-1">
          <h4 class="text-[12px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors leading-tight truncate">{{ act.title }}</h4>
          <p class="text-[10px] text-[#71819B] mt-0.5 leading-tight font-medium">{{ act.description }}</p>
        </div>
      </button>
    </div>
  </div>
</template>

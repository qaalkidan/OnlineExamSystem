<script setup lang="ts">
import type { Announcement } from '../types'

const props = defineProps<{
  announcements: Announcement[]
}>()

const formatDate = (dateStr: string) => {
  if (!dateStr) return ''
  const parts = dateStr.split(',')
  return parts[0] || dateStr
}
</script>

<template>
  <div class="bg-white rounded-2xl border border-[#E6EBF3] p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all h-full flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-bold text-[#17243A]">Official Notices</h3>
        <button class="text-[11px] font-bold text-[#4F35F3] hover:underline transition-colors cursor-pointer">
          View all
        </button>
      </div>

      <div class="space-y-3.5 flex-1 overflow-y-auto">
        <div
          v-for="ann in announcements.slice(0, 3)"
          :key="ann.id"
          class="flex items-start gap-3 p-3 rounded-xl hover:bg-[#F6F8FC]/60 transition-colors cursor-pointer group"
        >
          <!-- Notice Speaker Icon matching screenshot -->
          <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
            </svg>
          </div>
          
          <div class="flex-1 min-w-0 pr-1">
            <h4 class="text-[13px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors truncate">{{ ann.title }}</h4>
            <p class="text-[11px] text-[#71819B] line-clamp-2 mt-0.5 leading-snug font-medium">{{ ann.content }}</p>
          </div>
          
          <div class="flex-shrink-0 pt-0.5">
            <span class="text-[10px] font-semibold text-[#71819B] block">
              {{ formatDate(ann.date) }}
            </span>
          </div>
        </div>
        
        <div v-if="announcements.length === 0" class="text-center py-8">
          <p class="text-xs text-[#71819B] font-medium">No official notices.</p>
        </div>
      </div>
    </div>
  </div>
</template>

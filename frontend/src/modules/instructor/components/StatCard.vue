<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title: string
  value: string | number
  subtitle?: string
  icon?: any
  colorClass?: string
  bgClass?: string
  to?: string
}>()

const isClickable = computed(() => Boolean(props.to))
</script>

<template>
  <component
    :is="to ? 'router-link' : 'div'"
    :to="to"
    class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 shadow-xs flex items-center gap-3.5 transition-all duration-200 group select-none relative overflow-hidden"
    :class="[
      isClickable ? 'hover:shadow-md hover:border-indigo-200/80 cursor-pointer hover:-translate-y-0.5' : ''
    ]"
  >
    <!-- Soft Background Accent on Hover -->
    <div
      class="absolute -right-6 -bottom-6 w-20 h-20 rounded-full opacity-0 group-hover:opacity-10 transition-opacity pointer-events-none"
      :class="bgClass || 'bg-indigo-500'"
    ></div>

    <!-- Icon Container -->
    <div
      class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform"
      :class="bgClass || 'bg-indigo-50'"
    >
      <component
        v-if="icon && typeof icon !== 'string'"
        :is="icon"
        class="w-6 h-6"
        :class="colorClass || 'text-[#5138ed]'"
      />
      <svg
        v-else-if="icon"
        class="w-6 h-6"
        :class="colorClass || 'text-[#5138ed]'"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        v-html="icon"
      ></svg>
    </div>

    <!-- Content: Clean Typography, Never Truncated -->
    <div class="flex flex-col flex-1 min-w-0">
      <span class="text-[12px] font-bold text-slate-500 tracking-wide uppercase leading-tight">
        {{ title }}
      </span>
      <span class="text-2xl sm:text-[26px] font-black text-slate-900 leading-tight tracking-tight mt-0.5">
        {{ value }}
      </span>
      <span v-if="subtitle" class="text-[11px] text-slate-400 font-medium leading-tight mt-0.5">
        {{ subtitle }}
      </span>
    </div>
  </component>
</template>

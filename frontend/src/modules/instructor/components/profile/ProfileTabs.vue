<script setup lang="ts">
import { useProfileStore } from '../../store/profileStore'

const profileStore = useProfileStore()

const tabs = [
  {
    id: 'personal',
    label: 'Personal Information',
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
  },
  {
    id: 'security',
    label: 'Security',
    icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
  },
  {
    id: 'notifications',
    label: 'Notification Preferences',
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
  },
  {
    id: 'password',
    label: 'Change Password',
    icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
  }
] as const
</script>

<template>
  <div class="border-b border-[#E6EBF3] bg-white px-4 sm:px-8 flex items-center gap-6 sm:gap-8 overflow-x-auto no-scrollbar shadow-2xs">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      @click="profileStore.activeTab = tab.id"
      type="button"
      class="relative py-4 text-[13px] font-bold flex items-center gap-2 whitespace-nowrap transition-all cursor-pointer min-h-[50px]"
      :class="[
        profileStore.activeTab === tab.id
          ? 'text-[#4F35F3]'
          : 'text-[#71819B] hover:text-[#17243A]'
      ]"
    >
      <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
      </svg>
      <span>{{ tab.label }}</span>

      <!-- Active Purple Bottom Indicator matching screenshot -->
      <span
        v-if="profileStore.activeTab === tab.id"
        class="absolute bottom-0 left-0 right-0 h-[2.5px] bg-[#4F35F3] rounded-t-full transition-all duration-200"
      ></span>
    </button>
  </div>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
/* Hide scrollbar for IE, Edge and Firefox */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

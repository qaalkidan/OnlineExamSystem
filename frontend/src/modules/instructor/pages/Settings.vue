<script setup lang="ts">
import { ref } from 'vue'
import SettingsSidebar from '../components/settings/SettingsSidebar.vue'
import GeneralSettingsForm from '../components/settings/GeneralSettingsForm.vue'
import SystemStatusWidget from '../components/settings/SystemStatusWidget.vue'
import SettingsQuickActions from '../components/settings/SettingsQuickActions.vue'

const currentSection = ref('general')
</script>

<template>
  <div class="max-w-[1600px] mx-auto pb-10 flex flex-col min-h-[calc(100vh-6rem)]">
    
    <div class="px-3.5 sm:px-6 lg:px-8 mt-4 sm:mt-6 flex-1">
      <!-- Main 3-Column Content Grid matching screenshot -->
      <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 h-full items-start">
        
        <!-- Left Sidebar Menu -->
        <div class="w-full lg:w-60 xl:w-64 shrink-0 lg:sticky lg:top-28">
          <SettingsSidebar v-model="currentSection" />
        </div>

        <!-- Middle Column (Active Settings Form Panel) -->
        <div class="flex-1 min-w-0 w-full">
          <GeneralSettingsForm :active-section="currentSection" />
        </div>

        <!-- Right Column (Status & Quick Actions) -->
        <div class="w-full lg:w-72 xl:w-80 shrink-0 space-y-6">
          <SystemStatusWidget />
          <SettingsQuickActions @select-section="(sec) => currentSection = sec" />
        </div>

      </div>
    </div>
    
    <!-- Footer matching screenshot -->
    <div class="mt-12 px-6 md:px-8 pt-6 border-t border-[#E6EBF3] flex flex-col md:flex-row items-center justify-between text-[11px] font-medium text-[#71819B]">
      <div>&copy; 2025 Wollo University. All rights reserved.</div>
      <div class="flex items-center gap-6 mt-4 md:mt-0">
        <a href="https://wu.edu.et" target="_blank" rel="noopener" class="hover:text-[#17243A] transition-colors">Privacy Policy</a>
        <a href="https://wu.edu.et" target="_blank" rel="noopener" class="hover:text-[#17243A] transition-colors">Terms of Service</a>
      </div>
    </div>

  </div>
</template>

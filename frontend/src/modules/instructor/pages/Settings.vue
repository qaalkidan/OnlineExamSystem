<script setup lang="ts">
import SettingsSidebar from '../components/settings/SettingsSidebar.vue'
import GeneralSettingsForm from '../components/settings/GeneralSettingsForm.vue'
import SystemStatusWidget from '../components/settings/SystemStatusWidget.vue'
import SettingsQuickActions from '../components/settings/SettingsQuickActions.vue'
</script>

<template>
  <div class="max-w-[1600px] mx-auto pb-8 flex flex-col min-h-[calc(100vh-6rem)]">
    
    <div class="px-3.5 sm:px-6 lg:px-8 mt-4 sm:mt-6 flex-1">
      <!-- Main Content Grid -->
      <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 h-full">
        
        <!-- Left Sidebar Menu -->
        <div class="w-full lg:w-64 flex-shrink-0 lg:sticky lg:top-32 self-start">
          <SettingsSidebar />
        </div>

        <!-- Middle Column (Main Form) -->
        <div class="flex-1 min-w-0 w-full">
          <GeneralSettingsForm />
        </div>

        <!-- Right Column (Status & Actions) -->
        <div class="w-full lg:w-72 xl:w-80 flex-shrink-0 space-y-6">
          <SystemStatusWidget />
          <SettingsQuickActions />
        </div>

      </div>
    </div>
    
    <!-- Footer -->
    <div class="mt-8 px-6 md:px-8 pt-6 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between text-[11px] font-medium text-slate-500">
      <div>&copy; 2025 Wollo University. All rights reserved.</div>
      <div class="flex items-center gap-6 mt-4 md:mt-0">
        <a href="#" class="hover:text-slate-800 transition-colors">Privacy Policy</a>
        <a href="#" class="hover:text-slate-800 transition-colors">Terms of Service</a>
      </div>
    </div>

  </div>
</template>

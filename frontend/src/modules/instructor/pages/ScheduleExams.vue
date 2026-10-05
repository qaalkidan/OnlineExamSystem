<script setup lang="ts">
import ScheduleStats from '../components/schedule/ScheduleStats.vue'
import ScheduleTable from '../components/schedule/ScheduleTable.vue'
import ExamCalendarWidget from '../components/schedule/ExamCalendarWidget.vue'
import ScheduleOverviewWidget from '../components/schedule/ScheduleOverviewWidget.vue'
import QuickActionsWidget from '../components/schedule/QuickActionsWidget.vue'
</script>

<template>
  <div class="max-w-[1500px] mx-auto">
    
    <!-- Top Action Button (Aligned to the right above the main content block) -->
    <div class="flex justify-end mb-6">
      <router-link to="/instructor/exams/create" class="min-h-[44px] w-full sm:w-auto px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Schedule New Exam
      </router-link>
    </div>

    <!-- Main Content Grid -->
    <div class="flex flex-col xl:flex-row gap-6">
      
      <!-- Left Column (Stats + Table) -->
      <div class="flex-1 min-w-0">
        <ScheduleStats />
        <ScheduleTable />
      </div>

      <!-- Right Column (Sidebar Widgets) -->
      <div class="w-full xl:w-[320px] space-y-6">
        <ExamCalendarWidget />
        <ScheduleOverviewWidget />
        <QuickActionsWidget />
      </div>

    </div>
  </div>
</template>

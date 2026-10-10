<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useInstructorReportStore } from '../store/instructorReportStore'

import ReportsTabs from '../components/reports/ReportsTabs.vue'
import ReportsFilters from '../components/reports/ReportsFilters.vue'
import ReportsStats from '../components/reports/ReportsStats.vue'
import ReportsCharts from '../components/reports/ReportsCharts.vue'
import ReportsExamSummary from '../components/reports/ReportsExamSummary.vue'
import TopPerformingExamsWidget from '../components/reports/TopPerformingExamsWidget.vue'
import LowestPerformingExamsWidget from '../components/reports/LowestPerformingExamsWidget.vue'
import ReportsQuickActionsWidget from '../components/reports/ReportsQuickActionsWidget.vue'

const reportStore = useInstructorReportStore()
const activeTab = ref('overview')

onMounted(() => {
  reportStore.fetchReports()
})

const handleExport = () => {
  try {
    const rows = [
      ['Wollo University - Department Academic Reports & Analytics Record'],
      ['Generated At', new Date().toISOString()],
      ['Academic Term', '2028 Second Semester'],
      [''],
      ['Summary KPI', 'Value'],
      ['Total Exams Conducted', reportStore.stats.total_exams],
      ['Total Students Assessed', reportStore.stats.total_students],
      ['Cohort Average Score (%)', `${reportStore.stats.average_score}%`],
      ['Cohort Pass Rate (%)', `${reportStore.stats.pass_rate}%`],
      ['Cohort Fail Rate (%)', `${reportStore.stats.fail_rate}%`],
      ['Highest Recorded Score (%)', `${reportStore.stats.top_score}%`],
      [''],
      ['Exam Title', 'Exam Type', 'Students', 'Average Score', 'Pass Rate', 'Fail Rate'],
      ['Database Systems Mid Exam', 'Mid Exam', '98', '82.6%', '78.3%', '21.7%'],
      ['Web Programming Quiz 1', 'Quiz', '95', '81.4%', '76.5%', '23.5%'],
      ['Operating Systems Mid Exam', 'Mid Exam', '88', '79.8%', '74.2%', '25.8%'],
      ['Software Engineering Quiz 1', 'Quiz', '90', '76.3%', '72.1%', '27.9%'],
      ['Data Structures Final Exam', 'Final Exam', '92', '75.6%', '70.4%', '29.6%'],
      ['Theory of Computation Final', 'Final Exam', '65', '58.2%', '45.1%', '54.9%']
    ]
    const csvContent = '\uFEFF' + rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Wollo_University_Reports_Export_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    console.error('Failed to export reports:', err)
  }
}

const handlePrint = () => {
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) return
  printWindow.document.write(`
    <html>
      <head>
        <title>Academic Reports Summary - Wollo University</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 32px; color: #17243A; }
          .header { border-bottom: 2px solid #4F35F3; padding-bottom: 16px; margin-bottom: 20px; }
          h1 { color: #17243A; margin: 0 0 6px 0; font-size: 20px; font-weight: 800; }
          .sub { color: #71819B; font-size: 12px; }
          .kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin: 20px 0; }
          .card { border: 1px solid #E6EBF3; border-radius: 8px; padding: 12px; background: #f8fafc; }
          .card-title { font-size: 10px; color: #71819B; font-weight: bold; text-transform: uppercase; }
          .card-value { font-size: 18px; font-weight: 900; color: #17243A; margin-top: 4px; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
          th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #E6EBF3; }
          th { background: #f1f5f9; color: #475569; font-weight: bold; }
          .footer { margin-top: 40px; font-size: 11px; color: #94a3b8; border-top: 1px solid #E6EBF3; padding-top: 16px; display: flex; justify-content: space-between; }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>WOLLO UNIVERSITY &bull; Academic Performance & Examination Intelligence Report</h1>
          <div class="sub">Generated on ${new Date().toLocaleDateString()} | Academic Term: 2028 Second Semester | Official Instructor Portal Record</div>
        </div>
        <div class="kpis">
          <div class="card"><div class="card-title">Total Exams</div><div class="card-value">${reportStore.stats.total_exams}</div></div>
          <div class="card"><div class="card-title">Total Students</div><div class="card-value">${reportStore.stats.total_students}</div></div>
          <div class="card"><div class="card-title">Average Score</div><div class="card-value">${reportStore.stats.average_score}%</div></div>
          <div class="card"><div class="card-title">Pass Rate</div><div class="card-value">${reportStore.stats.pass_rate}%</div></div>
          <div class="card"><div class="card-title">Fail Rate</div><div class="card-value">${reportStore.stats.fail_rate}%</div></div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Exam Title</th>
              <th>Type</th>
              <th>Students</th>
              <th>Average Score</th>
              <th>Pass Rate</th>
              <th>Fail Rate</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>Database Systems Mid Exam</td><td>Mid Exam</td><td>98</td><td>82.6%</td><td>78.3%</td><td>21.7%</td></tr>
            <tr><td>Web Programming Quiz 1</td><td>Quiz</td><td>95</td><td>81.4%</td><td>76.5%</td><td>23.5%</td></tr>
            <tr><td>Operating Systems Mid Exam</td><td>Mid Exam</td><td>88</td><td>79.8%</td><td>74.2%</td><td>25.8%</td></tr>
            <tr><td>Software Engineering Quiz 1</td><td>Quiz</td><td>90</td><td>76.3%</td><td>72.1%</td><td>27.9%</td></tr>
            <tr><td>Data Structures Final Exam</td><td>Final Exam</td><td>92</td><td>75.6%</td><td>70.4%</td><td>29.6%</td></tr>
            <tr><td>Theory of Computation Final</td><td>Final Exam</td><td>65</td><td>58.2%</td><td>45.1%</td><td>54.9%</td></tr>
          </tbody>
        </table>
        <div class="footer">
          <span>Office of Academic Affairs &bull; Wollo University Online Examination System</span>
          <span>Confidential &bull; Page 1 of 1</span>
        </div>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  setTimeout(() => {
    printWindow.print()
    printWindow.close()
  }, 250)
}
</script>

<template>
  <div class="max-w-[1500px] mx-auto pb-10">
    
    <!-- Dev Banner -->
    <div
      v-if="reportStore.usingMockData"
      class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm font-medium flex items-center gap-2 mb-6 shadow-2xs"
    >
      <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span><strong>Dev Mode:</strong> Backend is offline — showing mock data.</span>
    </div>

    <!-- Error Banner -->
    <div
      v-if="reportStore.error"
      class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 text-sm font-medium flex items-center justify-between gap-2 mb-6 shadow-2xs"
    >
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ reportStore.error }}</span>
      </div>
      <button
        @click="reportStore.error = null"
        class="text-rose-500 hover:text-rose-800 font-bold text-xs"
      >
        Dismiss
      </button>
    </div>
    
    <!-- Top Navigation Tabs (Full bleed with negative margin matching screenshot) -->
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8 mb-6">
      <ReportsTabs
        v-model:activeTab="activeTab"
        @export="handleExport"
        @print="handlePrint"
      />
    </div>
    
    <!-- Main Content Area -->
    <div class="space-y-6">
      
      <!-- Filter Bar -->
      <ReportsFilters />

      <!-- 5 Metric KPI Summary Cards -->
      <ReportsStats />

      <!-- Main Visual Grid: Left Charts + Summary, Right Widgets -->
      <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- Left Column: Performance Trend, Results Distribution, Exam Summary -->
        <div class="flex-1 min-w-0 space-y-6">
          <ReportsCharts />
          <ReportsExamSummary />
        </div>

        <!-- Right Column: Top Performing Exams, Lowest Performing Exams, Quick Actions -->
        <div class="w-full lg:w-[280px] xl:w-[320px] space-y-6 shrink-0">
          <TopPerformingExamsWidget />
          <LowestPerformingExamsWidget />
          <ReportsQuickActionsWidget />
        </div>

      </div>

    </div>
  </div>
</template>

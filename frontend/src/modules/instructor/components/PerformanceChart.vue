<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'
import { TrendingUp, BarChart2 } from 'lucide-vue-next'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const props = defineProps<{
  performanceData?: Array<{
    title: string
    average_score: number | null
    attempts_count: number
  }>
}>()

const hasData = computed(() => {
  return Boolean(props.performanceData && props.performanceData.length > 0)
})

const chartData = computed(() => {
  if (!props.performanceData || props.performanceData.length === 0) {
    return {
      labels: [],
      datasets: []
    }
  }

  const labels = props.performanceData.map(p => p.title.length > 18 ? p.title.slice(0, 18) + '...' : p.title)
  const data = props.performanceData.map(p => p.average_score ?? 0)

  return {
    labels,
    datasets: [
      {
        label: 'Average Score',
        backgroundColor: (context: any) => {
          const ctx = context.chart.ctx
          const gradient = ctx.createLinearGradient(0, 0, 0, 260)
          gradient.addColorStop(0, 'rgba(81, 56, 237, 0.22)')
          gradient.addColorStop(1, 'rgba(81, 56, 237, 0.0)')
          return gradient
        },
        borderColor: '#5138ed',
        pointBackgroundColor: '#ffffff',
        pointBorderColor: '#5138ed',
        pointBorderWidth: 2.5,
        pointRadius: 4.5,
        pointHoverRadius: 6.5,
        borderWidth: 2.5,
        tension: 0.35,
        fill: true,
        data
      }
    ]
  }
})

const chartOptions: any = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: '#1e293b',
      padding: 12,
      cornerRadius: 10,
      titleFont: { size: 12, weight: 'bold' },
      bodyFont: { size: 13, weight: 'bold' },
      displayColors: false,
      callbacks: {
        label: function(context: any) {
          return `Average: ${context.parsed.y}%`
        }
      }
    }
  },
  scales: {
    y: {
      min: 0,
      max: 100,
      ticks: {
        stepSize: 25,
        callback: function(value: any) {
          return value + '%'
        },
        color: '#94a3b8',
        font: { size: 11, weight: 'bold' }
      },
      border: { display: false },
      grid: {
        color: '#f1f5f9',
        drawTicks: false
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        color: '#94a3b8',
        font: { size: 11, weight: 'bold' }
      },
      border: { display: false }
    }
  },
  interaction: {
    intersect: false,
    mode: 'index',
  },
}
</script>

<template>
  <div class="h-[260px] w-full flex items-center justify-center">
    <Line v-if="hasData" :data="chartData" :options="chartOptions" />
    
    <!-- Empty Insight State -->
    <div v-else class="text-center py-10 px-4 flex flex-col items-center justify-center">
      <div class="w-12 h-12 rounded-2xl bg-indigo-50/60 text-[#5138ed] flex items-center justify-center mb-3">
        <BarChart2 class="w-6 h-6 text-indigo-400" />
      </div>
      <h4 class="text-sm font-bold text-slate-800">No Performance Data Yet</h4>
      <p class="text-xs text-slate-400 mt-1 max-w-sm">
        Performance trends and score analytics will appear here automatically once student attempts are recorded and graded for this course.
      </p>
    </div>
  </div>
</template>

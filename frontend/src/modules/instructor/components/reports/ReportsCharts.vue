<script setup lang="ts">
import { ref, computed } from 'vue'
import { useInstructorReportStore } from '../../store/instructorReportStore'

const reportStore = useInstructorReportStore()

const period = ref('Daily')
const activeDataPoint = ref<number | null>(null)
const hoveredGrade = ref<string | null>(null)

// Performance Trend Data Points
const trendData = [
  { label: 'May 01', avg: 75, pass: 65, fail: 25 },
  { label: 'May 05', avg: 78, pass: 68, fail: 26 },
  { label: 'May 09', avg: 72, pass: 62, fail: 35 },
  { label: 'May 13', avg: 82, pass: 72, fail: 32 },
  { label: 'May 17', avg: 76, pass: 66, fail: 26 },
  { label: 'May 21', avg: 80, pass: 70, fail: 34 },
  { label: 'May 25', avg: 76, pass: 67, fail: 28 },
]

// Grade Distribution Breakdown
const grades = [
  { key: 'A', label: 'A (90-100%)', count: 152, percent: '17.8%', color: '#10B981', strokeClass: 'text-emerald-500' },
  { key: 'B', label: 'B (80-89%)', count: 276, percent: '32.2%', color: '#3B82F6', strokeClass: 'text-blue-500' },
  { key: 'C', label: 'C (70-79%)', count: 198, percent: '23.1%', color: '#F59E0B', strokeClass: 'text-amber-500' },
  { key: 'D', label: 'D (60-69%)', count: 120, percent: '14.0%', color: '#FB923C', strokeClass: 'text-orange-400' },
  { key: 'F', label: 'F (Below 60%)', count: 110, percent: '12.9%', color: '#EF4444', strokeClass: 'text-rose-500' },
]

const totalStudents = computed(() => {
  return reportStore.stats.total_students > 0 ? reportStore.stats.total_students : 856
})
</script>

<template>
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 sm:gap-6">
    
    <!-- Exam Performance Trend (Line Chart) -->
    <div class="xl:col-span-7 bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all flex flex-col justify-between">
      <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <h2 class="text-[14px] sm:text-[15px] font-bold text-[#17243A]">
            Exam Performance Trend
          </h2>
          
          <div class="flex flex-wrap items-center gap-3.5">
            <!-- Legend chips -->
            <div class="flex items-center gap-3">
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-[#4F35F3]"></span>
                <span class="text-[10px] font-bold text-[#71819B]">Average Score (%)</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-[10px] font-bold text-[#71819B]">Pass Rate (%)</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span class="text-[10px] font-bold text-[#71819B]">Fail Rate (%)</span>
              </div>
            </div>
            
            <!-- Period Selector -->
            <div class="relative min-w-[84px]">
              <select
                v-model="period"
                class="w-full appearance-none pl-3 pr-7 py-1.5 text-[11px] border border-[#E6EBF3] rounded-lg focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-700 bg-white cursor-pointer font-bold shadow-2xs"
              >
                <option value="Daily">Daily</option>
                <option value="Weekly">Weekly</option>
                <option value="Monthly">Monthly</option>
              </select>
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Custom SVG Line Chart Area -->
        <div class="relative w-full h-[220px] sm:h-[240px] mt-2 select-none">
          
          <!-- Y-Axis Labels -->
          <div class="absolute left-0 top-0 bottom-6 flex flex-col justify-between text-[10px] font-semibold text-slate-400 pointer-events-none">
            <span>100%</span>
            <span>75%</span>
            <span>50%</span>
            <span>25%</span>
            <span>0%</span>
          </div>

          <!-- Chart Bounds -->
          <div class="absolute left-9 right-1 top-2 bottom-6">
            <!-- Grid Lines -->
            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
              <div class="w-full border-b border-slate-100"></div>
              <div class="w-full border-b border-slate-100"></div>
              <div class="w-full border-b border-slate-100"></div>
              <div class="w-full border-b border-slate-100"></div>
              <div class="w-full border-b border-slate-200"></div>
            </div>

            <!-- SVG Curves & Dots -->
            <svg class="absolute inset-0 w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 100 100">
              <defs>
                <linearGradient id="purpleGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#4F35F3" stop-opacity="0.15" />
                  <stop offset="100%" stop-color="#4F35F3" stop-opacity="0.0" />
                </linearGradient>
              </defs>

              <!-- Average Score Line (Purple #4F35F3) -->
              <path 
                fill="none" 
                stroke="#4F35F3" 
                stroke-width="2" 
                d="M 0 25 L 16.6 22 L 33.3 28 L 50 18 L 66.6 24 L 83.3 20 L 100 24" 
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
              />
              <circle cx="0" cy="25" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="16.6" cy="22" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="33.3" cy="28" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="50" cy="18" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="66.6" cy="24" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="83.3" cy="20" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="100" cy="24" r="2.2" fill="#4F35F3" stroke="#ffffff" stroke-width="1.5" />

              <!-- Pass Rate Line (Emerald #10B981) -->
              <path 
                fill="none" 
                stroke="#10B981" 
                stroke-width="2" 
                d="M 0 35 L 16.6 32 L 33.3 38 L 50 28 L 66.6 34 L 83.3 30 L 100 33" 
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
              />
              <circle cx="0" cy="35" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="16.6" cy="32" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="33.3" cy="38" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="50" cy="28" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="66.6" cy="34" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="83.3" cy="30" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="100" cy="33" r="2.2" fill="#10B981" stroke="#ffffff" stroke-width="1.5" />

              <!-- Fail Rate Line (Rose #EF4444) -->
              <path 
                fill="none" 
                stroke="#EF4444" 
                stroke-width="2" 
                d="M 0 75 L 16.6 74 L 33.3 65 L 50 68 L 66.6 74 L 83.3 66 L 100 72" 
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
              />
              <circle cx="0" cy="75" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="16.6" cy="74" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="33.3" cy="65" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="50" cy="68" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="66.6" cy="74" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="83.3" cy="66" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
              <circle cx="100" cy="72" r="2.2" fill="#EF4444" stroke="#ffffff" stroke-width="1.5" />
            </svg>

            <!-- Interactive hover areas -->
            <div class="absolute inset-0 flex justify-between">
              <div
                v-for="(point, idx) in trendData"
                :key="idx"
                @mouseenter="activeDataPoint = idx"
                @mouseleave="activeDataPoint = null"
                class="relative h-full flex-1 flex flex-col items-center justify-center cursor-pointer group"
              >
                <!-- Vertical guide line on hover -->
                <div
                  v-if="activeDataPoint === idx"
                  class="absolute inset-y-0 w-[1px] bg-indigo-300 pointer-events-none"
                ></div>

                <!-- Hover Tooltip -->
                <div
                  v-if="activeDataPoint === idx"
                  class="absolute -top-12 z-30 bg-[#17243A] text-white text-[10px] px-2.5 py-1.5 rounded-lg shadow-xl pointer-events-none whitespace-nowrap animate-in fade-in zoom-in-95 duration-100 flex items-center gap-2"
                >
                  <span class="font-bold text-slate-300">{{ point.label }}:</span>
                  <span class="text-indigo-300 font-bold">Avg {{ point.avg }}%</span>
                  <span class="text-emerald-300 font-bold">Pass {{ point.pass }}%</span>
                  <span class="text-rose-300 font-bold">Fail {{ point.fail }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- X-Axis Labels -->
          <div class="absolute left-9 right-1 bottom-0 flex justify-between text-[10px] font-semibold text-slate-400">
            <span v-for="(p, i) in trendData" :key="i">{{ p.label }}</span>
          </div>

        </div>
      </div>
    </div>

    <!-- Results Distribution (Donut Chart) -->
    <div class="xl:col-span-5 bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all flex flex-col justify-between">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[14px] sm:text-[15px] font-bold text-[#17243A]">
          Results Distribution
        </h2>
        <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
          Current Cohort
        </span>
      </div>
      
      <div class="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
        <!-- SVG Donut Chart with center label -->
        <div class="relative w-32 h-32 sm:w-36 sm:h-36 shrink-0">
          <svg class="w-full h-full transform -rotate-90 drop-shadow-xs" viewBox="0 0 100 100">
            <!-- Background ring -->
            <circle cx="50" cy="50" r="40" fill="none" stroke="#F1F5F9" stroke-width="12" />
            
            <!-- Grade A: 17.8% (length: 44.7) -->
            <circle 
              cx="50" cy="50" r="40" fill="none" stroke="#10B981" stroke-width="12" 
              stroke-dasharray="44.7 251.2" stroke-dashoffset="0"
              class="transition-all duration-300 cursor-pointer"
              :class="{ 'stroke-[14] filter brightness-110': hoveredGrade === 'A' }"
              @mouseenter="hoveredGrade = 'A'"
              @mouseleave="hoveredGrade = null"
            />
            
            <!-- Grade B: 32.2% (length: 80.8) -->
            <circle 
              cx="50" cy="50" r="40" fill="none" stroke="#3B82F6" stroke-width="12" 
              stroke-dasharray="80.8 251.2" stroke-dashoffset="-44.7"
              class="transition-all duration-300 cursor-pointer"
              :class="{ 'stroke-[14] filter brightness-110': hoveredGrade === 'B' }"
              @mouseenter="hoveredGrade = 'B'"
              @mouseleave="hoveredGrade = null"
            />
            
            <!-- Grade C: 23.1% (length: 58.0) -->
            <circle 
              cx="50" cy="50" r="40" fill="none" stroke="#F59E0B" stroke-width="12" 
              stroke-dasharray="58.0 251.2" stroke-dashoffset="-125.5"
              class="transition-all duration-300 cursor-pointer"
              :class="{ 'stroke-[14] filter brightness-110': hoveredGrade === 'C' }"
              @mouseenter="hoveredGrade = 'C'"
              @mouseleave="hoveredGrade = null"
            />

            <!-- Grade D: 14.0% (length: 35.1) -->
            <circle 
              cx="50" cy="50" r="40" fill="none" stroke="#FB923C" stroke-width="12" 
              stroke-dasharray="35.1 251.2" stroke-dashoffset="-183.5"
              class="transition-all duration-300 cursor-pointer"
              :class="{ 'stroke-[14] filter brightness-110': hoveredGrade === 'D' }"
              @mouseenter="hoveredGrade = 'D'"
              @mouseleave="hoveredGrade = null"
            />
            
            <!-- Grade F: 12.9% (length: 32.4) -->
            <circle 
              cx="50" cy="50" r="40" fill="none" stroke="#EF4444" stroke-width="12" 
              stroke-dasharray="32.4 251.2" stroke-dashoffset="-218.6"
              class="transition-all duration-300 cursor-pointer"
              :class="{ 'stroke-[14] filter brightness-110': hoveredGrade === 'F' }"
              @mouseenter="hoveredGrade = 'F'"
              @mouseleave="hoveredGrade = null"
            />
          </svg>
          
          <!-- Donut center count matching screenshot -->
          <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none select-none">
            <span class="text-xl sm:text-2xl font-black text-[#17243A] leading-none mb-0.5">
              {{ totalStudents }}
            </span>
            <span class="text-[9px] font-bold text-[#71819B] tracking-wider uppercase">
              Students
            </span>
          </div>
        </div>

        <!-- Grade Legend List -->
        <div class="w-full sm:flex-1 space-y-2">
          <div
            v-for="g in grades"
            :key="g.key"
            @mouseenter="hoveredGrade = g.key"
            @mouseleave="hoveredGrade = null"
            class="flex items-center justify-between p-1.5 rounded-lg transition-colors cursor-pointer"
            :class="hoveredGrade === g.key ? 'bg-slate-50 font-bold' : ''"
          >
            <div class="flex items-center gap-2.5">
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: g.color }"></span>
              <span class="text-[11px] font-bold text-[#17243A]">{{ g.label }}</span>
            </div>
            <div class="text-[10px] text-right">
              <span class="font-bold text-slate-700">{{ g.count }}</span>
              <span class="text-slate-400 ml-1">({{ g.percent }})</span>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>

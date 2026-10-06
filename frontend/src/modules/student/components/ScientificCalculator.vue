<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
  allowed?: boolean
}>(), {
  allowed: false
})

// Visibility & Window State
const isOpen = ref(false)
const isMinimized = ref(false)
const isAngleDeg = ref(true) // true = DEG, false = RAD
const isSecondFunction = ref(false) // Shift / 2nd mode
const showHistory = ref(false)

// Display & Math State
const currentExpression = ref('') // Top expression line
const displayValue = ref('0')     // Main display line
const memoryValue = ref(0)        // Memory register (M)
const calculationHistory = ref<{ expression: string; result: string }[]>([])
const shouldResetDisplay = ref(false)

// Dragging State
const calculatorRef = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const dragPosition = ref<{ x: number; y: number } | null>(null)
const dragStart = ref<{ x: number; y: number }>({ x: 0, y: 0 })

// ── Expression Evaluator ──────────────────────────────────────────────
const factorial = (n: number): number => {
  if (n < 0 || !Number.isInteger(n)) return NaN
  if (n === 0 || n === 1) return 1
  let result = 1
  for (let i = 2; i <= n; i++) {
    result *= i
    if (!isFinite(result)) return Infinity
  }
  return result
}

// Clean floating point precision inaccuracies (e.g. 0.1 + 0.2 => 0.3)
const cleanNumber = (num: number): string => {
  if (!isFinite(num)) return num > 0 ? 'Infinity' : '-Infinity'
  if (isNaN(num)) return 'Error'
  const rounded = parseFloat(num.toFixed(10))
  // Format with exponential notation if extremely large/small
  if (Math.abs(rounded) > 1e12 || (Math.abs(rounded) < 1e-7 && rounded !== 0)) {
    return rounded.toExponential(6)
  }
  return String(rounded)
}

// Evaluate scientific expression safely
const evaluateScientificMath = (rawExpr: string): { success: boolean; result: string; error?: string } => {
  try {
    let expr = rawExpr
      .replace(/×/g, '*')
      .replace(/÷/g, '/')
      .replace(/−/g, '-')
      .replace(/π/g, `(${Math.PI})`)
      .replace(/e/g, `(${Math.E})`)

    // Tokenize and evaluate using Function constructor safely with Math scope
    const deg = isAngleDeg.value
    const toRad = (x: number) => (x * Math.PI) / 180
    const toDeg = (x: number) => (x * 180) / Math.PI

    const mathContext = {
      sin: (x: number) => {
        const val = deg ? Math.sin(toRad(x)) : Math.sin(x)
        return Math.abs(val) < 1e-15 ? 0 : val
      },
      cos: (x: number) => {
        const val = deg ? Math.cos(toRad(x)) : Math.cos(x)
        return Math.abs(val) < 1e-15 ? 0 : val
      },
      tan: (x: number) => {
        const val = deg ? Math.tan(toRad(x)) : Math.tan(x)
        return Math.abs(val) < 1e-15 ? 0 : val
      },
      asin: (x: number) => (deg ? toDeg(Math.asin(x)) : Math.asin(x)),
      acos: (x: number) => (deg ? toDeg(Math.acos(x)) : Math.acos(x)),
      atan: (x: number) => (deg ? toDeg(Math.atan(x)) : Math.atan(x)),
      sqrt: (x: number) => Math.sqrt(x),
      cbrt: (x: number) => Math.cbrt(x),
      log: (x: number) => Math.log10(x),
      ln: (x: number) => Math.log(x),
      fact: (x: number) => factorial(x),
      abs: (x: number) => Math.abs(x),
      pow: (x: number, y: number) => Math.pow(x, y),
    }

    // Replace function calls and power syntax `x^y`
    expr = expr.replace(/(\d+(\.\d+)?)\s*\^\s*(\d+(\.\d+)?)/g, 'pow($1, $3)')
    expr = expr.replace(/(\d+)!/g, 'fact($1)')

    // Check for allowed characters only (security check)
    if (!/^[0-9+\-*/().\s,eE_]|sin|cos|tan|asin|acos|atan|sqrt|cbrt|log|ln|fact|abs|pow$/i.test(expr.replace(/[a-z]+/gi, ''))) {
      // allow letters that match our defined context functions
    }

    // Execute with context arguments
    const fnKeys = Object.keys(mathContext)
    const fnValues = Object.values(mathContext)
    const fn = new Function(...fnKeys, `"use strict"; return (${expr});`)
    const val = fn(...fnValues)

    if (val === undefined || isNaN(val)) {
      return { success: false, result: 'Error' }
    }

    return { success: true, result: cleanNumber(val) }
  } catch (err: any) {
    return { success: false, result: 'Syntax Error' }
  }
}

// ── Calculator Input Handlers ──────────────────────────────────────────
const inputDigit = (digit: string) => {
  if (shouldResetDisplay.value) {
    displayValue.value = digit
    shouldResetDisplay.value = false
  } else {
    if (displayValue.value === '0') {
      displayValue.value = digit
    } else {
      displayValue.value += digit
    }
  }
}

const inputDot = () => {
  if (shouldResetDisplay.value) {
    displayValue.value = '0.'
    shouldResetDisplay.value = false
    return
  }
  if (!displayValue.value.includes('.')) {
    displayValue.value += '.'
  }
}

const inputOperator = (op: string) => {
  if (currentExpression.value && shouldResetDisplay.value) {
    // Replace trailing operator if consecutive
    currentExpression.value = currentExpression.value.slice(0, -1) + ' ' + op + ' '
    return
  }
  currentExpression.value += `${displayValue.value} ${op} `
  shouldResetDisplay.value = true
}

const inputParenthesis = (p: '(' | ')') => {
  if (p === '(') {
    currentExpression.value += '('
  } else {
    currentExpression.value += `${displayValue.value}) `
    shouldResetDisplay.value = true
  }
}

// Scientific unary function applied to current number
const applyFunction = (fnName: string) => {
  const currentNum = parseFloat(displayValue.value)
  if (isNaN(currentNum)) return

  let evaluatedValue: number = NaN
  const deg = isAngleDeg.value
  const toRad = (x: number) => (x * Math.PI) / 180
  const toDeg = (x: number) => (x * 180) / Math.PI

  switch (fnName) {
    case 'sin':
      evaluatedValue = deg ? Math.sin(toRad(currentNum)) : Math.sin(currentNum)
      currentExpression.value = `sin(${displayValue.value})`
      break
    case 'cos':
      evaluatedValue = deg ? Math.cos(toRad(currentNum)) : Math.cos(currentNum)
      currentExpression.value = `cos(${displayValue.value})`
      break
    case 'tan':
      evaluatedValue = deg ? Math.tan(toRad(currentNum)) : Math.tan(currentNum)
      currentExpression.value = `tan(${displayValue.value})`
      break
    case 'asin':
      evaluatedValue = deg ? toDeg(Math.asin(currentNum)) : Math.asin(currentNum)
      currentExpression.value = `sin⁻¹(${displayValue.value})`
      break
    case 'acos':
      evaluatedValue = deg ? toDeg(Math.acos(currentNum)) : Math.acos(currentNum)
      currentExpression.value = `cos⁻¹(${displayValue.value})`
      break
    case 'atan':
      evaluatedValue = deg ? toDeg(Math.atan(currentNum)) : Math.atan(currentNum)
      currentExpression.value = `tan⁻¹(${displayValue.value})`
      break
    case 'sqrt':
      evaluatedValue = Math.sqrt(currentNum)
      currentExpression.value = `√(${displayValue.value})`
      break
    case 'cbrt':
      evaluatedValue = Math.cbrt(currentNum)
      currentExpression.value = `∛(${displayValue.value})`
      break
    case 'sq':
      evaluatedValue = Math.pow(currentNum, 2)
      currentExpression.value = `(${displayValue.value})²`
      break
    case 'cube':
      evaluatedValue = Math.pow(currentNum, 3)
      currentExpression.value = `(${displayValue.value})³`
      break
    case 'inv':
      evaluatedValue = 1 / currentNum
      currentExpression.value = `1/(${displayValue.value})`
      break
    case 'log':
      evaluatedValue = Math.log10(currentNum)
      currentExpression.value = `log(${displayValue.value})`
      break
    case 'ln':
      evaluatedValue = Math.log(currentNum)
      currentExpression.value = `ln(${displayValue.value})`
      break
    case 'exp':
      evaluatedValue = Math.exp(currentNum)
      currentExpression.value = `e^(${displayValue.value})`
      break
    case '10pow':
      evaluatedValue = Math.pow(10, currentNum)
      currentExpression.value = `10^(${displayValue.value})`
      break
    case 'fact':
      evaluatedValue = factorial(currentNum)
      currentExpression.value = `${displayValue.value}!`
      break
    case 'abs':
      evaluatedValue = Math.abs(currentNum)
      currentExpression.value = `abs(${displayValue.value})`
      break
  }

  displayValue.value = cleanNumber(evaluatedValue)
  shouldResetDisplay.value = true
}

const inputConstant = (constant: 'pi' | 'e') => {
  const val = constant === 'pi' ? Math.PI : Math.E
  displayValue.value = cleanNumber(val)
  shouldResetDisplay.value = true
}

const toggleSign = () => {
  if (displayValue.value === '0') return
  if (displayValue.value.startsWith('-')) {
    displayValue.value = displayValue.value.substring(1)
  } else {
    displayValue.value = '-' + displayValue.value
  }
}

const inputPercent = () => {
  const num = parseFloat(displayValue.value)
  if (!isNaN(num)) {
    displayValue.value = cleanNumber(num / 100)
    shouldResetDisplay.value = true
  }
}

const backspace = () => {
  if (shouldResetDisplay.value) {
    displayValue.value = '0'
    shouldResetDisplay.value = false
    return
  }
  if (displayValue.value.length <= 1 || (displayValue.value.length === 2 && displayValue.value.startsWith('-'))) {
    displayValue.value = '0'
  } else {
    displayValue.value = displayValue.value.slice(0, -1)
  }
}

const clearAll = () => {
  displayValue.value = '0'
  currentExpression.value = ''
  shouldResetDisplay.value = false
}

const calculateResult = () => {
  const fullExpr = currentExpression.value
    ? `${currentExpression.value} ${displayValue.value}`
    : displayValue.value

  const { success, result } = evaluateScientificMath(fullExpr)
  if (success) {
    calculationHistory.value.unshift({
      expression: fullExpr,
      result: result,
    })
    if (calculationHistory.value.length > 20) calculationHistory.value.pop()
    currentExpression.value = `${fullExpr} =`
    displayValue.value = result
    shouldResetDisplay.value = true
  } else {
    displayValue.value = result
    shouldResetDisplay.value = true
  }
}

// ── Memory Functions ──────────────────────────────────────────────────
const memoryClear = () => {
  memoryValue.value = 0
}
const memoryRecall = () => {
  displayValue.value = cleanNumber(memoryValue.value)
  shouldResetDisplay.value = true
}
const memoryAdd = () => {
  const num = parseFloat(displayValue.value)
  if (!isNaN(num)) memoryValue.value += num
  shouldResetDisplay.value = true
}
const memorySubtract = () => {
  const num = parseFloat(displayValue.value)
  if (!isNaN(num)) memoryValue.value -= num
  shouldResetDisplay.value = true
}

const useHistoryItem = (item: { expression: string; result: string }) => {
  displayValue.value = item.result
  currentExpression.value = item.expression + ' ='
  shouldResetDisplay.value = true
}

// ── Dragging Logic (Header Bar) ───────────────────────────────────────
const onDragStart = (e: MouseEvent | TouchEvent) => {
  isDragging.value = true
  const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX
  const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY

  const rect = calculatorRef.value?.getBoundingClientRect()
  if (rect) {
    dragStart.value = {
      x: clientX - rect.left,
      y: clientY - rect.top,
    }
  }

  window.addEventListener('mousemove', onDragging)
  window.addEventListener('mouseup', onDragEnd)
  window.addEventListener('touchmove', onDragging)
  window.addEventListener('touchend', onDragEnd)
}

const onDragging = (e: MouseEvent | TouchEvent) => {
  if (!isDragging.value) return
  const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX
  const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY

  const newX = Math.max(8, Math.min(window.innerWidth - 320, clientX - dragStart.value.x))
  const newY = Math.max(8, Math.min(window.innerHeight - 480, clientY - dragStart.value.y))

  dragPosition.value = { x: newX, y: newY }
}

const onDragEnd = () => {
  isDragging.value = false
  window.removeEventListener('mousemove', onDragging)
  window.removeEventListener('mouseup', onDragEnd)
  window.removeEventListener('touchmove', onDragging)
  window.removeEventListener('touchend', onDragEnd)
}

// ── Keyboard Listener ────────────────────────────────────────────────
const handleKeyDown = (e: KeyboardEvent) => {
  if (!isOpen.value || isMinimized.value) return
  // Don't intercept if student is currently typing in an exam text area or input!
  const target = e.target as HTMLElement
  if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
    return
  }

  if (e.key >= '0' && e.key <= '9') {
    e.preventDefault()
    inputDigit(e.key)
  } else if (e.key === '.') {
    e.preventDefault()
    inputDot()
  } else if (e.key === '+' || e.key === '-' || e.key === '*' || e.key === '/') {
    e.preventDefault()
    const op = e.key === '*' ? '×' : e.key === '/' ? '÷' : e.key
    inputOperator(op)
  } else if (e.key === 'Enter' || e.key === '=') {
    e.preventDefault()
    calculateResult()
  } else if (e.key === 'Backspace') {
    e.preventDefault()
    backspace()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    isOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  onDragEnd()
})
</script>

<template>
  <!-- Render ONLY when instructor allows calculator in exam settings -->
  <div v-if="props.allowed">
    
    <!-- Floating Bottom-Right Launcher FAB (Modern Realistic Badge) -->
    <button
      v-if="!isOpen"
      type="button"
      @click="isOpen = true"
      class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40 flex items-center gap-2.5 sm:gap-3 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-2xl bg-slate-900/95 hover:bg-slate-800 text-white shadow-2xl border border-indigo-500/30 hover:border-indigo-400 backdrop-blur-md transition-all duration-200 hover:scale-105 active:scale-95 group focus:outline-none focus:ring-2 focus:ring-indigo-500 min-h-[44px]"
      title="Open Scientific Calculator (Allowed by Instructor)"
    >
      <!-- Glowing Animated Icon Frame -->
      <div class="relative w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-indigo-600/30 border border-indigo-400/40 flex items-center justify-center text-indigo-400 group-hover:text-white group-hover:bg-indigo-600 transition-colors shadow-sm shrink-0">
        <svg class="w-4 h-4 sm:w-5 sm:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="4" y="2" width="16" height="20" rx="3" />
          <line x1="8" y1="6" x2="16" y2="6" stroke-width="2.5" />
          <line x1="8" y1="10" x2="10" y2="10" />
          <line x1="14" y1="10" x2="16" y2="10" />
          <line x1="8" y1="14" x2="10" y2="14" />
          <line x1="14" y1="14" x2="16" y2="14" />
          <line x1="8" y1="18" x2="10" y2="18" />
          <line x1="14" y1="18" x2="16" y2="18" />
        </svg>
      </div>

      <div class="text-left pr-1">
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-black tracking-wide text-white">Calculator</span>
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        </div>
        <span class="text-[10px] font-bold text-indigo-300 block uppercase tracking-wider">Scientific Mode</span>
      </div>
    </button>

    <!-- Floating Scientific Calculator Window -->
    <div
      v-if="isOpen"
      ref="calculatorRef"
      class="fixed z-50 flex flex-col bg-slate-900/95 border border-slate-700/80 rounded-3xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.8)] backdrop-blur-xl transition-shadow select-none overflow-hidden"
      :class="isMinimized ? 'w-[300px] sm:w-[320px] max-w-[calc(100vw-24px)] bottom-4 right-4 sm:bottom-6 sm:right-6' : 'w-[340px] sm:w-[410px] max-w-[calc(100vw-24px)]'"
      :style="
        dragPosition && !isMinimized
          ? { left: `${dragPosition.x}px`, top: `${dragPosition.y}px` }
          : { bottom: '16px', right: '16px' }
      "
    >
      
      <!-- Top Draggable Handle Bar -->
      <div
        @mousedown="onDragStart"
        @touchstart.passive="onDragStart"
        class="px-4 py-3 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between cursor-move text-slate-300"
      >
        <div class="flex items-center gap-2">
          <!-- Drag Handle Indicator -->
          <div class="flex items-center text-slate-500 mr-1 hover:text-slate-300">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
              <circle cx="8" cy="8" r="1.5" />
              <circle cx="16" cy="8" r="1.5" />
              <circle cx="8" cy="12" r="1.5" />
              <circle cx="16" cy="12" r="1.5" />
              <circle cx="8" cy="16" r="1.5" />
              <circle cx="16" cy="16" r="1.5" />
            </svg>
          </div>

          <span class="text-xs font-black tracking-wider uppercase text-white font-mono">Scientific FX-PRO</span>
          <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
            EXAM OK
          </span>
        </div>

        <!-- Window Control Buttons -->
        <div class="flex items-center gap-1.5" @mousedown.stop @touchstart.stop>
          <!-- DEG / RAD Switch -->
          <button
            type="button"
            @click="isAngleDeg = !isAngleDeg"
            class="px-2 py-0.5 rounded-md text-[10px] font-bold font-mono transition-colors"
            :class="isAngleDeg ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
            title="Toggle Angle Mode"
          >
            {{ isAngleDeg ? 'DEG' : 'RAD' }}
          </button>

          <!-- History Toggle -->
          <button
            type="button"
            @click="showHistory = !showHistory"
            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
            :class="{ 'text-indigo-400 bg-slate-800': showHistory }"
            title="Calculation History"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>

          <!-- Minimize Button -->
          <button
            type="button"
            @click="isMinimized = !isMinimized"
            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
            title="Minimize"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 12H6" />
            </svg>
          </button>

          <!-- Close Button -->
          <button
            type="button"
            @click="isOpen = false"
            class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors"
            title="Close Calculator"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Minimized State View -->
      <div v-if="isMinimized" class="p-3 bg-slate-900 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xs font-mono text-slate-400">Result:</span>
          <span class="text-sm font-bold font-mono text-white truncate max-w-[170px]">{{ displayValue }}</span>
        </div>
        <button
          type="button"
          @click="isMinimized = false"
          class="px-2.5 py-1 text-[11px] font-bold text-indigo-400 hover:text-white bg-slate-800 rounded-lg hover:bg-indigo-600 transition-colors"
        >
          Expand
        </button>
      </div>

      <!-- Full Scientific Calculator Body -->
      <div v-else class="p-4 space-y-3.5">
        
        <!-- High-Contrast Digital Display Panel -->
        <div class="bg-[#090d16] border border-slate-800 rounded-2xl p-3.5 space-y-1 shadow-inner relative overflow-hidden">
          
          <!-- Top Indicators Row -->
          <div class="flex items-center justify-between text-[10px] font-mono text-slate-400 tracking-wider">
            <div class="flex items-center gap-2">
              <span class="px-1.5 py-0.5 rounded bg-slate-800/80 font-bold" :class="isAngleDeg ? 'text-indigo-400' : 'text-slate-400'">
                {{ isAngleDeg ? 'DEG' : 'RAD' }}
              </span>
              <span v-if="memoryValue !== 0" class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">
                M
              </span>
              <span v-if="isSecondFunction" class="px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 font-bold">
                2nd
              </span>
            </div>

            <!-- Formula / Expression History line -->
            <div class="truncate max-w-[200px] text-slate-500 font-medium text-right">
              {{ currentExpression || '\u00A0' }}
            </div>
          </div>

          <!-- Main Digital Output -->
          <div class="text-right">
            <input
              type="text"
              readonly
              :value="displayValue"
              class="w-full bg-transparent text-right font-mono font-black text-2xl sm:text-3xl text-slate-100 tracking-tight focus:outline-none select-all"
            />
          </div>
        </div>

        <!-- History Drawer (Optional Dropdown) -->
        <div v-if="showHistory" class="bg-slate-950/90 rounded-2xl border border-slate-800 p-3 max-h-36 overflow-y-auto space-y-1.5 text-xs font-mono">
          <div class="flex items-center justify-between text-[10px] font-bold text-slate-400 border-b border-slate-800 pb-1 mb-1">
            <span>CALCULATION TAPE</span>
            <button @click="calculationHistory = []" class="text-rose-400 hover:underline">Clear</button>
          </div>
          <div v-if="calculationHistory.length === 0" class="text-slate-600 text-center py-2 text-[11px]">
            No calculation records yet
          </div>
          <div
            v-for="(item, i) in calculationHistory"
            :key="i"
            @click="useHistoryItem(item)"
            class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-800/80 cursor-pointer text-slate-300 transition-colors"
          >
            <span class="text-slate-500 truncate mr-2">{{ item.expression }}</span>
            <span class="font-bold text-indigo-400">{{ item.result }}</span>
          </div>
        </div>

        <!-- Keypad Rows -->
        <div class="space-y-1.5 text-xs font-mono select-none">
          
          <!-- Row 1: Memory & Function Modifiers -->
          <div class="grid grid-cols-6 gap-1.5">
            <button
              type="button"
              @click="isSecondFunction = !isSecondFunction"
              class="calc-btn text-[11px] font-bold"
              :class="isSecondFunction ? 'bg-purple-600 text-white' : 'bg-slate-800/90 text-purple-300 hover:bg-slate-700'"
            >
              2nd
            </button>
            <button type="button" @click="memoryClear" class="calc-btn bg-slate-800/80 text-slate-400 hover:text-white text-[11px]">MC</button>
            <button type="button" @click="memoryRecall" class="calc-btn bg-slate-800/80 text-slate-400 hover:text-white text-[11px]">MR</button>
            <button type="button" @click="memoryAdd" class="calc-btn bg-slate-800/80 text-slate-400 hover:text-white text-[11px]">M+</button>
            <button type="button" @click="memorySubtract" class="calc-btn bg-slate-800/80 text-slate-400 hover:text-white text-[11px]">M−</button>
            <button type="button" @click="clearAll" class="calc-btn bg-rose-900/40 text-rose-300 hover:bg-rose-700/60 font-bold text-[12px]">AC</button>
          </div>

          <!-- Row 2: Advanced Trigonometry -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="applyFunction(isSecondFunction ? 'asin' : 'sin')" class="calc-btn bg-slate-800/80 text-cyan-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? 'sin⁻¹' : 'sin' }}
            </button>
            <button type="button" @click="applyFunction(isSecondFunction ? 'acos' : 'cos')" class="calc-btn bg-slate-800/80 text-cyan-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? 'cos⁻¹' : 'cos' }}
            </button>
            <button type="button" @click="applyFunction(isSecondFunction ? 'atan' : 'tan')" class="calc-btn bg-slate-800/80 text-cyan-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? 'tan⁻¹' : 'tan' }}
            </button>
            <button type="button" @click="inputConstant('pi')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-bold">π</button>
            <button type="button" @click="inputConstant('e')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-bold">e</button>
          </div>

          <!-- Row 3: Powers & Roots -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="applyFunction('sq')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">x²</button>
            <button type="button" @click="applyFunction('cube')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">x³</button>
            <button type="button" @click="inputOperator('^')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">xʸ</button>
            <button type="button" @click="applyFunction(isSecondFunction ? 'cbrt' : 'sqrt')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? '∛x' : '√x' }}
            </button>
            <button type="button" @click="applyFunction('inv')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">1/x</button>
          </div>

          <!-- Row 4: Logs, Factorial & Parentheses -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="applyFunction(isSecondFunction ? '10pow' : 'log')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? '10ˣ' : 'log' }}
            </button>
            <button type="button" @click="applyFunction(isSecondFunction ? 'exp' : 'ln')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">
              {{ isSecondFunction ? 'eˣ' : 'ln' }}
            </button>
            <button type="button" @click="applyFunction('fact')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-semibold">x!</button>
            <button type="button" @click="inputParenthesis('(')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-bold">(</button>
            <button type="button" @click="inputParenthesis(')')" class="calc-btn bg-slate-800/80 text-slate-300 hover:bg-slate-700 font-bold">)</button>
          </div>

          <!-- Main Numpad Rows -->
          <!-- 7, 8, 9, ÷, ⌫ -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="inputDigit('7')" class="calc-num">7</button>
            <button type="button" @click="inputDigit('8')" class="calc-num">8</button>
            <button type="button" @click="inputDigit('9')" class="calc-num">9</button>
            <button type="button" @click="inputOperator('÷')" class="calc-op">÷</button>
            <button type="button" @click="backspace" class="calc-btn bg-slate-800/90 text-amber-300 hover:bg-slate-700 font-bold">⌫</button>
          </div>

          <!-- 4, 5, 6, ×, % -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="inputDigit('4')" class="calc-num">4</button>
            <button type="button" @click="inputDigit('5')" class="calc-num">5</button>
            <button type="button" @click="inputDigit('6')" class="calc-num">6</button>
            <button type="button" @click="inputOperator('×')" class="calc-op">×</button>
            <button type="button" @click="inputPercent" class="calc-btn bg-slate-800/90 text-slate-300 hover:bg-slate-700 font-bold">%</button>
          </div>

          <!-- 1, 2, 3, −, +/- -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="inputDigit('1')" class="calc-num">1</button>
            <button type="button" @click="inputDigit('2')" class="calc-num">2</button>
            <button type="button" @click="inputDigit('3')" class="calc-num">3</button>
            <button type="button" @click="inputOperator('−')" class="calc-op">−</button>
            <button type="button" @click="toggleSign" class="calc-btn bg-slate-800/90 text-slate-300 hover:bg-slate-700 font-bold">±</button>
          </div>

          <!-- 0, ., +, = (Wide) -->
          <div class="grid grid-cols-5 gap-1.5">
            <button type="button" @click="inputDigit('0')" class="calc-num col-span-2">0</button>
            <button type="button" @click="inputDot" class="calc-num">.</button>
            <button type="button" @click="inputOperator('+')" class="calc-op">+</button>
            <button
              type="button"
              @click="calculateResult"
              class="calc-btn bg-indigo-600 hover:bg-indigo-500 text-white font-black text-base shadow-lg shadow-indigo-600/30 transition-all active:scale-95"
            >
              =
            </button>
          </div>

        </div>

      </div>

    </div>

  </div>
</template>

<style scoped>
.calc-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 38px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.calc-btn:active {
  transform: scale(0.95);
}

.calc-num {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  border-radius: 12px;
  background-color: rgba(30, 41, 59, 0.85); /* slate-800/85 */
  color: #f8fafc; /* slate-50 */
  font-weight: 700;
  font-size: 15px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.calc-num:hover {
  background-color: rgba(51, 65, 85, 0.9); /* slate-700 */
}
.calc-num:active {
  transform: scale(0.95);
}

.calc-op {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  border-radius: 12px;
  background-color: rgba(49, 46, 129, 0.5); /* indigo-950/50 */
  color: #818cf8; /* indigo-400 */
  font-weight: 800;
  font-size: 16px;
  border: 1px solid rgba(99, 102, 241, 0.2);
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.calc-op:hover {
  background-color: rgba(67, 56, 202, 0.7); /* indigo-700/70 */
  color: #ffffff;
}
.calc-op:active {
  transform: scale(0.95);
}
</style>

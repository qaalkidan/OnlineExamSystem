<script setup lang="ts">
import { ref, computed } from 'vue'
import * as XLSX from 'xlsx'
import { useCreateExamStore } from '../../store/createExamStore'

const emit = defineEmits(['imported', 'switch-to-manual', 'switch-to-bank'])
const formStore = useCreateExamStore()

interface ParsedQuestionRow {
  id: string
  rawIndex: number
  type: string
  instruction: string
  text: string
  difficulty: string
  marks: number
  options: string[]
  correct_answer: string
  description?: string
  status: 'valid' | 'warning' | 'error'
  statusMessage?: string
  // Matching specific
  column_a?: string[]
  column_b?: { label: string, text: string }[]
  correct_answers?: Record<string, string>
  pairs?: { left: string, right: string }[]
  marks_per_item?: number
}

const fileInputRef = ref<HTMLInputElement | null>(null)
const fileName = ref('')
const fileSize = ref('')
const isParsing = ref(false)
const parseError = ref('')
const parsedQuestions = ref<ParsedQuestionRow[]>([])
const selectedRowIds = ref<string[]>([])
const isDragging = ref(false)
const successBanner = ref('')

// Template generation and download
const downloadTemplate = (format: 'xlsx' | 'csv') => {
  const sampleHeaders = [
    'Type',
    'Question Text',
    'Instruction',
    'Difficulty',
    'Marks',
    'Option A',
    'Option B',
    'Option C',
    'Option D',
    'Correct Answer',
    'Explanation'
  ]

  const sampleRows = [
    [
      'multiple_choice',
      'What does HTTP stand for?',
      'Choose the correct answer',
      'Easy',
      5,
      'HyperText Transfer Protocol',
      'High Transfer Text Protocol',
      'HyperText Test Program',
      'Hyperlink Telecommunication Protocol',
      'A',
      'HTTP stands for HyperText Transfer Protocol.'
    ],
    [
      'multiple_choice',
      'Which layer of the OSI model does IP (Internet Protocol) operate at?',
      'Choose the correct answer',
      'Medium',
      5,
      'Data Link Layer',
      'Network Layer',
      'Transport Layer',
      'Application Layer',
      'B',
      'IP functions at the Network Layer (Layer 3).'
    ],
    [
      'true_false',
      'TCP is a connection-oriented protocol while UDP is connectionless.',
      'Select True or False',
      'Easy',
      3,
      'True',
      'False',
      '',
      '',
      'True',
      'TCP provides reliable, ordered connection-oriented byte streams.'
    ],
    [
      'fill_blank',
      'The default port for secure web traffic using HTTPS is _____',
      'Fill in the blank with the correct value',
      'Medium',
      3,
      '',
      '',
      '',
      '',
      '443',
      'HTTPS operates on port 443 by default.'
    ],
    [
      'short_answer',
      'Explain the difference between a switch and a router in computer networking.',
      'Provide a clear and concise answer',
      'Hard',
      10,
      '',
      '',
      '',
      '',
      'A switch operates at Layer 2 to connect devices within the same LAN using MAC addresses, while a router operates at Layer 3 to route packets between different networks using IP addresses.',
      'Award full points for mentioning Layer 2 vs Layer 3 and LAN vs inter-network routing.'
    ]
  ]

  const wsData = [sampleHeaders, ...sampleRows]
  const ws = XLSX.utils.aoa_to_sheet(wsData)

  // Set column widths
  ws['!cols'] = [
    { wch: 18 }, // Type
    { wch: 45 }, // Question Text
    { wch: 28 }, // Instruction
    { wch: 12 }, // Difficulty
    { wch: 8 },  // Marks
    { wch: 26 }, // Option A
    { wch: 26 }, // Option B
    { wch: 26 }, // Option C
    { wch: 26 }, // Option D
    { wch: 15 }, // Correct Answer
    { wch: 35 }  // Explanation
  ]

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Questions')

  if (format === 'xlsx') {
    XLSX.writeFile(wb, 'exam_questions_template.xlsx')
  } else {
    XLSX.writeFile(wb, 'exam_questions_template.csv')
  }
}

// Normalize question type
const normalizeType = (raw: string): string => {
  const s = (raw || '').toLowerCase().trim()
  if (s.includes('mcq') || s.includes('choice') || s === 'multiple_choice') return 'multiple_choice'
  if (s.includes('true') || s.includes('false') || s === 'tf' || s === 'true_false') return 'true_false'
  if (s.includes('blank') || s.includes('fib') || s === 'fill_blank') return 'fill_blank'
  if (s.includes('short') || s.includes('sa') || s === 'short_answer') return 'short_answer'
  if (s.includes('match') || s === 'matching') return 'matching'
  return 'multiple_choice'
}

// File drop & select handlers
const onFileDrop = (e: DragEvent) => {
  isDragging.value = false
  if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    processFile(e.dataTransfer.files[0])
  }
}

const onFileSelected = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    processFile(target.files[0])
  }
}

const triggerFileSelect = () => {
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
    fileInputRef.value.click()
  }
}

const formatBytes = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Process uploaded file
const processFile = async (file: File) => {
  parseError.value = ''
  successBanner.value = ''
  parsedQuestions.value = []
  selectedRowIds.value = []
  fileName.value = file.name
  fileSize.value = formatBytes(file.size)

  const isExcel = file.name.endsWith('.xlsx') || file.name.endsWith('.xls')
  const isCsv = file.name.endsWith('.csv') || file.type === 'text/csv'
  const isJson = file.name.endsWith('.json') || file.type === 'application/json'

  if (!isExcel && !isCsv && !isJson) {
    parseError.value = 'Unsupported file format. Please upload an Excel (.xlsx/.xls), CSV (.csv), or JSON (.json) file.'
    return
  }

  isParsing.value = true

  try {
    if (isJson) {
      const text = await file.text()
      const data = JSON.parse(text)
      parseJsonData(data)
    } else {
      const buffer = await file.arrayBuffer()
      const wb = XLSX.read(buffer, { type: 'array' })
      const firstSheetName = wb.SheetNames[0]
      if (!firstSheetName) {
        throw new Error('Workbook contains no sheets.')
      }
      const sheet = wb.Sheets[firstSheetName]
      const rawRows = XLSX.utils.sheet_to_json(sheet, { defval: '' }) as any[]
      parseSpreadsheetRows(rawRows)
    }
  } catch (err: any) {
    console.error('File parsing failed:', err)
    parseError.value = `Failed to parse file: ${err.message || 'Invalid format'}`
  } finally {
    isParsing.value = false
  }
}

// Parse Spreadsheet Rows
const parseSpreadsheetRows = (rows: any[]) => {
  if (rows.length === 0) {
    parseError.value = 'The uploaded file is empty. Please provide questions.'
    return
  }

  const results: ParsedQuestionRow[] = []

  rows.forEach((row, idx) => {
    // Find keys regardless of casing/spacing
    const getVal = (...keys: string[]) => {
      for (const k of keys) {
        if (row[k] !== undefined && row[k] !== null && String(row[k]).trim() !== '') {
          return String(row[k]).trim()
        }
        // Try case-insensitive matching
        const found = Object.keys(row).find(rk => rk.toLowerCase().replace(/[\s_-]/g, '') === k.toLowerCase().replace(/[\s_-]/g, ''))
        if (found && row[found] !== undefined && row[found] !== null && String(row[found]).trim() !== '') {
          return String(row[found]).trim()
        }
      }
      return ''
    }

    const rawType = getVal('type', 'question_type', 'questiontype')
    const type = normalizeType(rawType)
    const text = getVal('question text', 'question_text', 'question', 'text', 'prompt')
    const instruction = getVal('instruction', 'group instruction', 'instructor instruction')
    const difficultyRaw = getVal('difficulty', 'level') || 'Medium'
    const marksRaw = Number(getVal('marks', 'mark', 'score', 'points')) || 5
    const explanation = getVal('explanation', 'description', 'rubric')

    // Options
    const optA = getVal('option a', 'optiona', 'a', 'choice a')
    const optB = getVal('option b', 'optionb', 'b', 'choice b')
    const optC = getVal('option c', 'optionc', 'c', 'choice c')
    const optD = getVal('option d', 'optiond', 'd', 'choice d')

    let options: string[] = []
    if (type === 'multiple_choice') {
      if (optA || optB) {
        options = [optA, optB, optC, optD].filter(o => o !== '')
      } else {
        const combinedOpts = getVal('options', 'choices')
        if (combinedOpts) {
          options = combinedOpts.split(/[|,;]/).map(s => s.trim()).filter(Boolean)
        }
      }
    } else if (type === 'true_false') {
      options = ['True', 'False']
    }

    let correctAnswer = getVal('correct answer', 'correct_answer', 'answer', 'key')
    if (type === 'multiple_choice') {
      correctAnswer = correctAnswer.toUpperCase()
      // If answer is text matching one of options, get the letter
      if (options.length > 0 && !['A', 'B', 'C', 'D', 'E'].includes(correctAnswer)) {
        const matchIdx = options.findIndex(o => o.toLowerCase() === correctAnswer.toLowerCase())
        if (matchIdx >= 0) {
          correctAnswer = String.fromCharCode(65 + matchIdx)
        }
      }
    } else if (type === 'true_false') {
      const lower = correctAnswer.toLowerCase()
      if (lower.startsWith('t') || lower === '1') correctAnswer = 'True'
      else if (lower.startsWith('f') || lower === '0') correctAnswer = 'False'
    }

    // Row validation
    let status: 'valid' | 'warning' | 'error' = 'valid'
    let statusMessage = ''

    if (!text) {
      status = 'error'
      statusMessage = 'Missing question text'
    } else if (type === 'multiple_choice' && options.length < 2) {
      status = 'error'
      statusMessage = 'MCQ requires at least 2 options (Option A and Option B)'
    } else if (!correctAnswer) {
      status = 'warning'
      statusMessage = 'Missing correct answer (defaults to first option)'
      correctAnswer = type === 'multiple_choice' ? 'A' : (type === 'true_false' ? 'True' : 'Option A')
    }

    const rowId = `row-${idx}-${Date.now()}`
    results.push({
      id: rowId,
      rawIndex: idx + 1,
      type,
      instruction: instruction || (type === 'multiple_choice' ? 'Choose the correct answer' : (type === 'true_false' ? 'Select True or False' : 'Answer the question')),
      text,
      difficulty: ['Easy', 'Medium', 'Hard'].includes(difficultyRaw) ? difficultyRaw : 'Medium',
      marks: marksRaw > 0 ? marksRaw : 5,
      options,
      correct_answer: correctAnswer,
      description: explanation,
      status,
      statusMessage
    })
  })

  parsedQuestions.value = results
  // Pre-select all valid & warning rows
  selectedRowIds.value = results.filter(r => r.status !== 'error').map(r => r.id)
}

// Parse JSON data
const parseJsonData = (data: any) => {
  const rows = Array.isArray(data) ? data : (data.questions || [])
  if (rows.length === 0) {
    parseError.value = 'No question array found in JSON file.'
    return
  }
  parseSpreadsheetRows(rows)
}

// Selection helpers
const validQuestions = computed(() => parsedQuestions.value.filter(q => q.status !== 'error'))
const isAllValidSelected = computed(() => {
  if (validQuestions.value.length === 0) return false
  return validQuestions.value.every(q => selectedRowIds.value.includes(q.id))
})

const toggleSelectAll = () => {
  if (isAllValidSelected.value) {
    selectedRowIds.value = []
  } else {
    selectedRowIds.value = validQuestions.value.map(q => q.id)
  }
}

const toggleRow = (id: string) => {
  const idx = selectedRowIds.value.indexOf(id)
  if (idx > -1) {
    selectedRowIds.value.splice(idx, 1)
  } else {
    selectedRowIds.value.push(id)
  }
}

const selectedCount = computed(() => selectedRowIds.value.length)
const selectedMarks = computed(() => {
  return parsedQuestions.value
    .filter(q => selectedRowIds.value.includes(q.id))
    .reduce((sum, q) => sum + q.marks, 0)
})

// Clear / Reset uploaded file
const clearFile = () => {
  fileName.value = ''
  fileSize.value = ''
  parsedQuestions.value = []
  selectedRowIds.value = []
  parseError.value = ''
  successBanner.value = ''
  if (fileInputRef.value) fileInputRef.value.value = ''
}

// Import parsed questions into exam draft
const importSelectedQuestions = () => {
  const questionsToImport = parsedQuestions.value.filter(q => selectedRowIds.value.includes(q.id))
  if (questionsToImport.length === 0) return

  let importedCount = 0
  let totalMarks = 0

  questionsToImport.forEach(q => {
    let corrAns = q.correct_answer || ''
    if (q.type === 'true_false') {
      const lower = String(corrAns).toLowerCase().trim()
      if (lower === 'true' || lower === 'a' || lower === '1' || lower === 'yes') corrAns = 'True'
      else if (lower === 'false' || lower === 'b' || lower === '0' || lower === 'no') corrAns = 'False'
    }

    formStore.questions.push({
      type: q.type,
      instruction: q.instruction,
      text: q.text,
      difficulty: q.difficulty,
      marks: q.marks,
      description: q.description || '',
      options: q.options,
      correct_answer: corrAns,
      question_data: (q as any).question_data || {},
    })
    importedCount++
    totalMarks += q.marks
  })

  successBanner.value = `Successfully imported ${importedCount} question${importedCount !== 1 ? 's' : ''} (${totalMarks} marks) from "${fileName.value}" into this exam!`
  emit('imported', { count: importedCount, marks: totalMarks })

  // Clear parsed file after successful import
  setTimeout(() => {
    clearFile()
  }, 1500)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Success Banner -->
    <div v-if="successBanner" class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between text-emerald-800 shadow-xs animate-in fade-in duration-200">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div>
          <p class="text-[13px] font-bold">{{ successBanner }}</p>
          <p class="text-[11px] text-emerald-600 mt-0.5">Total questions currently in this exam: <strong class="text-emerald-800">{{ formStore.questions.length }}</strong></p>
        </div>
      </div>
      <button @click="successBanner = ''" class="text-emerald-500 hover:text-emerald-700 font-bold text-sm px-2 py-1">&times;</button>
    </div>

    <!-- Top Card: File Upload & Templates -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">
        <div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <h3 class="text-[16px] font-bold text-slate-800">Import Questions from File</h3>
          </div>
          <p class="text-[12px] text-slate-500 mt-1">Upload questions in bulk via Excel spreadsheet (.xlsx), CSV, or JSON format.</p>
        </div>

        <!-- Template Download Buttons -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="downloadTemplate('xlsx')"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[12px] font-bold rounded-xl border border-emerald-200 transition-colors cursor-pointer"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Excel Template (.xlsx)
          </button>
          <button
            type="button"
            @click="downloadTemplate('csv')"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-[12px] font-bold rounded-xl border border-slate-200 transition-colors cursor-pointer"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            CSV Template
          </button>
        </div>
      </div>

      <!-- Hidden file input -->
      <input
        ref="fileInputRef"
        type="file"
        accept=".xlsx,.xls,.csv,.json"
        class="hidden"
        @change="onFileSelected"
      />

      <!-- Dropzone (when no file selected yet) -->
      <div
        v-if="!fileName"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onFileDrop"
        @click="triggerFileSelect"
        :class="[
          isDragging ? 'border-[#5138ed] bg-indigo-50/40' : 'border-slate-300 hover:border-indigo-400 bg-slate-50/50 hover:bg-indigo-50/20',
          'border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all'
        ]"
      >
        <div class="w-14 h-14 bg-indigo-50 text-[#5138ed] rounded-2xl flex items-center justify-center mx-auto mb-3.5 shadow-xs">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
        </div>
        <p class="text-[14px] font-bold text-slate-800 mb-1">Click to upload or drag &amp; drop question file</p>
        <p class="text-[12px] text-slate-400 max-w-sm mx-auto mb-3">Supports Excel (.xlsx, .xls), Comma-Separated Values (.csv), or JSON format</p>
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-lg text-[11px] font-bold text-slate-600">
          <span>Max file size: 10MB</span>
        </div>
      </div>

      <!-- Selected File Info Banner (when file is uploaded) -->
      <div v-else class="p-4 bg-indigo-50/40 border border-indigo-100 rounded-2xl flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-[#5138ed] text-white flex items-center justify-center font-bold text-sm shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div>
            <p class="text-[13px] font-bold text-slate-800 flex items-center gap-2">
              {{ fileName }}
              <span class="text-[11px] font-normal text-slate-400">({{ fileSize }})</span>
            </p>
            <p class="text-[11px] text-indigo-600 mt-0.5">
              {{ parsedQuestions.length }} total rows parsed • {{ validQuestions.length }} valid questions ready
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="triggerFileSelect"
            class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-[12px] font-bold rounded-xl transition-colors cursor-pointer"
          >
            Change File
          </button>
          <button
            type="button"
            @click="clearFile"
            class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer"
            title="Remove File"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
          </button>
        </div>
      </div>

      <!-- Parsing Error Alert -->
      <div v-if="parseError" class="mt-4 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-[12.5px] font-medium flex items-center gap-2">
        <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <span>{{ parseError }}</span>
      </div>
    </div>

    <!-- Parsed Questions Preview Table -->
    <div v-if="parsedQuestions.length > 0" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
      <!-- Table Header & Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 border-b border-slate-100 bg-slate-50/60">
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              :checked="isAllValidSelected"
              @change="toggleSelectAll"
              class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer"
            />
            <span class="text-[13px] font-bold text-slate-700">
              Select All Valid ({{ validQuestions.length }})
            </span>
          </label>

          <span v-if="selectedCount > 0" class="text-[12px] font-bold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-100">
            {{ selectedCount }} selected • {{ selectedMarks }} marks
          </span>
        </div>

        <button
          type="button"
          @click="importSelectedQuestions"
          :disabled="selectedCount === 0"
          class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          Import Selected ({{ selectedCount }}) to Exam
        </button>
      </div>

      <!-- Preview Table -->
      <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 bg-white sticky top-0 z-10 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">#</th>
              <th class="py-3 px-4 w-28">Status</th>
              <th class="py-3 px-4 w-32">Type</th>
              <th class="py-3 px-4">Question Text &amp; Options</th>
              <th class="py-3 px-4 w-32">Answer</th>
              <th class="py-3 px-4 w-20 text-center">Marks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-[13px]">
            <tr
              v-for="q in parsedQuestions"
              :key="q.id"
              :class="[
                q.status === 'error' ? 'bg-rose-50/30' : (selectedRowIds.includes(q.id) ? 'bg-indigo-50/15' : 'hover:bg-slate-50/60'),
                'transition-colors'
              ]"
            >
              <!-- Checkbox -->
              <td class="py-3 px-4 text-center">
                <input
                  v-if="q.status !== 'error'"
                  type="checkbox"
                  :value="q.id"
                  v-model="selectedRowIds"
                  class="w-4 h-4 rounded border-slate-300 text-[#5138ed] focus:ring-[#5138ed] cursor-pointer"
                />
                <span v-else class="text-rose-400 font-bold text-xs">&times;</span>
              </td>

              <!-- Status -->
              <td class="py-3 px-4">
                <span
                  v-if="q.status === 'valid'"
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full"
                >
                  <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  Ready
                </span>
                <span
                  v-else-if="q.status === 'warning'"
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full"
                  :title="q.statusMessage"
                >
                  Warning
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full"
                  :title="q.statusMessage"
                >
                  Invalid
                </span>
                <p v-if="q.statusMessage" class="text-[10px] text-rose-500 mt-1 leading-tight">{{ q.statusMessage }}</p>
              </td>

              <!-- Type -->
              <td class="py-3 px-4">
                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 uppercase">
                  {{ q.type === 'multiple_choice' ? 'MCQ' : (q.type === 'true_false' ? 'True/False' : (q.type === 'fill_blank' ? 'Fill Blank' : (q.type === 'short_answer' ? 'Short Answer' : q.type))) }}
                </span>
                <p class="text-[10px] text-slate-400 mt-0.5">{{ q.difficulty }}</p>
              </td>

              <!-- Question Text & Options -->
              <td class="py-3 px-4 max-w-md">
                <p class="font-medium text-slate-800 leading-snug">{{ q.text }}</p>
                <div v-if="q.options && q.options.length > 0" class="flex flex-wrap gap-1.5 mt-1.5">
                  <span
                    v-for="(opt, oIdx) in q.options"
                    :key="oIdx"
                    :class="[
                      (String.fromCharCode(65 + oIdx) === q.correct_answer)
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-semibold'
                        : 'bg-slate-50 text-slate-600 border-slate-200',
                      'text-[10.5px] px-2 py-0.5 rounded-md border'
                    ]"
                  >
                    {{ String.fromCharCode(65 + oIdx) }}: {{ opt }}
                  </span>
                </div>
              </td>

              <!-- Answer -->
              <td class="py-3 px-4">
                <span class="font-bold text-[12px] text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md">
                  {{ q.correct_answer }}
                </span>
              </td>

              <!-- Marks -->
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ q.marks }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

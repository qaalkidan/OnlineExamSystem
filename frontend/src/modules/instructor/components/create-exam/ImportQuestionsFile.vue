<script setup lang="ts">
import { ref, computed } from 'vue'
import { useCreateExamStore } from '../../store/createExamStore'
import {
  parseAndValidateSpreadsheet,
  parseAndValidateJson,
  downloadQuestionTemplate,
  formatBytes,
  type ParsedQuestionRow,
  type FileValidationResult
} from '../../utils/questionImportValidator'
import apiClient from '../../../../core/api/apiClient'

const emit = defineEmits(['imported', 'switch-to-manual', 'switch-to-bank'])
const formStore = useCreateExamStore()

const fileInputRef = ref<HTMLInputElement | null>(null)
const fileName = ref('')
const fileSize = ref('')
const isParsing = ref(false)
const isImporting = ref(false)
const parseError = ref('')
const parsedQuestions = ref<ParsedQuestionRow[]>([])
const selectedRowIds = ref<string[]>([])
const isDragging = ref(false)
const successBanner = ref('')

// Summary counts
const totalRows = ref(0)
const validCount = ref(0)
const invalidCount = ref(0)
const skippedRows = ref(0)

// Template generation and download
const downloadTemplate = (format: 'xlsx' | 'csv' | 'json') => {
  downloadQuestionTemplate(format)
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

// Clear / Reset uploaded file
const clearFile = () => {
  fileName.value = ''
  fileSize.value = ''
  parsedQuestions.value = []
  selectedRowIds.value = []
  parseError.value = ''
  successBanner.value = ''
  totalRows.value = 0
  validCount.value = 0
  invalidCount.value = 0
  skippedRows.value = 0
  if (fileInputRef.value) fileInputRef.value.value = ''
}

// Process uploaded file
const processFile = async (file: File) => {
  parseError.value = ''
  successBanner.value = ''
  parsedQuestions.value = []
  selectedRowIds.value = []
  totalRows.value = 0
  validCount.value = 0
  invalidCount.value = 0
  skippedRows.value = 0

  fileName.value = file.name
  fileSize.value = formatBytes(file.size)

  const lowerName = file.name.toLowerCase()
  const isExcel = lowerName.endsWith('.xlsx') || lowerName.endsWith('.xls')
  const isCsv = lowerName.endsWith('.csv') || file.type === 'text/csv'
  const isJson = lowerName.endsWith('.json') || file.type === 'application/json'

  if (!isExcel && !isCsv && !isJson) {
    parseError.value = `Unsupported file format. Please upload an Excel (.xlsx/.xls), CSV (.csv), or JSON (.json) file.`
    return
  }

  isParsing.value = true

  try {
    let result: FileValidationResult

    if (isJson) {
      result = await parseAndValidateJson(file)
    } else {
      result = await parseAndValidateSpreadsheet(file)
    }

    if (!result.success) {
      parseError.value = result.message || 'The uploaded file failed question validation.'
      parsedQuestions.value = []
      return
    }

    // Update counts and parsed questions list
    totalRows.value = result.totalRows
    validCount.value = result.validCount
    invalidCount.value = result.invalidCount
    skippedRows.value = result.skippedRows
    parsedQuestions.value = result.questions

    // Pre-select ONLY valid questions (invalid questions must never be selectable)
    selectedRowIds.value = result.questions.filter(q => q.status === 'valid').map(q => q.id)

    // Also attempt backend validation verification if available
    try {
      const formData = new FormData()
      formData.append('file', file)
      await apiClient.post('/instructor/exams/validate-import', formData)
    } catch (backendErr: any) {
      // If backend explicitly rejected the file with a validation error
      if (backendErr.response?.status === 422) {
        const backendMsg = backendErr.response?.data?.message
        const fileErrors = backendErr.response?.data?.errors?.file
        if (fileErrors && fileErrors.length > 0) {
          parseError.value = fileErrors[0]
          parsedQuestions.value = []
          return
        } else if (backendMsg) {
          parseError.value = backendMsg
        }
      }
    }
  } catch (err: any) {
    console.error('File parsing failed:', err)
    parseError.value = `Failed to parse file: ${err.message || 'Invalid format or corrupted file.'}`
    parsedQuestions.value = []
  } finally {
    isParsing.value = false
  }
}

// Selection helpers
const validQuestions = computed(() => parsedQuestions.value.filter(q => q.status === 'valid'))
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

const toggleRow = (id: string, isValid: boolean) => {
  if (!isValid) return // Block selecting invalid questions
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
    .reduce((sum, q) => sum + (Number(q.marks) || 0), 0)
})

// Import parsed questions into exam draft
const importSelectedQuestions = () => {
  // Only import valid selected questions
  const questionsToImport = parsedQuestions.value.filter(
    q => selectedRowIds.value.includes(q.id) && q.status === 'valid'
  )
  if (questionsToImport.length === 0) return

  isImporting.value = true

  let importedCount = 0
  let totalImportMarks = 0

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
    totalImportMarks += q.marks
  })

  successBanner.value = `Successfully imported ${importedCount} valid question${importedCount !== 1 ? 's' : ''} (${totalImportMarks} marks) from "${fileName.value}" into this exam!`
  emit('imported', { count: importedCount, marks: totalImportMarks })

  isImporting.value = false

  // Clear parsed file after successful import
  setTimeout(() => {
    clearFile()
  }, 2000)
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
      <button @click="successBanner = ''" class="text-emerald-500 hover:text-emerald-700 font-bold text-sm px-2 py-1 cursor-pointer">&times;</button>
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
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="downloadTemplate('xlsx')"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[12px] font-bold rounded-xl border border-emerald-200 transition-colors cursor-pointer shadow-2xs"
            title="Download Excel format template with instructions"
          >
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Excel Template (.xlsx)
          </button>
          <button
            type="button"
            @click="downloadTemplate('csv')"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-[12px] font-bold rounded-xl border border-slate-200 transition-colors cursor-pointer shadow-2xs"
            title="Download CSV format template"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            CSV Template
          </button>
          <button
            type="button"
            @click="downloadTemplate('json')"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-[#4F35F3] text-[12px] font-bold rounded-xl border border-indigo-200 transition-colors cursor-pointer shadow-2xs"
            title="Download JSON format template"
          >
            <svg class="w-4 h-4 text-[#4F35F3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            JSON Template (.json)
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

      <!-- Dropzone (when no file selected yet and not parsing) -->
      <div
        v-if="!fileName && !isParsing"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onFileDrop"
        @click="triggerFileSelect"
        :class="[
          isDragging ? 'border-[#4F35F3] bg-indigo-50/40' : 'border-slate-300 hover:border-indigo-400 bg-slate-50/50 hover:bg-indigo-50/20',
          'border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all'
        ]"
      >
        <div class="w-14 h-14 bg-indigo-50 text-[#4F35F3] rounded-2xl flex items-center justify-center mx-auto mb-3.5 shadow-xs">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
        </div>
        <p class="text-[14px] font-bold text-slate-800 mb-1">Click to upload or drag &amp; drop question file</p>
        <p class="text-[12px] text-slate-400 max-w-sm mx-auto mb-3">Accepts Excel (.xlsx, .xls), CSV (.csv), or JSON format according to the exam question format</p>
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 rounded-lg text-[11px] font-bold text-slate-600">
          <span>Supported types: MCQ, True/False, Fill in Blank, Short Answer, Matching</span>
          <span>•</span>
          <span>Max: 10MB</span>
        </div>
      </div>

      <!-- Parsing Loading Indicator -->
      <div v-else-if="isParsing" class="p-8 border-2 border-dashed border-indigo-200 rounded-2xl text-center bg-indigo-50/30">
        <div class="w-10 h-10 border-3 border-[#4F35F3] border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-[14px] font-bold text-slate-800">Validating and parsing question file...</p>
        <p class="text-[12px] text-slate-500 mt-1">Verifying file structure, question types, required fields, and option formats</p>
      </div>

      <!-- Selected File Info Banner (when file is uploaded) -->
      <div v-else class="p-4 bg-indigo-50/40 border border-indigo-100 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-[#4F35F3] text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div class="min-w-0">
            <p class="text-[13px] font-bold text-slate-800 flex items-center gap-2 truncate">
              <span class="truncate">{{ fileName }}</span>
              <span class="text-[11px] font-normal text-slate-400 shrink-0">({{ fileSize }})</span>
            </p>
            <div class="flex flex-wrap items-center gap-2 text-[11px] mt-1">
              <span class="text-slate-600 font-semibold">{{ totalRows }} total row{{ totalRows !== 1 ? 's' : '' }} detected</span>
              <span class="text-slate-300">•</span>
              <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                {{ validCount }} valid question{{ validCount !== 1 ? 's' : '' }} ready
              </span>
              <span v-if="invalidCount > 0" class="text-rose-700 font-bold bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                {{ invalidCount }} invalid row{{ invalidCount !== 1 ? 's' : '' }} blocked
              </span>
              <span v-if="skippedRows > 0" class="text-slate-500 font-medium bg-slate-100 px-2 py-0.5 rounded-md">
                {{ skippedRows }} empty row{{ skippedRows !== 1 ? 's' : '' }} skipped
              </span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
          <button
            type="button"
            @click="triggerFileSelect"
            class="px-3.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-[12px] font-bold rounded-xl transition-colors cursor-pointer shadow-2xs"
          >
            Change File
          </button>
          <button
            type="button"
            @click="clearFile"
            class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer"
            title="Remove File"
            aria-label="Remove File"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
          </button>
        </div>
      </div>

      <!-- Parsing Error Alert -->
      <div v-if="parseError" class="mt-4 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-[12.5px] font-medium flex items-start gap-3 shadow-2xs">
        <div class="w-6 h-6 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div class="flex-1">
          <p class="font-bold text-rose-900">File Validation Failed</p>
          <p class="text-rose-700 mt-0.5 leading-relaxed">{{ parseError }}</p>
          <div class="mt-2.5 flex items-center gap-2">
            <button
              type="button"
              @click="downloadTemplate('xlsx')"
              class="text-[11.5px] font-bold text-[#4F35F3] hover:underline cursor-pointer"
            >
              Download Example Excel Template →
            </button>
            <span class="text-rose-300">•</span>
            <button
              type="button"
              @click="clearFile"
              class="text-[11.5px] font-semibold text-rose-600 hover:text-rose-800 cursor-pointer"
            >
              Upload a different file
            </button>
          </div>
        </div>
      </div>

      <!-- Warning Banner if some questions are invalid -->
      <div v-if="invalidCount > 0 && parsedQuestions.length > 0" class="mt-4 p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-[12px] flex items-start gap-2.5">
        <svg class="w-4.5 h-4.5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
          <span class="font-bold">Attention:</span>
          <span> {{ invalidCount }} row(s) in this file failed validation against the exam question schema and cannot be imported. You can import the <strong>{{ validCount }} valid question(s)</strong> below, or fix the errors in your spreadsheet and upload again.</span>
        </div>
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
              :disabled="validCount === 0"
              @change="toggleSelectAll"
              class="w-4 h-4 rounded border-slate-300 text-[#4F35F3] focus:ring-[#4F35F3] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
            />
            <span class="text-[13px] font-bold text-slate-700">
              Select All Valid ({{ validCount }})
            </span>
          </label>

          <span v-if="selectedCount > 0" class="text-[12px] font-bold text-[#4F35F3] bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-100">
            {{ selectedCount }} selected • {{ selectedMarks }} marks
          </span>
        </div>

        <button
          type="button"
          @click="importSelectedQuestions"
          :disabled="selectedCount === 0 || isImporting"
          class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        >
          <svg v-if="!isImporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          <span v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Import Selected ({{ selectedCount }}) to Exam
        </button>
      </div>

      <!-- Preview Table -->
      <div class="overflow-x-auto max-h-[520px] overflow-y-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 bg-white sticky top-0 z-10 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4 w-12 text-center">#</th>
              <th class="py-3 px-4 w-32">Status</th>
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
                q.status === 'error'
                  ? 'bg-rose-50/25 border-l-3 border-l-rose-500'
                  : (selectedRowIds.includes(q.id) ? 'bg-indigo-50/15' : 'hover:bg-slate-50/60'),
                'transition-colors'
              ]"
            >
              <!-- Checkbox (Only valid questions can be checked) -->
              <td class="py-3 px-4 text-center">
                <input
                  v-if="q.status === 'valid'"
                  type="checkbox"
                  :value="q.id"
                  :checked="selectedRowIds.includes(q.id)"
                  @change="toggleRow(q.id, true)"
                  class="w-4 h-4 rounded border-slate-300 text-[#4F35F3] focus:ring-[#4F35F3] cursor-pointer"
                />
                <span
                  v-else
                  class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-rose-100 text-rose-600 font-bold text-xs"
                  title="Invalid questions cannot be selected or imported"
                >
                  ✕
                </span>
              </td>

              <!-- Status -->
              <td class="py-3 px-4 align-top">
                <span
                  v-if="q.status === 'valid'"
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full"
                >
                  <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  Valid
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full"
                  :title="q.statusMessage"
                >
                  Invalid
                </span>

                <!-- Specific Error Reasons -->
                <div v-if="q.errors && q.errors.length > 0" class="mt-1.5 space-y-1">
                  <div
                    v-for="(err, eIdx) in q.errors"
                    :key="eIdx"
                    class="text-[10.5px] font-medium text-rose-600 flex items-start gap-1 leading-tight"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0 mt-1"></span>
                    <span>{{ err }}</span>
                  </div>
                </div>
              </td>

              <!-- Type -->
              <td class="py-3 px-4 align-top">
                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 uppercase">
                  {{
                    q.type === 'multiple_choice' ? 'MCQ' :
                    q.type === 'true_false' ? 'True/False' :
                    q.type === 'fill_blank' ? 'Fill Blank' :
                    q.type === 'short_answer' ? 'Short Answer' :
                    q.type === 'matching' ? 'Matching' : q.type
                  }}
                </span>
                <p class="text-[10px] text-slate-400 mt-1 font-medium">{{ q.difficulty || 'Medium' }}</p>
              </td>

              <!-- Question Text & Options -->
              <td class="py-3 px-4 max-w-md align-top">
                <p class="font-medium text-slate-800 leading-snug">
                  {{ q.text || '(Empty Question Text)' }}
                </p>
                <p v-if="q.instruction" class="text-[11px] text-slate-400 mt-0.5 italic">
                  {{ q.instruction }}
                </p>

                <!-- Multiple Choice Options -->
                <div v-if="q.options && q.options.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                  <span
                    v-for="(opt, oIdx) in q.options"
                    :key="oIdx"
                    :class="[
                      (String.fromCharCode(65 + oIdx) === q.correct_answer || (q.type === 'true_false' && opt === q.correct_answer))
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold'
                        : 'bg-slate-50 text-slate-600 border-slate-200',
                      'text-[10.5px] px-2 py-0.5 rounded-md border flex items-center gap-1'
                    ]"
                  >
                    <span class="font-bold text-slate-400">{{ String.fromCharCode(65 + oIdx) }}:</span>
                    <span>{{ opt }}</span>
                  </span>
                </div>
              </td>

              <!-- Answer -->
              <td class="py-3 px-4 align-top">
                <span
                  v-if="q.correct_answer"
                  class="font-bold text-[11.5px] text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md inline-block max-w-[120px] truncate"
                  :title="q.correct_answer"
                >
                  {{ q.correct_answer }}
                </span>
                <span v-else class="text-[11px] font-semibold text-rose-500 italic">
                  Not Provided
                </span>
              </td>

              <!-- Marks -->
              <td class="py-3 px-4 text-center font-bold text-slate-700 align-top">
                <span :class="q.marks > 0 ? 'text-slate-800' : 'text-rose-500'">
                  {{ q.marks > 0 ? q.marks : '0' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

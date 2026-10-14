import * as XLSX from 'xlsx'

export interface ParsedQuestionRow {
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
  status: 'valid' | 'error'
  statusMessage?: string
  errors: string[]
  // Matching specific
  column_a?: string[]
  column_b?: { label: string; text: string }[]
  correct_answers?: Record<string, string>
  pairs?: { left: string; right: string }[]
  marks_per_item?: number
}

export interface FileValidationResult {
  success: boolean
  message?: string
  fileName: string
  fileSize: string
  totalRows: number
  validCount: number
  invalidCount: number
  skippedRows: number
  questions: ParsedQuestionRow[]
  detectedHeaders?: string[]
}

export const SUPPORTED_EXTENSIONS = ['.xlsx', '.xls', '.csv', '.json']
export const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024 // 10 MB

/**
 * Format bytes to readable string (e.g., "6.63 KB")
 */
export const formatBytes = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

/**
 * Normalize raw question type into one of the 5 system types.
 * Returns null if the type is unrecognized or unsupported.
 */
export const normalizeQuestionType = (raw: string): string | null => {
  if (!raw) return null
  const s = raw.toLowerCase().replace(/[\s_-]/g, '').trim()

  if (s === 'multiplechoice' || s === 'mcq' || s === 'choice') {
    return 'multiple_choice'
  }
  if (s === 'truefalse' || s === 'tf' || s === 'trueorfalse') {
    return 'true_false'
  }
  if (s === 'fillblank' || s === 'fillintheblank' || s === 'fib' || s === 'blank') {
    return 'fill_blank'
  }
  if (s === 'shortanswer' || s === 'sa' || s === 'essay' || s === 'short') {
    return 'short_answer'
  }
  if (s === 'matching' || s === 'match') {
    return 'matching'
  }

  return null
}

/**
 * Check if a file's binary content indicates a PDF masquerading as Excel/CSV.
 */
export const isPdfHeader = (buffer: ArrayBuffer): boolean => {
  if (buffer.byteLength < 5) return false
  const bytes = new Uint8Array(buffer.slice(0, 5))
  // %PDF-
  return bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46 && bytes[4] === 0x2d
}

/**
 * Validate a question against the application schema.
 */
export const validateQuestionData = (
  raw: {
    type?: string
    text?: string
    instruction?: string
    difficulty?: string
    marks?: any
    options?: string[]
    correct_answer?: string
    description?: string
    column_a?: string[]
    column_b?: any[]
    correct_answers?: any
    pairs?: any[]
  },
  index: number
): ParsedQuestionRow => {
  const errors: string[] = []

  const rawText = String(raw.text || '').trim()
  const strippedText = rawText.replace(/<[^>]*>?/gm, '').trim()

  const rawType = String(raw.type || '').trim()
  const normalizedType = normalizeQuestionType(rawType)

  // 1. Validate Question Text
  if (normalizedType !== 'matching' && !strippedText) {
    errors.push('Missing question text')
  }

  // 2. Validate Question Type
  if (!rawType) {
    errors.push('Missing question type')
  } else if (!normalizedType) {
    errors.push(`Unsupported question type "${rawType}". Allowed types: multiple_choice, true_false, fill_blank, short_answer, matching`)
  }

  // 3. Validate Marks
  const marksNum = Number(raw.marks)
  if (raw.marks === undefined || raw.marks === null || raw.marks === '' || isNaN(marksNum) || marksNum <= 0) {
    errors.push('Marks must be a positive number greater than 0')
  }

  // 4. Validate Specific Question Types
  let cleanOptions: string[] = []
  let resolvedAnswer = String(raw.correct_answer || '').trim()

  if (normalizedType === 'multiple_choice') {
    cleanOptions = (raw.options || []).map(o => String(o || '').trim()).filter(Boolean)

    if (cleanOptions.length < 2) {
      errors.push('Multiple Choice questions require at least 2 non-empty options (Option A and Option B)')
    }

    if (!resolvedAnswer) {
      errors.push('Correct answer is required for Multiple Choice questions')
    } else {
      const upperAns = resolvedAnswer.toUpperCase()
      let matchedLabel: string | null = null

      // Check letter match: A, B, C, D...
      if (upperAns.length === 1 && upperAns >= 'A' && upperAns <= 'Z') {
        const targetIdx = upperAns.charCodeAt(0) - 65
        if (targetIdx >= 0 && targetIdx < cleanOptions.length) {
          matchedLabel = upperAns
        }
      }

      // Check exact text match against options
      if (!matchedLabel) {
        const foundIdx = cleanOptions.findIndex(o => o.toLowerCase() === resolvedAnswer.toLowerCase())
        if (foundIdx >= 0) {
          matchedLabel = String.fromCharCode(65 + foundIdx)
        }
      }

      if (!matchedLabel) {
        const availableLabels = cleanOptions.map((_, i) => String.fromCharCode(65 + i)).join(', ')
        errors.push(`Correct answer "${resolvedAnswer}" does not match any available option (${availableLabels || 'None'})`)
      } else {
        resolvedAnswer = matchedLabel
      }
    }
  } else if (normalizedType === 'true_false') {
    cleanOptions = ['True', 'False']

    if (!resolvedAnswer) {
      errors.push('Correct answer is required for True/False questions')
    } else {
      const lower = resolvedAnswer.toLowerCase()
      if (lower === 'true' || lower === 't' || lower === '1' || lower === 'a') {
        resolvedAnswer = 'True'
      } else if (lower === 'false' || lower === 'f' || lower === '0' || lower === 'b') {
        resolvedAnswer = 'False'
      } else {
        errors.push(`Correct answer must be "True" or "False" (received "${resolvedAnswer}")`)
      }
    }
  } else if (normalizedType === 'fill_blank') {
    if (!resolvedAnswer) {
      errors.push('Correct answer is required for Fill in the Blank questions')
    }
  } else if (normalizedType === 'short_answer') {
    if (!resolvedAnswer) {
      errors.push('Expected answer or rubric is required for Short Answer questions')
    }
  } else if (normalizedType === 'matching') {
    const colA = raw.column_a || []
    const colB = raw.column_b || []
    if (colA.length < 1 || colB.length < 1) {
      errors.push('Matching questions require at least one Column A prompt and one Column B matching item')
    }
    if (!resolvedAnswer && !raw.correct_answers) {
      errors.push('Correct answer mapping is required for Matching questions')
    }
  }

  const rawDiff = String(raw.difficulty || '').trim().toLowerCase()
  let difficulty = 'Medium'
  if (rawDiff === 'easy') difficulty = 'Easy'
  else if (rawDiff === 'hard') difficulty = 'Hard'

  const isValid = errors.length === 0
  const rowId = `import-q-${index}-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`

  return {
    id: rowId,
    rawIndex: index,
    type: normalizedType || rawType,
    instruction: String(raw.instruction || '').trim() || (normalizedType === 'multiple_choice' ? 'Choose the correct answer' : normalizedType === 'true_false' ? 'Select True or False' : 'Answer the question'),
    text: normalizedType === 'matching' && !strippedText ? 'Matching Question' : rawText,
    difficulty,
    marks: marksNum > 0 ? marksNum : 0,
    options: cleanOptions,
    correct_answer: resolvedAnswer,
    description: String(raw.description || '').trim(),
    status: isValid ? 'valid' : 'error',
    statusMessage: isValid ? 'Valid question ready for import' : errors.join('; '),
    errors,
    column_a: raw.column_a,
    column_b: raw.column_b,
    correct_answers: raw.correct_answers,
    pairs: raw.pairs
  }
}

/**
 * Validate headers from a spreadsheet.
 * Rejects arbitrary spreadsheets that lack question columns.
 */
export const validateSpreadsheetHeaders = (headers: string[]): { valid: boolean; error?: string } => {
  const cleanHeaders = headers.map(h => (h || '').toLowerCase().replace(/[^a-z0-9]/g, ''))

  const textAliases = ['questiontext', 'question', 'text', 'prompt']
  const secondaryAliases = ['type', 'questiontype', 'marks', 'mark', 'score', 'points', 'correctanswer', 'answer', 'key', 'optiona', 'choicea']

  const hasText = cleanHeaders.some(h => textAliases.includes(h))
  const hasSecondary = cleanHeaders.some(h => secondaryAliases.includes(h))

  if (!hasText || !hasSecondary) {
    const detected = headers.filter(h => h && h.trim()).join(', ')
    return {
      valid: false,
      error: `Invalid question file format: The uploaded file does not match the exam question format. Expected column headers such as "Question Text", "Type", "Option A", "Option B", "Correct Answer", and "Marks". Detected headers: [${detected || 'None'}].`
    }
  }

  return { valid: true }
}

/**
 * Parse and validate an Excel (.xlsx/.xls) or CSV (.csv) file.
 */
export const parseAndValidateSpreadsheet = async (file: File): Promise<FileValidationResult> => {
  const fileName = file.name
  const fileSize = formatBytes(file.size)

  if (file.size === 0) {
    return {
      success: false,
      message: 'The uploaded file is empty. Please upload a file containing exam questions.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  if (file.size > MAX_FILE_SIZE_BYTES) {
    return {
      success: false,
      message: 'File size exceeds the 10 MB limit. Please upload a smaller file.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const buffer = await file.arrayBuffer()

  // Verify binary integrity (e.g. check for renamed PDF)
  if (isPdfHeader(buffer)) {
    return {
      success: false,
      message: 'Invalid file format: A PDF document cannot be uploaded as an Excel spreadsheet.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  let wb: XLSX.WorkBook
  try {
    wb = XLSX.read(buffer, { type: 'array' })
  } catch (err: any) {
    return {
      success: false,
      message: `Failed to read spreadsheet: ${err.message || 'Corrupted or unreadable file format.'}`,
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const sheetName = wb.SheetNames[0]
  if (!sheetName) {
    return {
      success: false,
      message: 'Workbook contains no sheets or data.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const sheet = wb.Sheets[sheetName]
  const rawRows = XLSX.utils.sheet_to_json<any[]>(sheet, { header: 1, defval: '' })

  if (!rawRows || rawRows.length === 0) {
    return {
      success: false,
      message: 'The worksheet is empty.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  // Find first non-empty row as header
  let headerRowIndex = -1
  for (let i = 0; i < rawRows.length; i++) {
    const row = rawRows[i]
    if (Array.isArray(row) && row.some(cell => String(cell || '').trim() !== '')) {
      headerRowIndex = i
      break
    }
  }

  if (headerRowIndex === -1) {
    return {
      success: false,
      message: 'The uploaded file does not contain any readable rows.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const headerRow = rawRows[headerRowIndex].map(h => String(h || '').trim())
  const headerCheck = validateSpreadsheetHeaders(headerRow)
  if (!headerCheck.valid) {
    return {
      success: false,
      message: headerCheck.error,
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: [],
      detectedHeaders: headerRow
    }
  }

  // Column map based on normalized header aliases
  const cleanHeaders = headerRow.map(h => h.toLowerCase().replace(/[^a-z0-9]/g, ''))
  const colMap: Record<string, number> = {}

  cleanHeaders.forEach((ch, idx) => {
    if (['questiontext', 'question', 'text', 'prompt'].includes(ch)) colMap.text = idx
    else if (['type', 'questiontype'].includes(ch)) colMap.type = idx
    else if (['marks', 'mark', 'score', 'points'].includes(ch)) colMap.marks = idx
    else if (['correctanswer', 'answer', 'key'].includes(ch)) colMap.correct_answer = idx
    else if (['instruction', 'instructions', 'groupinstruction'].includes(ch)) colMap.instruction = idx
    else if (['difficulty', 'level'].includes(ch)) colMap.difficulty = idx
    else if (['explanation', 'description', 'rubric'].includes(ch)) colMap.description = idx
    else if (['optiona', 'choicea', 'a'].includes(ch)) colMap.option_a = idx
    else if (['optionb', 'choiceb', 'b'].includes(ch)) colMap.option_b = idx
    else if (['optionc', 'choicec', 'c'].includes(ch)) colMap.option_c = idx
    else if (['optiond', 'choiced', 'd'].includes(ch)) colMap.option_d = idx
    else if (['optione', 'choicee', 'e'].includes(ch)) colMap.option_e = idx
    else if (['options', 'choices'].includes(ch)) colMap.options_combined = idx
  })

  const questions: ParsedQuestionRow[] = []
  let skippedRows = headerRowIndex // any empty rows before header
  let validCount = 0
  let invalidCount = 0

  for (let i = headerRowIndex + 1; i < rawRows.length; i++) {
    const row = rawRows[i]
    if (!Array.isArray(row) || !row.some(cell => String(cell || '').trim() !== '')) {
      skippedRows++
      continue
    }

    const getVal = (colKey: string): string => {
      const idx = colMap[colKey]
      if (idx !== undefined && row[idx] !== undefined && row[idx] !== null) {
        return String(row[idx]).trim()
      }
      return ''
    }

    let options: string[] = []
    const optA = getVal('option_a')
    const optB = getVal('option_b')
    const optC = getVal('option_c')
    const optD = getVal('option_d')
    const optE = getVal('option_e')

    if (optA || optB || optC || optD || optE) {
      options = [optA, optB, optC, optD, optE].filter(o => o !== '')
    } else if (getVal('options_combined')) {
      options = getVal('options_combined').split(/[|,;]/).map(s => s.trim()).filter(Boolean)
    }

    const questionRow = validateQuestionData(
      {
        type: getVal('type'),
        text: getVal('text'),
        instruction: getVal('instruction'),
        difficulty: getVal('difficulty'),
        marks: getVal('marks'),
        options,
        correct_answer: getVal('correct_answer'),
        description: getVal('description')
      },
      questions.length + 1
    )

    if (questionRow.status === 'valid') {
      validCount++
    } else {
      invalidCount++
    }
    questions.push(questionRow)
  }

  if (questions.length === 0) {
    return {
      success: false,
      message: 'The uploaded file does not contain any valid question rows beneath the headers.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows,
      questions: []
    }
  }

  return {
    success: true,
    fileName,
    fileSize,
    totalRows: questions.length,
    validCount,
    invalidCount,
    skippedRows,
    questions,
    detectedHeaders: headerRow
  }
}

/**
 * Parse and validate a JSON file.
 */
export const parseAndValidateJson = async (file: File): Promise<FileValidationResult> => {
  const fileName = file.name
  const fileSize = formatBytes(file.size)

  if (file.size === 0) {
    return {
      success: false,
      message: 'The uploaded JSON file is empty.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  if (file.size > MAX_FILE_SIZE_BYTES) {
    return {
      success: false,
      message: 'File size exceeds the 10 MB limit.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  let text: string
  try {
    text = await file.text()
  } catch (err: any) {
    return {
      success: false,
      message: `Failed to read file: ${err.message}`,
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  let data: any
  try {
    data = JSON.parse(text)
  } catch (err: any) {
    return {
      success: false,
      message: `Malformed JSON file: ${err.message}. Please upload valid JSON syntax.`,
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const rawQuestions = Array.isArray(data) ? data : data.questions || []
  if (!Array.isArray(rawQuestions) || rawQuestions.length === 0) {
    return {
      success: false,
      message: 'No question objects found in JSON file. Expected an array of questions or a { "questions": [...] } structure.',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  // Schema check: verify that items look like questions
  const hasQuestionKeys = rawQuestions.some(item => {
    if (!item || typeof item !== 'object') return false
    return ['text', 'question', 'question_text', 'type', 'prompt'].some(k => k in item)
  })

  if (!hasQuestionKeys) {
    return {
      success: false,
      message: 'Invalid question format: Objects in JSON file do not contain expected question properties (such as "text", "type", "marks", "correct_answer").',
      fileName,
      fileSize,
      totalRows: 0,
      validCount: 0,
      invalidCount: 0,
      skippedRows: 0,
      questions: []
    }
  }

  const questions: ParsedQuestionRow[] = []
  let validCount = 0
  let invalidCount = 0

  rawQuestions.forEach((item, idx) => {
    const qRow = validateQuestionData(item, idx + 1)
    if (qRow.status === 'valid') {
      validCount++
    } else {
      invalidCount++
    }
    questions.push(qRow)
  })

  return {
    success: true,
    fileName,
    fileSize,
    totalRows: questions.length,
    validCount,
    invalidCount,
    skippedRows: 0,
    questions
  }
}

/**
 * Download sample template for Excel (.xlsx), CSV (.csv), or JSON (.json).
 */
export const downloadQuestionTemplate = (format: 'xlsx' | 'csv' | 'json') => {
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
      'What does HTTP stand for in computer networking?',
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
      'Which layer of the OSI model does the Internet Protocol (IP) operate at?',
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
      'Explain the difference between a network switch and a router.',
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

  if (format === 'json') {
    const jsonSample = {
      questions: [
        {
          type: 'multiple_choice',
          text: 'What does HTTP stand for in computer networking?',
          instruction: 'Choose the correct answer',
          difficulty: 'Easy',
          marks: 5,
          options: [
            'HyperText Transfer Protocol',
            'High Transfer Text Protocol',
            'HyperText Test Program',
            'Hyperlink Telecommunication Protocol'
          ],
          correct_answer: 'A',
          description: 'HTTP stands for HyperText Transfer Protocol.'
        },
        {
          type: 'true_false',
          text: 'TCP is a connection-oriented protocol while UDP is connectionless.',
          instruction: 'Select True or False',
          difficulty: 'Easy',
          marks: 3,
          options: ['True', 'False'],
          correct_answer: 'True',
          description: 'TCP provides reliable, ordered connection-oriented byte streams.'
        },
        {
          type: 'fill_blank',
          text: 'The default port for secure web traffic using HTTPS is _____',
          instruction: 'Fill in the blank with the correct value',
          difficulty: 'Medium',
          marks: 3,
          correct_answer: '443',
          description: 'HTTPS operates on port 443 by default.'
        },
        {
          type: 'short_answer',
          text: 'Explain the difference between a network switch and a router.',
          instruction: 'Provide a clear and concise answer',
          difficulty: 'Hard',
          marks: 10,
          correct_answer: 'A switch operates at Layer 2 to connect devices within the same LAN using MAC addresses, while a router operates at Layer 3 to route packets between different networks using IP addresses.',
          description: 'Award full points for mentioning Layer 2 vs Layer 3 and LAN vs inter-network routing.'
        }
      ]
    }

    const blob = new Blob([JSON.stringify(jsonSample, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'exam_questions_template.json'
    a.click()
    URL.revokeObjectURL(url)
    return
  }

  const wsData = [sampleHeaders, ...sampleRows]
  const ws = XLSX.utils.aoa_to_sheet(wsData)

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

  // Instructions Sheet for Excel
  if (format === 'xlsx') {
    const guideHeaders = ['Field', 'Required', 'Allowed Values / Format', 'Description']
    const guideRows = [
      ['Type', 'Yes', 'multiple_choice, true_false, fill_blank, short_answer, matching', 'The question format category'],
      ['Question Text', 'Yes', 'Any text string', 'The main question prompt or problem statement'],
      ['Instruction', 'No', 'Any text string', 'Guidance to student (e.g. Choose the correct answer)'],
      ['Difficulty', 'No', 'Easy, Medium, Hard', 'Complexity level (defaults to Medium)'],
      ['Marks', 'Yes', 'Positive number > 0', 'Exam points awarded for correct answer'],
      ['Option A - Option D', 'Yes (for MCQ)', 'Text strings', 'At least Option A and Option B are required for Multiple Choice'],
      ['Correct Answer', 'Yes', 'A/B/C/D (MCQ), True/False (T/F), Answer string (Fill/Short)', 'The correct solution matching an option or expected key'],
      ['Explanation', 'No', 'Any text string', 'Grading rubric or solution explanation']
    ]
    const guideWs = XLSX.utils.aoa_to_sheet([guideHeaders, ...guideRows])
    guideWs['!cols'] = [{ wch: 20 }, { wch: 12 }, { wch: 38 }, { wch: 45 }]
    XLSX.utils.book_append_sheet(wb, guideWs, 'Format Instructions')

    XLSX.writeFile(wb, 'exam_questions_template.xlsx')
  } else {
    XLSX.writeFile(wb, 'exam_questions_template.csv')
  }
}

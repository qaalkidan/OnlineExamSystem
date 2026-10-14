<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuestionValidationService
{
    public const MAX_FILE_SIZE_BYTES = 10485760; // 10 MB

    public const SUPPORTED_TYPES = [
        'multiple_choice',
        'true_false',
        'fill_blank',
        'short_answer',
        'matching',
    ];

    /**
     * Normalize raw question type string into standard system type.
     */
    public function normalizeType(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = strtolower(trim($raw));
        $s = str_replace(['_', '-', ' ', '/'], '', $s);

        if (in_array($s, ['multiplechoice', 'mcq', 'choice'])) {
            return 'multiple_choice';
        }
        if (in_array($s, ['truefalse', 'tf', 'trueorfalse'])) {
            return 'true_false';
        }
        if (in_array($s, ['fillblank', 'fillintheblank', 'fillinblank', 'fib', 'blank'])) {
            return 'fill_blank';
        }
        if (in_array($s, ['shortanswer', 'sa', 'essay', 'short'])) {
            return 'short_answer';
        }
        if (in_array($s, ['matching', 'match'])) {
            return 'matching';
        }

        return null;
    }

    /**
     * Validate an individual question data array.
     */
    public function validateQuestion(array $q, int $index = 1): array
    {
        $errors = [];

        // 1. Validate Question Text
        $rawText = $q['text'] ?? $q['question_text'] ?? $q['question'] ?? '';
        $text = is_string($rawText) ? trim($rawText) : '';
        $strippedText = trim(strip_tags($text));

        $rawType = $q['type'] ?? $q['question_type'] ?? '';
        $normalizedType = $this->normalizeType(is_string($rawType) ? $rawType : '');

        if ($normalizedType !== 'matching' && empty($strippedText)) {
            $errors[] = 'Question text is required.';
        }

        // 2. Validate Question Type
        if (empty($rawType)) {
            $errors[] = 'Question type is required.';
        } elseif ($normalizedType === null) {
            $allowed = implode(', ', self::SUPPORTED_TYPES);
            $errors[] = "Unsupported question type '{$rawType}'. Allowed types: {$allowed}.";
        }

        // 3. Validate Marks
        $rawMarks = $q['marks'] ?? $q['mark'] ?? $q['score'] ?? $q['points'] ?? null;
        if ($rawMarks === null || $rawMarks === '' || !is_numeric($rawMarks) || (float)$rawMarks <= 0) {
            $errors[] = 'Marks must be a valid positive number greater than 0.';
        }
        $marks = (float)($rawMarks ?? 0);

        // 4. Validate Specific Question Types
        $sanitizedOptions = [];
        $sanitizedCorrectAnswer = null;
        $sanitizedQuestionData = $q['question_data'] ?? null;

        if ($normalizedType === 'multiple_choice') {
            $rawOptions = $q['options'] ?? [];
            if (!is_array($rawOptions)) {
                $rawOptions = [];
            }

            // If options was passed as option_a, option_b, etc.
            if (empty($rawOptions)) {
                foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $letter) {
                    $val = $q["option_{$letter}"] ?? $q["option {$letter}"] ?? $q[$letter] ?? null;
                    if ($val !== null && trim((string)$val) !== '') {
                        $rawOptions[] = trim((string)$val);
                    }
                }
            }

            // Extract string values
            $cleanOptions = [];
            foreach ($rawOptions as $opt) {
                if (is_array($opt)) {
                    $cleanOptions[] = trim((string)($opt['text'] ?? ''));
                } elseif (is_string($opt) || is_numeric($opt)) {
                    $cleanOptions[] = trim((string)$opt);
                }
            }
            $cleanOptions = array_values(array_filter($cleanOptions, fn($o) => $o !== ''));

            if (count($cleanOptions) < 2) {
                $errors[] = 'Multiple Choice questions require at least 2 non-empty options (Option A and Option B).';
            }

            $sanitizedOptions = $cleanOptions;

            // Correct Answer Validation
            $rawAns = $q['correct_answer'] ?? $q['correctAnswer'] ?? $q['answer'] ?? '';
            $ansStr = is_string($rawAns) || is_numeric($rawAns) ? trim((string)$rawAns) : '';

            if ($ansStr === '') {
                $errors[] = 'Correct answer is required for Multiple Choice questions.';
            } else {
                $upperAns = strtoupper($ansStr);
                $matchedLetter = null;

                // Check letter format: A, B, C, D...
                if (strlen($upperAns) === 1 && ctype_alpha($upperAns)) {
                    $targetIdx = ord($upperAns) - ord('A');
                    if (isset($cleanOptions[$targetIdx])) {
                        $matchedLetter = $upperAns;
                    }
                }

                // If not matched by letter, try matching by exact text
                if ($matchedLetter === null) {
                    foreach ($cleanOptions as $i => $optText) {
                        if (strcasecmp($optText, $ansStr) === 0) {
                            $matchedLetter = chr(ord('A') + $i);
                            break;
                        }
                    }
                }

                if ($matchedLetter === null) {
                    $availableLabels = [];
                    foreach ($cleanOptions as $i => $optText) {
                        $availableLabels[] = chr(ord('A') + $i);
                    }
                    $labelsStr = implode(', ', $availableLabels);
                    $errors[] = "Correct answer '{$ansStr}' does not match any available option ({$labelsStr}).";
                } else {
                    $sanitizedCorrectAnswer = $matchedLetter;
                }
            }
        } elseif ($normalizedType === 'true_false') {
            $sanitizedOptions = ['True', 'False'];

            $rawAns = $q['correct_answer'] ?? $q['correctAnswer'] ?? $q['answer'] ?? '';
            $ansStr = is_string($rawAns) || is_numeric($rawAns) ? trim((string)$rawAns) : '';

            if ($ansStr === '') {
                $errors[] = 'Correct answer is required for True/False questions (must be True or False).';
            } else {
                $lowerAns = strtolower($ansStr);
                if (in_array($lowerAns, ['true', 't', '1', 'a'])) {
                    $sanitizedCorrectAnswer = 'A'; // 'True' / Option A
                } elseif (in_array($lowerAns, ['false', 'f', '0', 'b'])) {
                    $sanitizedCorrectAnswer = 'B'; // 'False' / Option B
                } else {
                    $errors[] = "Correct answer for True/False must be 'True' or 'False' (received '{$ansStr}').";
                }
            }
        } elseif ($normalizedType === 'fill_blank') {
            $rawAns = $q['correct_answer'] ?? $q['correctAnswer'] ?? $q['answer'] ?? '';
            $ansStr = is_string($rawAns) || is_numeric($rawAns) ? trim((string)$rawAns) : '';

            if ($ansStr === '') {
                $errors[] = 'Correct answer is required for Fill in the Blank questions.';
            } else {
                $sanitizedCorrectAnswer = $ansStr;
            }
        } elseif ($normalizedType === 'short_answer') {
            $rawAns = $q['correct_answer'] ?? $q['correctAnswer'] ?? $q['answer'] ?? '';
            $ansStr = is_string($rawAns) || is_numeric($rawAns) ? trim((string)$rawAns) : '';

            if ($ansStr === '') {
                $errors[] = 'Expected answer or grading rubric is required for Short Answer questions.';
            } else {
                $sanitizedCorrectAnswer = $ansStr;
            }
        } elseif ($normalizedType === 'matching') {
            $parsedMatching = $this->parseMatchingQuestionData($q);
            $columnA = $parsedMatching['column_a'];
            $columnB = $parsedMatching['column_b'];
            $pairs = $parsedMatching['pairs'];
            $correctAnswers = $parsedMatching['correct_answers'];
            $normalizedAnswer = $parsedMatching['correct_answer'];
            $marksPerItem = $parsedMatching['marks_per_item'];

            if (count($columnA) < 1 || count($columnB) < 1) {
                $errors[] = 'Matching questions require at least one Column A prompt and one Column B matching item.';
            }

            if (empty($normalizedAnswer) && empty($correctAnswers)) {
                $errors[] = 'Correct answer mapping is required for Matching questions.';
            }

            $sanitizedCorrectAnswer = $normalizedAnswer;

            $sanitizedQuestionData = [
                'column_a'        => $columnA,
                'column_b'        => $columnB,
                'correct_answers' => $correctAnswers,
                'pairs'           => $pairs,
                'marks_per_item'  => $marksPerItem,
            ];

            if (empty($strippedText)) {
                $text = 'Matching Question';
            }
        }

        // Sanitized Question Object
        $difficulty = trim((string)($q['difficulty'] ?? 'Medium'));
        if (!in_array(ucfirst(strtolower($difficulty)), ['Easy', 'Medium', 'Hard'])) {
            $difficulty = 'Medium';
        } else {
            $difficulty = ucfirst(strtolower($difficulty));
        }

        $sanitized = [
            'type'           => $normalizedType ?? $rawType,
            'instruction'    => trim((string)($q['instruction'] ?? '')) ?: ($normalizedType === 'matching' ? 'Match the items from Column A with Column B' : ''),
            'text'           => $text,
            'difficulty'     => $difficulty,
            'marks'          => $marks,
            'options'        => $sanitizedOptions,
            'correct_answer' => $sanitizedCorrectAnswer,
            'description'    => trim((string)($q['description'] ?? $q['explanation'] ?? '')),
            'question_data'  => $sanitizedQuestionData,
            'column_a'       => $sanitizedQuestionData['column_a'] ?? null,
            'column_b'       => $sanitizedQuestionData['column_b'] ?? null,
            'correct_answers'=> $sanitizedQuestionData['correct_answers'] ?? null,
            'pairs'          => $sanitizedQuestionData['pairs'] ?? null,
            'marks_per_item' => $sanitizedQuestionData['marks_per_item'] ?? null,
        ];

        return [
            'valid'          => empty($errors),
            'errors'         => $errors,
            'status'         => empty($errors) ? 'valid' : 'error',
            'status_message' => empty($errors) ? 'Valid question ready for import' : implode('; ', $errors),
            'index'          => $index,
            'question'       => $sanitized,
        ];
    }

    /**
     * Validate an array of question records.
     */
    public function validateQuestionsBatch(array $questions): array
    {
        $results = [];
        $validCount = 0;
        $invalidCount = 0;
        $allErrors = [];

        foreach ($questions as $idx => $q) {
            $res = $this->validateQuestion($q, $idx + 1);
            if ($res['valid']) {
                $validCount++;
            } else {
                $invalidCount++;
                $allErrors["questions.{$idx}"] = $res['errors'];
            }
            $results[] = $res;
        }

        return [
            'valid'         => $invalidCount === 0 && count($questions) > 0,
            'total'         => count($questions),
            'valid_count'   => $validCount,
            'invalid_count' => $invalidCount,
            'results'       => $results,
            'errors'        => $allErrors,
        ];
    }

    /**
     * Validate file type, size, schema, headers, and parse all question rows.
     */
    public function validateAndParseFile(UploadedFile $file): array
    {
        // 1. File size check
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            return [
                'success' => false,
                'message' => 'File size exceeds the maximum limit of 10 MB.',
                'errors'  => ['file' => ['File size exceeds the 10 MB limit.']]
            ];
        }

        if ($file->getSize() === 0) {
            return [
                'success' => false,
                'message' => 'The uploaded file is empty. Please upload a file containing questions.',
                'errors'  => ['file' => ['File is empty (0 bytes).']]
            ];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $supportedExtensions = ['xlsx', 'xls', 'csv', 'json', 'pdf'];

        if (!in_array($extension, $supportedExtensions)) {
            return [
                'success' => false,
                'message' => "Unsupported file format '.{$extension}'. Only Excel (.xlsx, .xls), CSV (.csv), JSON (.json), and PDF (.pdf) files are accepted.",
                'errors'  => ['file' => ["Unsupported file extension '.{$extension}'."]]
            ];
        }

        $filePath = $file->getRealPath();

        // 2. Validate file integrity / binary structure
        if (in_array($extension, ['xlsx', 'xls'])) {
            // Verify it is not a renamed PDF or arbitrary binary
            $handle = fopen($filePath, 'rb');
            $headerBytes = fread($handle, 8);
            fclose($handle);

            // PDF magic number check
            if (str_starts_with($headerBytes, '%PDF-')) {
                return [
                    'success' => false,
                    'message' => 'Invalid file format: A PDF document cannot be uploaded as an Excel spreadsheet.',
                    'errors'  => ['file' => ['The uploaded file is a PDF renamed with an Excel extension.']]
                ];
            }

            // Attempt to load workbook with PhpSpreadsheet
            try {
                $spreadsheet = IOFactory::load($filePath);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Invalid or corrupted Excel file. Could not read spreadsheet.',
                    'errors'  => ['file' => ['File is corrupted or not a valid Excel spreadsheet.']]
                ];
            }

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            return $this->parseSpreadsheetArray($rows, $file->getClientOriginalName());
        }

        if ($extension === 'csv') {
            // Verify it is text, not binary
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return [
                    'success' => false,
                    'message' => 'Could not read CSV file.',
                    'errors'  => ['file' => ['Unable to open CSV file for reading.']]
                ];
            }

            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);

            return $this->parseSpreadsheetArray($rows, $file->getClientOriginalName());
        }

        if ($extension === 'json') {
            $content = file_get_contents($filePath);
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return [
                    'success' => false,
                    'message' => 'Malformed JSON file: ' . json_last_error_msg(),
                    'errors'  => ['file' => ['Invalid JSON syntax.']]
                ];
            }

            $rawQuestions = isset($decoded['questions']) && is_array($decoded['questions'])
                ? $decoded['questions']
                : $decoded;

            if (!is_array($rawQuestions) || empty($rawQuestions)) {
                return [
                    'success' => false,
                    'message' => 'No question records found in JSON file.',
                    'errors'  => ['file' => ['JSON file must contain an array of question objects.']]
                ];
            }

            // Check if objects have at least one question property
            $hasQuestionProps = false;
            foreach ($rawQuestions as $item) {
                if (is_array($item)) {
                    foreach (['text', 'question', 'question_text', 'prompt', 'type'] as $k) {
                        if (isset($item[$k])) {
                            $hasQuestionProps = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$hasQuestionProps) {
                return [
                    'success' => false,
                    'message' => "Invalid question schema: The JSON structure does not match the exam question format.",
                    'errors'  => ['file' => ["Expected JSON properties such as 'text', 'type', 'marks', 'options', 'correct_answer'."]]
                ];
            }

            $batchResult = $this->validateQuestionsBatch($rawQuestions);

            return [
                'success'       => true,
                'file_name'     => $file->getClientOriginalName(),
                'total_rows'    => count($rawQuestions),
                'valid_count'   => $batchResult['valid_count'],
                'invalid_count' => $batchResult['invalid_count'],
                'skipped_rows'  => 0,
                'questions'     => $batchResult['results'],
            ];
        }

        if ($extension === 'pdf') {
            $handle = fopen($filePath, 'rb');
            $headerBytes = fread($handle, 8);
            fclose($handle);

            if (!str_starts_with($headerBytes, '%PDF-')) {
                return [
                    'success' => false,
                    'message' => 'Invalid file format: The uploaded file is not a valid PDF document.',
                    'errors'  => ['file' => ['File does not contain valid PDF headers.']]
                ];
            }

            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to read PDF document: ' . $e->getMessage(),
                    'errors'  => ['file' => ['Unable to extract text from PDF document.']]
                ];
            }

            return $this->parsePdfDocument($text, $file->getClientOriginalName());
        }

        return [
            'success' => false,
            'message' => 'Unsupported file format.',
            'errors'  => ['file' => ['Unsupported format.']]
        ];
    }

    /**
     * Parse raw 2D array of rows from Excel or CSV, validate headers, and validate each question.
     */
    protected function parseSpreadsheetArray(array $rows, string $fileName): array
    {
        // Filter out completely empty rows
        $nonEmptyRows = [];
        $skippedEmptyCount = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hasContent = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim((string)$cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $nonEmptyRows[] = $row;
            } else {
                $skippedEmptyCount++;
            }
        }

        if (empty($nonEmptyRows)) {
            return [
                'success' => false,
                'message' => 'The uploaded spreadsheet contains no data rows.',
                'errors'  => ['file' => ['The worksheet is empty.']]
            ];
        }

        // Header row
        $headerRow = array_shift($nonEmptyRows);
        $headers = array_map(function ($h) {
            return strtolower(trim((string)$h));
        }, $headerRow);

        // Normalize header strings by stripping special chars
        $cleanHeaders = array_map(function ($h) {
            return preg_replace('/[^a-z0-9]/', '', $h);
        }, $headers);

        // 3. Schema & Header Validation: Check for required Question headers
        $hasTextHeader = false;
        $hasTypeOrMarksHeader = false;

        $textAliases = ['questiontext', 'question', 'text', 'prompt'];
        $typeAliases = ['type', 'questiontype'];
        $marksAliases = ['marks', 'mark', 'score', 'points'];
        $answerAliases = ['correctanswer', 'answer', 'key'];

        foreach ($cleanHeaders as $ch) {
            if (in_array($ch, $textAliases)) {
                $hasTextHeader = true;
            }
            if (in_array($ch, $typeAliases) || in_array($ch, $marksAliases) || in_array($ch, $answerAliases)) {
                $hasTypeOrMarksHeader = true;
            }
        }

        if (!$hasTextHeader || !$hasTypeOrMarksHeader) {
            $detected = implode(', ', array_filter(array_map('trim', $headerRow)));
            return [
                'success' => false,
                'message' => "Invalid question file format: The uploaded file does not match the exam question schema. Expected headers like 'Question Text', 'Type', 'Option A', 'Correct Answer', and 'Marks'.",
                'errors'  => [
                    'file' => [
                        "Missing required question headers. Detected headers: [{$detected}]."
                    ]
                ]
            ];
        }

        // Map header index to column type
        $columnMap = [];
        foreach ($cleanHeaders as $idx => $ch) {
            if (in_array($ch, $textAliases)) {
                $columnMap['text'] = $idx;
            } elseif (in_array($ch, $typeAliases)) {
                $columnMap['type'] = $idx;
            } elseif (in_array($ch, $marksAliases)) {
                $columnMap['marks'] = $idx;
            } elseif (in_array($ch, $answerAliases)) {
                $columnMap['correct_answer'] = $idx;
            } elseif ($ch === 'instruction' || $ch === 'instructions' || $ch === 'groupinstruction' || $ch === 'instructorinstruction') {
                $columnMap['instruction'] = $idx;
            } elseif ($ch === 'difficulty' || $ch === 'level') {
                $columnMap['difficulty'] = $idx;
            } elseif ($ch === 'explanation' || $ch === 'description' || $ch === 'rubric') {
                $columnMap['explanation'] = $idx;
            } elseif ($ch === 'optiona' || $ch === 'choicea' || $ch === 'a') {
                $columnMap['option_a'] = $idx;
            } elseif ($ch === 'optionb' || $ch === 'choiceb' || $ch === 'b') {
                $columnMap['option_b'] = $idx;
            } elseif ($ch === 'optionc' || $ch === 'choicec' || $ch === 'c') {
                $columnMap['option_c'] = $idx;
            } elseif ($ch === 'optiond' || $ch === 'choiced' || $ch === 'd') {
                $columnMap['option_d'] = $idx;
            } elseif ($ch === 'optione' || $ch === 'choicee' || $ch === 'e') {
                $columnMap['option_e'] = $idx;
            } elseif ($ch === 'options' || $ch === 'choices') {
                $columnMap['options_combined'] = $idx;
            } elseif ($ch === 'matchingpairs' || $ch === 'matching' || $ch === 'pairs' || $ch === 'matchpairs') {
                $columnMap['matching_pairs'] = $idx;
            } elseif ($ch === 'columna' || $ch === 'leftcolumn' || $ch === 'prompts') {
                $columnMap['column_a'] = $idx;
            } elseif ($ch === 'columnb' || $ch === 'rightcolumn' || $ch === 'matches' || $ch === 'definitions') {
                $columnMap['column_b'] = $idx;
            }
        }

        $results = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($nonEmptyRows as $rowIdx => $row) {
            $getVal = function ($key) use ($columnMap, $row) {
                if (isset($columnMap[$key]) && isset($row[$columnMap[$key]])) {
                    return trim((string)$row[$columnMap[$key]]);
                }
                return '';
            };

            $options = [];
            foreach (['option_a', 'option_b', 'option_c', 'option_d', 'option_e'] as $optKey) {
                $val = $getVal($optKey);
                if ($val !== '') {
                    $options[] = $val;
                }
            }

            if (empty($options) && !empty($getVal('options_combined'))) {
                $options = array_map('trim', preg_split('/[|,;]/', $getVal('options_combined')));
                $options = array_values(array_filter($options, fn($o) => $o !== ''));
            }

            $questionData = [
                'type'           => $getVal('type'),
                'text'           => $getVal('text'),
                'instruction'    => $getVal('instruction'),
                'difficulty'     => $getVal('difficulty') ?: 'Medium',
                'marks'          => $getVal('marks'),
                'options'        => $options,
                'correct_answer' => $getVal('correct_answer'),
                'description'    => $getVal('explanation'),
                'matching_pairs' => $getVal('matching_pairs'),
                'column_a'       => $getVal('column_a'),
                'column_b'       => $getVal('column_b'),
            ];

            $res = $this->validateQuestion($questionData, $rowIdx + 1);
            if ($res['valid']) {
                $validCount++;
            } else {
                $invalidCount++;
            }
            $results[] = $res;
        }

        return [
            'success'       => true,
            'file_name'     => $fileName,
            'total_rows'    => count($nonEmptyRows),
            'valid_count'   => $validCount,
            'invalid_count' => $invalidCount,
            'skipped_rows'  => $skippedEmptyCount,
            'questions'     => $results,
        ];
    }

    /**
     * Parse and normalize matching question prompts, definitions, pairs, and answers.
     */
    public function parseMatchingQuestionData(array $raw): array
    {
        $ansMap = [];
        if (isset($raw['correct_answers']) && is_array($raw['correct_answers'])) {
            foreach ($raw['correct_answers'] as $k => $v) {
                $ansMap[trim((string)$k)] = strtoupper(trim((string)$v));
            }
        } elseif (!empty($raw['correct_answer'])) {
            $parts = preg_split('/[;,]/', (string)$raw['correct_answer']);
            foreach ($parts as $p) {
                if (preg_match('/^(?:\s*)(\d+|[A-Za-z])[\s:=—\-]+([A-Za-z0-9]+)/u', trim($p), $m)) {
                    $ansMap[$m[1]] = strtoupper($m[2]);
                }
            }
        }

        $columnA = [];
        $columnB = [];
        $pairs = [];

        // Check 1: Explicit column_a and column_b
        if (isset($raw['column_a']) && is_array($raw['column_a']) && !empty($raw['column_a'])) {
            foreach ($raw['column_a'] as $item) {
                $val = trim((string)$item);
                if ($val !== '') {
                    $columnA[] = $val;
                }
            }
        } elseif (!empty($raw['column_a']) && is_string($raw['column_a'])) {
            $parts = preg_split('/[;\n|]/', $raw['column_a']);
            foreach ($parts as $p) {
                $val = trim($p);
                if ($val !== '') {
                    $columnA[] = $val;
                }
            }
        }

        if (isset($raw['column_b']) && is_array($raw['column_b']) && !empty($raw['column_b'])) {
            foreach ($raw['column_b'] as $idx => $item) {
                $label = is_array($item) ? ($item['label'] ?? chr(ord('A') + $idx)) : chr(ord('A') + $idx);
                $text = is_array($item) ? ($item['text'] ?? '') : (string)$item;
                $text = trim($text);
                if ($text !== '') {
                    $columnB[] = ['label' => $label, 'text' => $text];
                }
            }
        } elseif (!empty($raw['column_b']) && is_string($raw['column_b'])) {
            $parts = preg_split('/[;\n|]/', $raw['column_b']);
            foreach ($parts as $idx => $p) {
                $val = trim($p);
                if ($val !== '') {
                    $columnB[] = ['label' => chr(ord('A') + $idx), 'text' => $val];
                }
            }
        }

        // Check 2: Explicit pairs
        if (empty($columnA) && isset($raw['pairs']) && is_array($raw['pairs']) && !empty($raw['pairs'])) {
            foreach ($raw['pairs'] as $idx => $p) {
                $left = trim((string)($p['left'] ?? ''));
                $right = trim((string)($p['right'] ?? ''));
                if ($left !== '' || $right !== '') {
                    $label = chr(ord('A') + $idx);
                    $columnA[] = $left;
                    $columnB[] = ['label' => $label, 'text' => $right];
                    $pairs[] = ['left' => $left, 'right' => $right];
                    if (!isset($ansMap[(string)($idx + 1)])) {
                        $ansMap[(string)($idx + 1)] = $label;
                    }
                }
            }
        }

        // Check 3: matching_pairs string
        $rawPairs = trim((string)($raw['matching_pairs'] ?? ''));
        if (empty($columnA) && $rawPairs !== '') {
            $structuredRegex = '/(?:^|;|\s+)(\d+)\s*:\s*([^->;]+)->/u';
            if (preg_match_all($structuredRegex, $rawPairs, $matches, PREG_OFFSET_CAPTURE)) {
                $promptMatches = [];
                $numMatches = count($matches[0]);
                for ($i = 0; $i < $numMatches; $i++) {
                    $fullMatchStr = $matches[0][$i][0];
                    $fullMatchOffset = $matches[0][$i][1];
                    $contentStart = $fullMatchOffset + strlen($fullMatchStr);
                    $promptMatches[] = [
                        'num'          => $matches[1][$i][0],
                        'text'         => trim($matches[2][$i][0]),
                        'matchStart'   => $fullMatchOffset,
                        'contentStart' => $contentStart,
                    ];
                }

                for ($i = 0; $i < count($promptMatches); $i++) {
                    $end = ($i + 1 < count($promptMatches)) ? $promptMatches[$i + 1]['matchStart'] : strlen($rawPairs);
                    $promptMatches[$i]['rhs'] = trim(substr($rawPairs, $promptMatches[$i]['contentStart'], $end - $promptMatches[$i]['contentStart']));
                }

                $letterDefs = [];
                foreach ($promptMatches as $p) {
                    $columnA[] = $p['text'];
                    if (preg_match_all('/([A-Za-z])\s*:\s*([^;]+)/u', $p['rhs'], $defMatches, PREG_SET_ORDER)) {
                        foreach ($defMatches as $dm) {
                            $letter = strtoupper(trim($dm[1]));
                            $text = trim($dm[2]);
                            if (isset($ansMap[$p['num']]) && $ansMap[$p['num']] === $letter) {
                                $letterDefs[$letter] = $text;
                            } elseif (!isset($letterDefs[$letter])) {
                                $letterDefs[$letter] = $text;
                            }
                        }
                    }
                }

                ksort($letterDefs);
                foreach ($letterDefs as $label => $text) {
                    $columnB[] = ['label' => $label, 'text' => $text];
                }

                foreach ($columnA as $idx => $prompt) {
                    $numStr = (string)($idx + 1);
                    $matchedLetter = $ansMap[$numStr] ?? chr(ord('A') + $idx);
                    $matchedText = $letterDefs[$matchedLetter] ?? '';
                    $pairs[] = ['left' => $prompt, 'right' => $matchedText];
                }
            } else {
                // Pattern: Left -> Right
                $items = array_values(array_filter(array_map('trim', preg_split('/[;\n]/', $rawPairs))));
                foreach ($items as $idx => $item) {
                    $arrowParts = preg_split('/->|=>|--/', $item);
                    if (count($arrowParts) >= 2) {
                        $left = trim(preg_replace('/^\d+[\s:.)-]+/', '', trim($arrowParts[0])));
                        $right = trim(preg_replace('/^[A-Za-z][\s:.)-]+/', '', trim($arrowParts[1])));
                        $label = chr(ord('A') + $idx);
                        $columnA[] = $left;
                        $columnB[] = ['label' => $label, 'text' => $right];
                        $pairs[] = ['left' => $left, 'right' => $right];
                        if (!isset($ansMap[(string)($idx + 1)])) {
                            $ansMap[(string)($idx + 1)] = $label;
                        }
                    }
                }
            }
        }

        // Check 4: If columnB still empty but options array exists
        if (empty($columnB) && !empty($raw['options']) && is_array($raw['options'])) {
            foreach ($raw['options'] as $idx => $opt) {
                $text = is_array($opt) ? ($opt['text'] ?? $opt['right'] ?? '') : (string)$opt;
                $text = trim($text);
                if ($text !== '') {
                    $columnB[] = ['label' => chr(ord('A') + $idx), 'text' => $text];
                }
            }
        }

        // Pairs fallback
        if (empty($pairs) && !empty($columnA)) {
            foreach ($columnA as $idx => $prompt) {
                $numStr = (string)($idx + 1);
                $matchedLetter = $ansMap[$numStr] ?? chr(ord('A') + $idx);
                $bText = '';
                foreach ($columnB as $bItem) {
                    if (strcasecmp($bItem['label'], $matchedLetter) === 0) {
                        $bText = $bItem['text'];
                        break;
                    }
                }
                if ($bText === '' && isset($columnB[$idx])) {
                    $bText = $columnB[$idx]['text'];
                }
                $pairs[] = ['left' => $prompt, 'right' => $bText];
            }
        }

        // Sort answers and normalize string
        uksort($ansMap, fn($a, $b) => (int)$a <=> (int)$b);
        $normalizedAnswerParts = [];
        foreach ($ansMap as $k => $v) {
            $normalizedAnswerParts[] = "{$k}-{$v}";
        }
        $normalizedAnswer = !empty($normalizedAnswerParts)
            ? implode(', ', $normalizedAnswerParts)
            : trim((string)($raw['correct_answer'] ?? ''));

        $marks = (float)($raw['marks'] ?? 0);
        $marksPerItem = count($columnA) > 0 ? round($marks / count($columnA), 2) : $marks;

        return [
            'column_a'        => $columnA,
            'column_b'        => $columnB,
            'pairs'           => $pairs,
            'correct_answers' => $ansMap,
            'correct_answer'  => $normalizedAnswer,
            'marks_per_item'  => $marksPerItem,
        ];
    }

    /**
     * Parse text extracted from an uploaded PDF file, extract question records, and validate them.
     */
    public function parsePdfDocument(string $text, string $fileName): array
    {
        // 1. Remove Page headers/footers
        $lines = explode("\n", $text);
        $filteredLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^Page\s+\d+(\s+of\s+\d+)?$/i', $trimmed)) {
                continue;
            }
            $filteredLines[] = $line;
        }
        $cleanText = implode("\n", $filteredLines);

        // 2. Identify question boundaries
        // Matches 'Question 1 — True / False (2 marks)' or 'Question 1: ...' or 'Q1. ...'
        $headerPattern = '/(?:^|\n)\s*(?:Question|Q\.?)\s*(\d+)\s*(?:[—–\-:]|\.|\))\s*([^\n(]+?)(?:\s*[\(\[](\d+(?:\.\d+)?)\s*(?:marks?|pts?|points?)[\)\]])?(?=\n|$)/iu';

        if (!preg_match_all($headerPattern, $cleanText, $matches, PREG_OFFSET_CAPTURE)) {
            return [
                'success' => false,
                'message' => 'Invalid question file format: No questions matching the exam question format were detected in the uploaded PDF. Expected question blocks such as "Question 1 — Multiple Choice (5 marks)" with Question, Options, and Correct Answer fields.',
                'errors'  => ['file' => ['No question blocks detected in the uploaded PDF file.']]
            ];
        }

        $rawQuestions = [];
        $totalQuestions = count($matches[0]);

        for ($i = 0; $i < $totalQuestions; $i++) {
            $qNum = $matches[1][$i][0];
            $rawType = trim($matches[2][$i][0]);
            $marks = isset($matches[3][$i][0]) && $matches[3][$i][0] !== '' ? (float)$matches[3][$i][0] : null;

            $contentStart = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $contentEnd = ($i + 1 < $totalQuestions) ? $matches[0][$i + 1][1] : strlen($cleanText);
            $block = trim(substr($cleanText, $contentStart, $contentEnd - $contentStart));

            // Marks fallback if not in header line
            if ($marks === null && preg_match('/(?:Marks?|Points?|Score)\s*:\s*(\d+(?:\.\d+)?)/i', $block, $mMark)) {
                $marks = (float)$mMark[1];
            }
            if ($marks === null || $marks <= 0) {
                $marks = 1.0;
            }

            // Difficulty
            $difficulty = 'Medium';
            if (preg_match('/Difficulty\s*:\s*([A-Za-z]+)/i', $block, $mDiff)) {
                $difficulty = ucfirst(strtolower(trim($mDiff[1])));
            }

            // Instruction
            $instruction = '';
            if (preg_match('/(?:Instructor\s+instruction|Instructions?)\s*:\s*(.*?)(?=\n\s*(?:Difficulty:|Chapter:|Topic:|\Z))/is', $block, $mInst)) {
                $instruction = trim(preg_replace('/\s+/', ' ', $mInst[1]));
            }

            // Correct Answer
            $correctAnswer = '';
            if (preg_match('/(?:Correct\s+answer(?:\s*\/\s*marking\s*guide)?|Expected\s+answer|Answer|Key)\s*:\s*(.*?)(?=\n\s*(?:Instructor\s+instruction|Instructions?|Difficulty:|Student\s+response)|\Z)/is', $block, $mAns)) {
                $correctAnswer = trim($mAns[1]);
            }

            // Question Text
            $qText = '';
            if (preg_match('/(?:Question|Prompt)\s*:\s*(.*?)(?=\n\s*(?:[A-D][\.\)]|\bColumn\s+A\b|Correct\s+answer|Answer:|Instructor\s+instruction|Instruction:|Difficulty:)|\Z)/is', $block, $mText)) {
                $qText = trim(preg_replace('/\s+/', ' ', $mText[1]));
            } else {
                $firstLine = strtok($block, "\n");
                $qText = trim($firstLine ?: 'Question ' . $qNum);
            }

            // Options (for Multiple Choice)
            $options = [];
            $lowerType = strtolower($rawType);
            $isMcq = str_contains($lowerType, 'choice') || str_contains($lowerType, 'mcq');
            $isMatching = str_contains($lowerType, 'match') || str_contains($block, 'Column A');

            if ($isMcq || preg_match('/(?:^|\n)\s*[A-D][\.\)]\s+/', $block)) {
                if (preg_match('/(?:^|\n)\s*([A-D][\.\)]\s+.*?)(?=\n\s*(?:Correct\s+answer|Answer:|Instructor\s+instruction|Difficulty:)|\Z)/is', $block, $mOptBlock)) {
                    $optBlock = trim($mOptBlock[1]);
                    $parts = preg_split('/(?=\b[A-D][\.\)]\s*)/', $optBlock);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if (preg_match('/^[A-D][\.\)]\s*(.+)$/s', $p, $pm)) {
                            $options[] = trim(preg_replace('/\s+/', ' ', $pm[1]));
                        }
                    }
                }
            }

            // Matching (Column A & Column B)
            $columnA = [];
            $columnB = [];
            if ($isMatching) {
                $blockLines = explode("\n", $block);
                foreach ($blockLines as $bLine) {
                    $bLine = trim($bLine);
                    if (preg_match('/^(\d+)[\.\)]\s*(.*?)(?:\t+|\s{2,})([A-Za-z])[\.\)]\s*(.+)$/u', $bLine, $pairMatch)) {
                        $colAItem = trim($pairMatch[2]);
                        $colBLabel = strtoupper(trim($pairMatch[3]));
                        $colBText = trim($pairMatch[4]);

                        $columnA[] = $colAItem;
                        $columnB[] = ['label' => $colBLabel, 'text' => $colBText];
                    }
                }
            }

            $rawQuestions[] = [
                'type'           => $rawType,
                'text'           => $qText,
                'marks'          => $marks,
                'options'        => $options,
                'column_a'       => $columnA,
                'column_b'       => $columnB,
                'correct_answer' => $correctAnswer,
                'instruction'    => $instruction,
                'difficulty'     => $difficulty,
            ];
        }

        if (empty($rawQuestions)) {
            return [
                'success' => false,
                'message' => 'Invalid question file format: No readable questions were parsed from the PDF document.',
                'errors'  => ['file' => ['Failed to extract valid question blocks.']]
            ];
        }

        $batchResult = $this->validateQuestionsBatch($rawQuestions);

        return [
            'success'       => true,
            'file_name'     => $fileName,
            'total_rows'    => count($rawQuestions),
            'valid_count'   => $batchResult['valid_count'],
            'invalid_count' => $batchResult['invalid_count'],
            'skipped_rows'  => 0,
            'questions'     => $batchResult['results'],
        ];
    }

    /**
     * Generate a downloadable sample exam question template in PDF format using Dompdf.
     */
    public function generatePdfTemplate(): string
    {
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Exam Questions Template</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #1e293b; margin: 30px; }
        h1 { font-size: 18pt; color: #1e293b; margin-bottom: 5px; }
        .subtitle { font-size: 10pt; color: #64748b; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .q-block { margin-bottom: 22px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1; }
        .q-header { font-weight: bold; font-size: 12pt; color: #0f172a; margin-bottom: 6px; }
        .q-field { margin-bottom: 4px; }
        .q-label { font-weight: bold; color: #475569; }
        .q-ans { font-weight: bold; color: #047857; }
        .meta { color: #64748b; font-size: 9.5pt; margin-top: 4px; }
        table.matching { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10.5pt; }
        table.matching th, table.matching td { border: 1px solid #cbd5e1; padding: 6px 10px; text-align: left; }
        table.matching th { background-color: #f1f5f9; font-weight: bold; color: #334155; }
    </style>
</head>
<body>
    <h1>Wollo University - Exam Question PDF Template</h1>
    <div class="subtitle">Standardized format for bulk importing exam questions. Follow this structure for True/False, Multiple Choice, Matching, Fill in the Blank, and Short Answer questions.</div>

    <div class="q-block">
        <div class="q-header">Question 1 — True / False (2 marks)</div>
        <div class="q-field"><span class="q-label">Question:</span> In a relational database, a primary key uniquely identifies each record in a table.</div>
        <div class="q-field"><span class="q-label">Correct answer:</span> <span class="q-ans">True</span></div>
        <div class="q-field"><span class="q-label">Instructor instruction:</span> Mark True if the statement is correct; otherwise mark False.</div>
        <div class="meta"><span class="q-label">Difficulty:</span> Easy | <span class="q-label">Chapter:</span> Chapter 1 - Database Fundamentals</div>
    </div>

    <div class="q-block">
        <div class="q-header">Question 2 — Matching (4 marks)</div>
        <div class="q-field"><span class="q-label">Question:</span> Match each database term in Column A with its correct definition in Column B.</div>
        <table class="matching">
            <thead>
                <tr>
                    <th style="width: 45%;">Column A — Terms</th>
                    <th style="width: 55%;">Column B — Options</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1. Primary Key</td><td>A. A command used to sort query results</td></tr>
                <tr><td>2. Foreign Key</td><td>B. A field that uniquely identifies a record</td></tr>
                <tr><td>3. Query</td><td>C. A request to retrieve or manipulate data</td></tr>
                <tr><td>4. Sort</td><td>D. A field that references a key in another table</td></tr>
            </tbody>
        </table>
        <div class="q-field"><span class="q-label">Correct answer:</span> <span class="q-ans">1-B; 2-D; 3-C; 4-A</span></div>
        <div class="q-field"><span class="q-label">Instructor instruction:</span> Match every term in Column A with one definition in Column B.</div>
        <div class="meta"><span class="q-label">Difficulty:</span> Medium</div>
    </div>

    <div class="q-block">
        <div class="q-header">Question 3 — Multiple Choice (3 marks)</div>
        <div class="q-field"><span class="q-label">Question:</span> Which SQL clause is used to filter rows before grouping results?</div>
        <div class="q-field">A. ORDER BY &nbsp;&nbsp;&nbsp; B. WHERE &nbsp;&nbsp;&nbsp; C. SELECT &nbsp;&nbsp;&nbsp; D. INSERT</div>
        <div class="q-field"><span class="q-label">Correct answer:</span> <span class="q-ans">B</span></div>
        <div class="q-field"><span class="q-label">Instructor instruction:</span> Choose one correct option (A, B, C, or D).</div>
        <div class="meta"><span class="q-label">Difficulty:</span> Easy | <span class="q-label">Chapter:</span> Chapter 2 - SQL Queries</div>
    </div>

    <div class="q-block">
        <div class="q-header">Question 4 — Fill in the Blank (2 marks)</div>
        <div class="q-field"><span class="q-label">Question:</span> In HTTP, the status code commonly used for a successful request is ____.</div>
        <div class="q-field"><span class="q-label">Correct answer:</span> <span class="q-ans">200</span></div>
        <div class="q-field"><span class="q-label">Instructor instruction:</span> Enter the missing number or term in the blank.</div>
        <div class="meta"><span class="q-label">Difficulty:</span> Easy</div>
    </div>

    <div class="q-block">
        <div class="q-header">Question 5 — Short Answer (4 marks)</div>
        <div class="q-field"><span class="q-label">Question:</span> Explain the primary function of an operating system kernel.</div>
        <div class="q-field"><span class="q-label">Correct answer:</span> <span class="q-ans">The kernel acts as the core interface between computer hardware and user applications, managing CPU scheduling, memory allocation, and peripheral access.</span></div>
        <div class="q-field"><span class="q-label">Instructor instruction:</span> Provide a concise explanation covering resource management and hardware abstraction.</div>
        <div class="meta"><span class="q-label">Difficulty:</span> Hard</div>
    </div>
</body>
</html>
HTML;

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string)$dompdf->output();
    }
}

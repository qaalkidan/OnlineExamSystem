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
        $s = str_replace(['_', '-', ' '], '', $s);

        if (in_array($s, ['multiplechoice', 'mcq', 'choice'])) {
            return 'multiple_choice';
        }
        if (in_array($s, ['truefalse', 'tf', 'trueorfalse'])) {
            return 'true_false';
        }
        if (in_array($s, ['fillblank', 'fillintheblank', 'fib', 'blank'])) {
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
        $supportedExtensions = ['xlsx', 'xls', 'csv', 'json'];

        if (!in_array($extension, $supportedExtensions)) {
            return [
                'success' => false,
                'message' => "Unsupported file format '.{$extension}'. Only Excel (.xlsx, .xls), CSV (.csv), and JSON (.json) files are accepted.",
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
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class DepartmentExamExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected Collection $exams;
    protected int $index = 0;

    public function __construct(Collection $exams)
    {
        $this->exams = $exams;
    }

    public function collection()
    {
        return $this->exams;
    }

    public function headings(): array
    {
        return [
            '#',
            'Exam Code',
            'Exam Title',
            'Course Code',
            'Course Title',
            'Section',
            'Academic Year',
            'Semester',
            'Exam Type',
            'Scheduled Date',
            'Start Time',
            'Duration',
            'Total Marks',
            'Questions',
            'Assigned Instructor',
            'Student Submissions',
            'Status',
            'Created Date',
        ];
    }

    public function map($exam): array
    {
        $this->index++;

        $code = $exam->code ?? ('EXM-' . ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT));
        $courseCode = $exam->course_code ?? ($exam->course->code ?? 'N/A');
        $courseName = $exam->course_name ?? ($exam->course->title ?? 'General Course');
        $section = $exam->section ?: ($exam->settings['section'] ?? 'All Sections');
        $academicYear = $exam->settings['academic_year'] ?? ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y'));
        $semester = $exam->settings['semester'] ?? ($exam->course->semester ?? 'N/A');
        $type = $exam->type ?? 'Examination';
        $date = $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'Unscheduled';
        $time = $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'Flexible';
        $duration = ($exam->duration_minutes ?? 60) . ' mins';
        $marks = $exam->total_marks ?? 100;
        $questions = $exam->questions_count ?? ($exam->questions ? $exam->questions->count() : 0);
        $instructorName = $exam->instructor ? $exam->instructor->name : 'Unassigned';
        $attempts = $exam->attempts_count ?? ($exam->attempts ? $exam->attempts->count() : 0);
        $status = ucfirst($exam->status ?? 'Draft');
        $created = $exam->created_at ? $exam->created_at->format('M d, Y') : '';

        return [
            $this->index,
            $code,
            $exam->title ?? '',
            $courseCode,
            $courseName,
            $section,
            $academicYear,
            $semester,
            $type,
            $date,
            $time,
            $duration,
            $marks,
            $questions,
            $instructorName,
            $attempts,
            $status,
            $created,
        ];
    }
}

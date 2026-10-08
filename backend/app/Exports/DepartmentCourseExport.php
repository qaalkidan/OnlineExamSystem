<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class DepartmentCourseExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected Collection $courses;
    protected int $index = 0;

    public function __construct(Collection $courses)
    {
        $this->courses = $courses;
    }

    public function collection()
    {
        return $this->courses;
    }

    public function headings(): array
    {
        return [
            '#',
            'Course Code',
            'Course Title',
            'Credit Hours',
            'Year Level',
            'Semester',
            'Section',
            'Department',
            'Primary Instructor',
            'Co-Instructor',
            'Exams Count',
            'Enrolled Students',
            'Status',
            'Created By',
            'Created At',
        ];
    }

    public function map($course): array
    {
        $this->index++;

        $instructorName = $course->instructor ? $course->instructor->name : 'Unassigned';
        $coInstructorName = $course->coInstructor ? $course->coInstructor->name : '—';
        $deptName = $course->department ? $course->department->name : 'Department';
        $creatorName = $course->creator ? $course->creator->name : ($course->is_admin_created ? 'Super Admin' : 'Department Head');

        return [
            $this->index,
            $course->code ?? '',
            $course->title ?? '',
            $course->credits ?? 0,
            $course->level ?? '—',
            $course->semester ?? '—',
            $course->section ?? 'All Sections',
            $deptName,
            $instructorName,
            $coInstructorName,
            $course->exams_count ?? 0,
            $course->students_count ?? 0,
            ucfirst($course->status ?? 'active'),
            $creatorName,
            $course->created_at ? $course->created_at->format('M d, Y') : '',
        ];
    }
}

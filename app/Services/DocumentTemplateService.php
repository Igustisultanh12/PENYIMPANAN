<?php

namespace App\Services;

use App\Models\DocumentTemplate;

class DocumentTemplateService
{
    /**
     * Get available document templates categorized by document type.
     */
    public function getTemplates(?string $type = null): array
    {
        $query = DocumentTemplate::query();

        if ($type) {
            $query->where('type', $type);
        }

        $templates = $query->get();

        if ($templates->isEmpty()) {
            $this->seedDefaultTemplates();
            $templates = $query->get();
        }

        return $templates->toArray();
    }

    /**
     * Seed initial standard templates if table is empty.
     */
    public function seedDefaultTemplates(): void
    {
        $defaults = [
            // Word / Documents
            [
                'name' => 'Blank Document',
                'type' => 'document',
                'extension' => 'docx',
                'description' => 'Clean empty document ready for notes, reports, and memos.',
                'thumbnail_url' => '/icons/templates/blank-doc.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Project Proposal',
                'type' => 'document',
                'extension' => 'docx',
                'description' => 'Standard project pitch with executive summary, milestones, and budget.',
                'thumbnail_url' => '/icons/templates/proposal.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Meeting Notes & Agenda',
                'type' => 'document',
                'extension' => 'docx',
                'description' => 'Structured meeting minutes with attendee list and action items.',
                'thumbnail_url' => '/icons/templates/meeting.svg',
                'is_system' => true,
            ],

            // Excel / Spreadsheets
            [
                'name' => 'Blank Spreadsheet',
                'type' => 'spreadsheet',
                'extension' => 'xlsx',
                'description' => 'Empty grid sheet for data modeling and calculations.',
                'thumbnail_url' => '/icons/templates/blank-sheet.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Monthly Budget Tracker',
                'type' => 'spreadsheet',
                'extension' => 'xlsx',
                'description' => 'Track income, fixed expenses, and savings with formula summaries.',
                'thumbnail_url' => '/icons/templates/budget.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Project Task Tracker',
                'type' => 'spreadsheet',
                'extension' => 'xlsx',
                'description' => 'Manage deliverables, task assignees, priorities, and deadlines.',
                'thumbnail_url' => '/icons/templates/tracker.svg',
                'is_system' => true,
            ],

            // PowerPoint / Presentations
            [
                'name' => 'Blank Presentation',
                'type' => 'presentation',
                'extension' => 'pptx',
                'description' => 'Modern widescreen presentation slides layout.',
                'thumbnail_url' => '/icons/templates/blank-deck.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Business Pitch Deck',
                'type' => 'presentation',
                'extension' => 'pptx',
                'description' => 'Pitch deck with company overview, problem, solution, and growth metrics.',
                'thumbnail_url' => '/icons/templates/pitch.svg',
                'is_system' => true,
            ],
            [
                'name' => 'Quarterly Product Review',
                'type' => 'presentation',
                'extension' => 'pptx',
                'description' => 'Slide deck for sprint retrospectives and executive product updates.',
                'thumbnail_url' => '/icons/templates/review.svg',
                'is_system' => true,
            ],
        ];

        foreach ($defaults as $item) {
            DocumentTemplate::firstOrCreate(
                ['name' => $item['name'], 'type' => $item['type']],
                $item
            );
        }
    }
}

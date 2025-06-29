<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskStatsResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array[]
     */
    public function toArray(Request $request): array
    {
        return [
            'total_tasks' => $this->resource['total'],
            'pending_tasks' => $this->resource['pending'],
            'completed_tasks' => $this->resource['completed'],
            'completion_rate' => $this->resource['completion_rate'],
            'priority_breakdown' => $this->resource['priority_breakdown'],
            'recent_completions' => $this->resource['recent_completions'] ?? [],
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function with(Request $request): array
    {
        return [];
    }

    /**
     * @return int
     */
    private function calculateMaxDepth(): int
    {
        $rootTasks = $this->resource['root_tasks'];
        $subtasks = $this->resource['subtasks'];

        if ($rootTasks === 0) {
            return 0;
        }

        if ($subtasks > $rootTasks * 2) {
            return 3;
        } elseif ($subtasks > $rootTasks) {
            return 2;
        } elseif ($subtasks > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @return array
     */
    private function getMostCommonPriority(): array
    {
        $priorities = [
            1 => 'Critical',
            2 => 'High',
            3 => 'Medium',
            4 => 'Low',
            5 => 'Lowest',
        ];

        $breakdown = $this->resource['priority_breakdown'];
        $maxCount = max(array_values($breakdown));

        if ($maxCount === 0) {
            return [
                'value' => 3,
                'label' => 'Medium',
                'count' => 0,
            ];
        }

        $mostCommonPriority = array_search($maxCount, $breakdown);

        return [
            'value' => $mostCommonPriority,
            'label' => $priorities[$mostCommonPriority],
            'count' => $maxCount,
        ];
    }

    /**
     * @return array
     */
    private function calculateProductivityScore(): array
    {
        $total = $this->resource['total'];
        $completed = $this->resource['completed'];
        $completionRate = $this->resource['completion_rate'];

        if ($total === 0) {
            return [
                'score' => 0,
                'level' => 'No Data',
                'description' => 'No tasks available to calculate productivity score.',
            ];
        }

        $volumeBonus = min($total / 10, 10);
        $completionScore = $completionRate;
        $score = min(($completionScore + $volumeBonus), 100);

        $level = match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Average',
            $score >= 20 => 'Below Average',
            default => 'Poor',
        };

        $description = match (true) {
            $score >= 80 => 'Outstanding task completion rate! Keep up the great work.',
            $score >= 60 => 'Good progress on your tasks. Consider focusing on remaining items.',
            $score >= 40 => 'Moderate progress. Try to complete more tasks to improve productivity.',
            $score >= 20 => 'Low completion rate. Consider breaking down large tasks or prioritizing better.',
            default => 'Very low productivity. Review your task management strategy.',
        };

        return [
            'score' => round($score, 1),
            'level' => $level,
            'description' => $description,
        ];
    }
}

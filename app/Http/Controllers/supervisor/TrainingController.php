<?php

namespace App\Http\Controllers\supervisor;

use App\Models\Trainers;
use App\Models\Trainings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingController extends Controller
{

    /**
     * Main Training Dashboard (Calendar + Upcoming)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $calendarEvents = Trainings::
            whereMonth('start_date', now()->month)
            ->get()
            ->map(function ($training) {
                return [
                    'date'  => $training->start_date,
                    'title' => $training->name,
                    'type'  => $training->type,
                ];
            });

        $upcomingTrainings = Trainings::
            where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->take(5)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Training dashboard retrieved',
            'data' => [
                'calendar_events'    => $calendarEvents,
                'upcoming_trainings' => $this->formatTrainingList($upcomingTrainings),
            ]
        ]);
    }

    /**
     * Training Videos List
     */
    public function videos(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fetch materials where type is 'video'
        $videos = $user->employee->trainingMaterials()
            ->where('type', 'video')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Training videos retrieved',
            'data' => $this->formatMaterials($videos)
        ]);
    }

    /**
     * Training Manuals List
     */
    public function manuals(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fetch materials where type is 'manual' or 'document'
        $manuals = $user->employee->trainingMaterials()
            ->where('type', 'manual')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Training manuals retrieved',
            'data' => $this->formatMaterials($manuals)
        ]);
    }

// -----------------------------------------------------------------------------
// HELPER METHODS
// -----------------------------------------------------------------------------

    private function formatTrainingList($trainings)
    {
        return $trainings->map(function ($training) {
            return [
                'id'            => $training->id,
                'title'         => $training->name,        // CSO March Parade
                'image_url'     => $training->image_url,
                'date'          => $training->start_date,
                'location'      => $training->location,
                'training_type' => $training->type,
                'is_expanded'   => false,
            ];
        });
    }

    private function formatMaterials($materials)
    {
        return $materials->map(function ($material) {
            return [
                'id'           => $material->id,
                'title'        => $material->title,       // Man Guard Fundamentals
                'series_tag'   => $material->is_series ? 'Series' : null,
                'thumbnail'    => $material->thumbnail_url,
                'status'       => $material->pivot->status ?? 'Pending', // Completed/Pending
                'content_url'  => $material->file_url,    // Video link or PDF link
                'duration'     => $material->duration,    // e.g. "10 mins" (optional)
            ];
        });
    }
}

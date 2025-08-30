<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SurveyController extends Controller
{
    /**
     * Display a listing of surveys.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Survey::with(['creator:id,name,email', 'responses:id,survey_id,nps_score,sentiment'])
                          ->byUser($user->id);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('category') && $request->category !== 'all') {
                $query->byCategory($request->category);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $surveys = $query->paginate($perPage);

            // Transform data
            $surveys->getCollection()->transform(function ($survey) {
                return [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'description' => $survey->description,
                    'category' => $survey->category,
                    'status' => $survey->status,
                    'question_count' => $survey->question_count,
                    'estimated_time' => $survey->estimated_time,
                    'url' => $survey->url,
                    'share_url' => $survey->share_url,
                    'analytics' => $survey->analytics,
                    'creator' => $survey->creator,
                    'created_at' => $survey->created_at,
                    'updated_at' => $survey->updated_at,
                    'published_at' => $survey->published_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Surveys retrieved successfully',
                'data' => $surveys
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve surveys',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created survey.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'category' => 'required|string|max:100',
                'estimated_time' => 'nullable|string|max:50',
                'questions' => 'required|array|min:1',
                'questions.*.type' => 'required|string|in:nps,rating,multiple_choice,text,scale,slider,yes_no,matrix',
                'questions.*.question' => 'required|string',
                'questions.*.required' => 'boolean',
                'questions.*.options' => 'array',
                'settings' => 'nullable|array',
                'settings.collect_email' => 'boolean',
                'settings.anonymous_responses' => 'boolean',
                'settings.one_response_per_person' => 'boolean',
                'settings.show_progress_bar' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $survey = Survey::create([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'estimated_time' => $request->estimated_time ?? '5-10 minutes',
                'questions' => $request->questions,
                'settings' => $request->settings ?? [
                    'collect_email' => false,
                    'anonymous_responses' => true,
                    'one_response_per_person' => true,
                    'show_progress_bar' => true,
                ],
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $survey->load(['creator:id,name,email']);

            return response()->json([
                'success' => true,
                'message' => 'Survey created successfully',
                'data' => [
                    'survey' => [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'description' => $survey->description,
                        'category' => $survey->category,
                        'status' => $survey->status,
                        'question_count' => $survey->question_count,
                        'estimated_time' => $survey->estimated_time,
                        'questions' => $survey->questions,
                        'settings' => $survey->settings,
                        'url' => $survey->url,
                        'share_url' => $survey->share_url,
                        'analytics' => $survey->analytics,
                        'creator' => $survey->creator,
                        'created_at' => $survey->created_at,
                        'updated_at' => $survey->updated_at,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified survey.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $survey = Survey::with(['creator:id,name,email', 'responses'])
                          ->where('id', $id)
                          ->where('created_by', $user->id)
                          ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Survey retrieved successfully',
                'data' => [
                    'survey' => [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'description' => $survey->description,
                        'category' => $survey->category,
                        'status' => $survey->status,
                        'question_count' => $survey->question_count,
                        'estimated_time' => $survey->estimated_time,
                        'questions' => $survey->questions,
                        'settings' => $survey->settings,
                        'url' => $survey->url,
                        'share_url' => $survey->share_url,
                        'analytics' => $survey->analytics,
                        'creator' => $survey->creator,
                        'created_at' => $survey->created_at,
                        'updated_at' => $survey->updated_at,
                        'published_at' => $survey->published_at,
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified survey.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $survey = Survey::where('id', $id)
                          ->where('created_by', $user->id)
                          ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'category' => 'sometimes|required|string|max:100',
                'estimated_time' => 'nullable|string|max:50',
                'questions' => 'sometimes|required|array|min:1',
                'questions.*.type' => 'required|string|in:nps,rating,multiple_choice,text,scale,slider,yes_no,matrix',
                'questions.*.question' => 'required|string',
                'questions.*.required' => 'boolean',
                'questions.*.options' => 'array',
                'settings' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if survey can be updated (not published with responses)
            if ($survey->isPublished() && $survey->responses()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update published survey with existing responses'
                ], 422);
            }

            $updateData = $request->only(['title', 'description', 'category', 'estimated_time', 'questions', 'settings']);
            $survey->update($updateData);

            $survey->load(['creator:id,name,email']);

            return response()->json([
                'success' => true,
                'message' => 'Survey updated successfully',
                'data' => [
                    'survey' => [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'description' => $survey->description,
                        'category' => $survey->category,
                        'status' => $survey->status,
                        'question_count' => $survey->question_count,
                        'estimated_time' => $survey->estimated_time,
                        'questions' => $survey->questions,
                        'settings' => $survey->settings,
                        'url' => $survey->url,
                        'share_url' => $survey->share_url,
                        'analytics' => $survey->analytics,
                        'creator' => $survey->creator,
                        'created_at' => $survey->created_at,
                        'updated_at' => $survey->updated_at,
                        'published_at' => $survey->published_at,
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified survey.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $survey = Survey::where('id', $id)
                          ->where('created_by', $user->id)
                          ->firstOrFail();

            // Check if survey can be deleted
            if ($survey->isPublished() && $survey->responses()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete published survey with existing responses. Consider closing it instead.'
                ], 422);
            }

            $survey->delete();

            return response()->json([
                'success' => true,
                'message' => 'Survey deleted successfully'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a survey.
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $survey = Survey::where('id', $id)
                          ->where('created_by', $user->id)
                          ->firstOrFail();

            if ($survey->isPublished()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Survey is already published'
                ], 422);
            }

            if (empty($survey->questions) || count($survey->questions) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot publish survey without questions'
                ], 422);
            }

            $survey->publish();

            return response()->json([
                'success' => true,
                'message' => 'Survey published successfully',
                'data' => [
                    'survey' => [
                        'id' => $survey->id,
                        'status' => $survey->status,
                        'published_at' => $survey->published_at,
                        'url' => $survey->url,
                        'share_url' => $survey->share_url,
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close a survey.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $survey = Survey::where('id', $id)
                          ->where('created_by', $user->id)
                          ->firstOrFail();

            if ($survey->isClosed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Survey is already closed'
                ], 422);
            }

            $survey->close();

            return response()->json([
                'success' => true,
                'message' => 'Survey closed successfully',
                'data' => [
                    'survey' => [
                        'id' => $survey->id,
                        'status' => $survey->status,
                        'updated_at' => $survey->updated_at,
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a survey.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $originalSurvey = Survey::where('id', $id)
                                  ->where('created_by', $user->id)
                                  ->firstOrFail();

            $duplicatedSurvey = Survey::create([
                'title' => $originalSurvey->title . ' (Copy)',
                'description' => $originalSurvey->description,
                'category' => $originalSurvey->category,
                'estimated_time' => $originalSurvey->estimated_time,
                'questions' => $originalSurvey->questions,
                'settings' => $originalSurvey->settings,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            $duplicatedSurvey->load(['creator:id,name,email']);

            return response()->json([
                'success' => true,
                'message' => 'Survey duplicated successfully',
                'data' => [
                    'survey' => [
                        'id' => $duplicatedSurvey->id,
                        'title' => $duplicatedSurvey->title,
                        'description' => $duplicatedSurvey->description,
                        'category' => $duplicatedSurvey->category,
                        'status' => $duplicatedSurvey->status,
                        'question_count' => $duplicatedSurvey->question_count,
                        'estimated_time' => $duplicatedSurvey->estimated_time,
                        'questions' => $duplicatedSurvey->questions,
                        'settings' => $duplicatedSurvey->settings,
                        'url' => $duplicatedSurvey->url,
                        'share_url' => $duplicatedSurvey->share_url,
                        'analytics' => $duplicatedSurvey->analytics,
                        'creator' => $duplicatedSurvey->creator,
                        'created_at' => $duplicatedSurvey->created_at,
                        'updated_at' => $duplicatedSurvey->updated_at,
                    ]
                ]
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Survey not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate survey',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

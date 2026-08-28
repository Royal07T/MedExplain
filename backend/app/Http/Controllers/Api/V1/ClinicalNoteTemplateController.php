<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNoteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ClinicalNoteTemplateController extends Controller
{
    /**
     * List clinical note templates for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $query = ClinicalNoteTemplate::forOrganization($organizationId);

        // Filter by specialty if provided
        if ($request->has('specialty')) {
            $query->bySpecialty($request->specialty);
        }

        // Filter by note type if provided
        if ($request->has('note_type')) {
            $query->byNoteType($request->note_type);
        }

        // Filter active only
        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $templates = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'specialty' => $template->specialty,
                    'note_type' => $template->note_type,
                    'structure' => $template->structure,
                    'default_subjective' => $template->default_subjective,
                    'default_objective' => $template->default_objective,
                    'default_assessment' => $template->default_assessment,
                    'default_plan' => $template->default_plan,
                    'is_active' => $template->is_active,
                    'created_by' => $template->created_by,
                    'updated_by' => $template->updated_by,
                    'created_at' => $template->created_at?->toISOString(),
                    'updated_at' => $template->updated_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get a single clinical note template.
     */
    public function show($id): JsonResponse
    {
        $organizationId = request()->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $template = ClinicalNoteTemplate::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'specialty' => $template->specialty,
                'note_type' => $template->note_type,
                'structure' => $template->structure,
                'default_subjective' => $template->default_subjective,
                'default_objective' => $template->default_objective,
                'default_assessment' => $template->default_assessment,
                'default_plan' => $template->default_plan,
                'is_active' => $template->is_active,
                'created_by' => $template->created_by,
                'updated_by' => $template->updated_by,
                'created_at' => $template->created_at?->toISOString(),
                'updated_at' => $template->updated_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Create a new clinical note template.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:100'],
            'note_type' => ['required', 'in:admission,progress,discharge,consultation,procedure,other'],
            'structure' => ['sometimes', 'array'],
            'default_subjective' => ['sometimes', 'nullable', 'string'],
            'default_objective' => ['sometimes', 'nullable', 'string'],
            'default_assessment' => ['sometimes', 'nullable', 'string'],
            'default_plan' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $template = ClinicalNoteTemplate::create([
            'organization_id' => $organizationId,
            'name' => $request->name,
            'specialty' => $request->specialty,
            'note_type' => $request->note_type,
            'structure' => $request->structure ?? [],
            'default_subjective' => $request->default_subjective,
            'default_objective' => $request->default_objective,
            'default_assessment' => $request->default_assessment,
            'default_plan' => $request->default_plan,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'note_type' => $template->note_type,
                'message' => 'Clinical note template created successfully',
            ],
        ], 201);
    }

    /**
     * Update a clinical note template.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'specialty' => ['sometimes', 'string', 'max:100'],
            'note_type' => ['sometimes', 'in:admission,progress,discharge,consultation,procedure,other'],
            'structure' => ['sometimes', 'array'],
            'default_subjective' => ['sometimes', 'nullable', 'string'],
            'default_objective' => ['sometimes', 'nullable', 'string'],
            'default_assessment' => ['sometimes', 'nullable', 'string'],
            'default_plan' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $template = ClinicalNoteTemplate::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->has('name')) {
            $template->name = $request->name;
        }
        if ($request->has('specialty')) {
            $template->specialty = $request->specialty;
        }
        if ($request->has('note_type')) {
            $template->note_type = $request->note_type;
        }
        if ($request->has('structure')) {
            $template->structure = $request->structure;
        }
        if ($request->has('default_subjective')) {
            $template->default_subjective = $request->default_subjective;
        }
        if ($request->has('default_objective')) {
            $template->default_objective = $request->default_objective;
        }
        if ($request->has('default_assessment')) {
            $template->default_assessment = $request->default_assessment;
        }
        if ($request->has('default_plan')) {
            $template->default_plan = $request->default_plan;
        }
        if ($request->has('is_active')) {
            $template->is_active = $request->is_active;
        }
        $template->updated_by = $request->user()->id;
        $template->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'message' => 'Clinical note template updated successfully',
            ],
        ]);
    }

    /**
     * Delete a clinical note template.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $template = ClinicalNoteTemplate::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Clinical note template deleted successfully',
        ]);
    }
}

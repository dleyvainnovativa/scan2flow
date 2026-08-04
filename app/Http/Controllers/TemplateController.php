<?php

namespace App\Http\Controllers;

use App\Http\Requests\TemplateRequest;
use App\Models\Area;
use App\Models\Template;
use App\Models\TemplateField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PlanGate;

class TemplateController extends Controller
{
    public function __construct(private PlanGate $planGate) {}
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Template::with('area')->withCount('fields')->orderBy('name');

        if (! $user->isAdmin()) {
            $areaIds = $user->areas()->where('can_view', true)->pluck('areas.id');
            $query->whereIn('area_id', $areaIds);
        }

        $templates = $query->get();
        $areas = $user->isAdmin() ? Area::orderBy('name')->get() : collect();
        $remainingTemplates = app(\App\Services\PlanGate::class)->remaining('templates');
        $used = $remainingTemplates !== null ? $templates->count() : null;

        return view('templates.index', compact('templates', 'areas', 'remainingTemplates', 'used'));
    }

    public function show(Request $request, Template $template)
    {
        $this->authorize('view', $template);
        $template->load(['area', 'fields']);
        $remainingTemplates = app(\App\Services\PlanGate::class)->remaining('templates');
        $used = $remainingTemplates !== null ? Template::count() : null;
        return view('templates.show', compact('template', 'remainingTemplates', 'used'));
    }

    /** JSON payload used by the edit modal to prefill the builder. */
    public function json(Template $template)
    {
        $this->authorize('view', $template);

        return response()->json(
            $template->load('fields')->only([
                'id',
                'area_id',
                'name',
                'input_folder_path',
                'naming_rule',
                'ai_enabled', // >>> ADD
            ])
                + ['fields' => $template->fields->map->only(['label', 'type', 'is_required', 'options'])]
        );
    }

    public function store(TemplateRequest $request)
    {
        $this->authorize('create', Template::class);
        $this->planGate->ensureCanCreate('templates');

        $template = DB::transaction(function () use ($request) {
            $template = Template::create($request->safe()->only([
                'area_id',
                'name',
                'description',
                'input_folder_path',
                'naming_rule',
                'ai_enabled',                          // >>> ADD
            ]));

            $this->syncFields($template, $request->validated('fields'));

            return $template;
        });

        return response()->json([
            'message'  => 'Plantilla creada.',
            'template' => $template->load('fields'),
        ], 201);
    }

    public function update(TemplateRequest $request, Template $template)
    {
        $this->authorize('update', $template);

        DB::transaction(function () use ($request, $template) {
            $template->update($request->safe()->only([
                'area_id',
                'name',
                'description',
                'input_folder_path',
                'naming_rule',
                'ai_enabled',
            ]));

            // Replace fields wholesale. Safe in Phase 2 (no documents yet).
            // Phase 3+ note: once documents exist, guard against deleting fields
            // that already have metadata, or migrate their values first.
            $template->fields()->delete();
            $this->syncFields($template, $request->validated('fields'));
        });

        return response()->json([
            'message'  => 'Plantilla actualizada.',
            'template' => $template->load('fields'),
        ]);
    }

    public function destroy(Template $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json(['message' => 'Plantilla eliminada.']);
    }

    /**
     * Create TemplateField rows from the request payload, deriving a unique
     * `key` slug per field and normalizing select options.
     */
    private function syncFields(Template $template, array $fields): void
    {
        $usedKeys = [];

        foreach (array_values($fields) as $position => $field) {
            $key = $this->uniqueKey($field['label'], $usedKeys);
            $usedKeys[] = $key;

            $isSelect = ($field['type'] ?? 'text') === 'select';
            $options = $isSelect
                ? array_values(array_filter(array_map('trim', $field['options'] ?? [])))
                : null;

            TemplateField::create([
                'template_id' => $template->id,
                'key'         => $key,
                'label'       => $field['label'],
                'type'        => $field['type'],
                'is_required' => (bool) ($field['is_required'] ?? false),
                'position'    => $position,
                'options'     => $options,
            ]);
        }
    }

    private function uniqueKey(string $label, array $used): string
    {
        $base = Str::slug($label, '_') ?: 'campo';
        $key = $base;
        $i = 2;
        while (in_array($key, $used, true)) {
            $key = "{$base}_{$i}";
            $i++;
        }
        return $key;
    }
}

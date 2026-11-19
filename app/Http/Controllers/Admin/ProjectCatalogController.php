<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MissionSubmission;
use App\Models\MissionTemplateStep;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Services\CatalogEditor;
use App\Services\OpportunityCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectCatalogController extends Controller
{
    private const MODELS = CatalogEditor::MODELS + ['projects' => Project::class, 'missions' => ProjectMission::class, 'verification' => MissionSubmission::class];

    public const COLUMNS = [
        'categories' => ['name' => 'Nume', 'description' => 'Descriere', 'visibility' => 'Vizibilitate', 'status' => 'Stare'],
        'opportunities' => ['title' => 'Titlu', 'category.name' => 'Categorie', 'reward_label' => 'Valoare', 'payer' => 'Plătitor', 'ends_at' => 'Expiră', 'status' => 'Stare'],
        'eligibility' => ['name' => 'Nume', 'category.name' => 'Categorie', 'conditions_text' => 'Condiții', 'geography' => 'Geografie', 'status' => 'Stare'],
        'mission-templates' => ['title' => 'Titlu', 'opportunity.title' => 'Oportunitate', 'verification_method' => 'Verificare', 'xp_points' => 'XP', 'status' => 'Stare'],
        'missions' => ['title' => 'Misiune', 'project.user.name' => 'Utilizator', 'verification_method' => 'Verificare', 'xp_points' => 'XP', 'due_on' => 'Termen', 'status' => 'Stare'],
        'verification' => ['mission.project.user.name' => 'Utilizator', 'mission.title' => 'Misiune', 'method' => 'Tip', 'submitted_at' => 'Data', 'status' => 'Stare'],
        'projects' => ['title' => 'Proiect', 'user.name' => 'Titular', 'accepted_at' => 'Acceptat', 'started_at' => 'Început', 'status' => 'Stare'],
    ];

    private const RELATIONS = ['opportunities' => ['category'], 'eligibility' => ['category'], 'mission-templates' => ['opportunity', 'steps'],
        'missions' => ['project.user', 'steps'], 'verification' => ['mission.project.user'], 'projects' => ['user']];

    public function index(Request $request): View
    {
        $entity = $request->route('entity');
        $query = $this->filtered($request, $entity);

        return view('admin.pages.'.$entity, $this->page($entity) + ['columns' => self::COLUMNS[$entity],
            'items' => $query->paginate($entity === 'mission-templates' ? 18 : 6)->withQueryString()]);
    }

    private function filtered(Request $request, string $entity): Builder
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|string|max:40', 'sort' => 'nullable|string|max:80',
            'dir' => 'nullable|in:asc,desc', 'range' => 'nullable|in:7,30,90', 'due_on' => 'nullable|date_format:Y-m-d']);
        $columns = self::COLUMNS[$entity];
        $query = self::MODELS[$entity]::with(self::RELATIONS[$entity] ?? []);
        if ($term = $data['q'] ?? null) {
            $query->where(function ($q) use ($term, $columns) {
                foreach (array_keys($columns) as $column) {
                    if (str_contains($column, '.')) {
                        $q->orWhereHas(Str::beforeLast($column, '.'), fn ($rel) => $rel->where(Str::afterLast($column, '.'), 'like', "%{$term}%"));
                    } else {
                        $q->orWhere($column, 'like', "%{$term}%");
                    }
                }
            });
        }
        if ($status = $data['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($entity === 'verification' && isset($data['range'])) {
            $query->where('submitted_at', '>=', now()->subDays((int) $data['range']));
        }
        if ($entity === 'missions' && isset($data['due_on'])) {
            $query->where(fn ($q) => $q->whereDate('due_on', $data['due_on'])->orWhere(fn ($inherited) => $inherited
                ->whereNull('due_on')->whereNull('deadline_anchor')->whereHas('project', fn ($p) => $p->whereDate('due_on', $data['due_on']))));
        }
        $sort = $data['sort'] ?? 'id';
        if (! isset($columns[$sort])) {
            $sort = 'id';
        }
        $dir = $data['dir'] ?? 'desc';
        if (str_contains($sort, '.')) {
            $model = $query->getModel();
            $parent = $model->getTable();
            $parts = explode('.', $sort);
            $column = array_pop($parts);
            $subquery = DB::query();
            foreach ($parts as $index => $relationship) {
                $relation = $model->{$relationship}();
                $model = $relation->getRelated();
                $alias = 'ordered_'.$index;
                $table = $model->getTable().' as '.$alias;
                $foreignKey = $parent.'.'.$relation->getForeignKeyName();
                $ownerKey = $alias.'.'.$relation->getOwnerKeyName();
                if ($index === 0) {
                    $subquery->from($table)->whereColumn($ownerKey, $foreignKey);
                } else {
                    $subquery->join($table, $ownerKey, '=', $foreignKey);
                }
                $parent = $alias;
            }
            $query->orderBy($subquery->select($parent.'.'.$column)->limit(1), $dir);
        } elseif ($entity === 'opportunities' && $sort === 'reward_label') {
            $query->orderBy('reward_unit', $dir)->orderBy('reward_currency', $dir)->orderBy('reward_amount_minor', $dir)->orderBy('reward_bps', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        return $query->orderBy('id');
    }

    public function export(Request $request): StreamedResponse
    {
        $entity = $request->route('entity');
        $columns = self::COLUMNS[$entity];
        $query = $this->filtered($request, $entity);

        return response()->streamDownload(function () use ($columns, $query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, array_values($columns), ',', '"', '');
            foreach ($query->lazy(200) as $row) {
                $values = [];
                foreach (array_keys($columns) as $key) {
                    $value = (string) data_get($row, $key, '');
                    $values[] = preg_match('/^[\s]*[=+\-@\t\r]/u', $value) ? "'".$value : $value;
                }
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, $entity.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function page(string $entity): array
    {
        return ['entity' => $entity, 'demoPage' => $entity, 'pageTitle' => CatalogEditor::TITLES[$entity], 'projectAdmin' => true, 'auraAdminServerRendered' => true];
    }

    public function form(Request $request): View
    {
        $entity = $request->route('entity');
        $class = CatalogEditor::MODELS[$entity];
        $record = $request->route('record') ? $class::findOrFail($request->route('record')) : new $class;
        $values = $record->attributesToArray();
        if ($entity === 'opportunities' && $record->exists) {
            $values['starts_at'] = $record->starts_at->setTimezone(OpportunityCatalog::TIMEZONE)->toDateString();
            $values['ends_at'] = $record->ends_at?->setTimezone(OpportunityCatalog::TIMEZONE)->subDay()->toDateString();
        }
        if ($entity === 'mission-templates') {
            $values['steps'] = $record->exists ? $record->steps->where('status', '!=', 'archived')->values()->toArray() : [];
        }

        return view('admin.pages.project-form', self::page($entity) + [
            'record' => $record, 'values' => $values, 'fields' => CatalogEditor::fields($entity), 'readonly' => $request->routeIs('*.show'),
            'choices' => ['category_id' => Category::orderBy('name')->pluck('name', 'id'), 'opportunity_id' => Opportunity::orderBy('title')->pluck('title', 'id'),
                'anchor_step_id' => MissionTemplateStep::with('template')->where('status', 'active')->orderBy('template_id')->orderBy('position')->get()
                    ->mapWithKeys(fn ($step) => [$step->id => $step->template->title.' · '.$step->title.' (#'.$step->id.')'])],
        ]);
    }

    public function archive(Request $request, CatalogEditor $editor): RedirectResponse
    {
        $entity = $request->route('entity');
        $data = $request->validate(['version' => 'nullable|integer|min:1']);
        $editor->archive($entity, (int) $request->route('record'), $data['version'] ?? null, $request->user());

        return to_route('aura.admin.'.$entity)->with('status', 'Înregistrarea a fost arhivată.');
    }

    public function save(Request $request, CatalogEditor $editor): RedirectResponse
    {
        $entity = $request->route('entity');
        $record = $editor->save($entity, $request->route('record'), $request->all(), $request->user());

        return to_route('aura.admin.'.$entity.'.show', $record)->with('status', 'Modificările au fost salvate.');
    }
}

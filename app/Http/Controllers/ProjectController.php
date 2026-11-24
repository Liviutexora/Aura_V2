<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExternalEarning;
use App\Models\Opportunity;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Models\User;
use App\Services\MissionProgress;
use App\Services\OpportunityCatalog;
use App\Services\ProjectEnrollment;
use App\Services\SubscriptionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private OpportunityCatalog $catalog, private ProjectEnrollment $enrollment, private SubscriptionAccess $access) {}

    public function catalog(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'category' => 'nullable|integer', 'difficulty' => 'nullable|in:Ușor,Mediu,Dificil',
            'reward' => 'nullable|in:100', 'time' => 'nullable|in:15', 'availability' => 'nullable|in:available,expired']);
        $query = $this->catalog->published(($filters['availability'] ?? '') === 'expired', $this->catalogLevel($request->user()));
        if ($term = $filters['q'] ?? null) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('summary', 'like', "%{$term}%"));
        }
        foreach (['category' => 'category_id', 'difficulty' => 'difficulty'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['reward'])) {
            $query->where('reward_unit', 'money')->where('reward_currency', 'RON')->where('reward_amount_minor', '>=', 10000);
        }
        if (! empty($filters['time'])) {
            $query->where('estimated_minutes', '<=', 15);
        }

        return view('opportunities.index', ['offers' => $query->orderByDesc('is_featured')->latest('id')->paginate(24)->withQueryString(),
            'categories' => Category::where('status', 'active')->where('visibility', 'all')->orderBy('name')->get()]);
    }

    public function opportunity(Request $request, Opportunity $opportunity): View
    {
        $project = $request->user()->projects()->where('opportunity_id', $opportunity->id)->first();
        $level = $this->catalogLevel($request->user());
        $available = $this->catalog->published(false, $level)->whereKey($opportunity->id)->exists();
        abort_unless($project || $available || $this->catalog->published(true, $level)->whereKey($opportunity->id)->exists(), 404);
        $offer = $project ? (new Opportunity)->forceFill($project->offer_snapshot) : $opportunity;
        $offer->setRelation('category', $project ? Category::find($offer->category_id) : $opportunity->category);
        $templates = $project ? $project->missions()->with('steps')->get() : $opportunity->templates()->where('assign_on_accept', true)->where('status', 'active')
            ->with(['steps' => fn ($q) => $q->where('status', 'active')])->orderBy('position')->get();
        $rules = $project ? collect($project->eligibility_snapshot) : $this->catalog->rules($opportunity);

        return view('opportunities.show', compact('offer', 'opportunity', 'templates', 'project', 'rules', 'available'));
    }

    public function accept(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['attestations' => 'nullable|array', 'attestations.*' => 'string|max:64', 'participation_type' => 'sometimes|in:explorer,mentor']);
        $project = $this->enrollment->accept($request->user(), $opportunity, $request->user(), $data['attestations'] ?? [], type: $data['participation_type'] ?? null);

        return to_route('aura.projects.show', $project)->with('status', 'Oportunitate acceptată.');
    }

    public function index(Request $request): View
    {
        $tab = $request->validate(['tab' => 'nullable|in:active,completed,history'])['tab'] ?? 'active';
        $query = $request->user()->projects()->with('missions.steps')->latest('accepted_at');
        if ($tab !== 'history') {
            $query->where('status', $tab === 'completed' ? '=' : '!=', 'completed');
        }

        return view('projects.index', ['items' => $query->paginate(20)->withQueryString(), 'tab' => $tab, 'projects' => true]);
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($project->user_id === $request->user()->id, 404);
        $project->load('missions.steps', 'earnings');

        return view('projects.show', ['project' => $project, 'events' => $project->events()->where('event_type', '!=', 'request_processed')->latest('id')->paginate(30)]);
    }

    public function start(Request $request, Project $project): RedirectResponse
    {
        $this->enrollment->start($project, $request->user());

        return to_route('aura.projects.show', $project)->with('status', 'Proiect început.');
    }

    public static function paymentRules(bool $required): array
    {
        return ['amount' => [$required ? 'required' : 'nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'currency' => [$required ? 'required' : 'required_with:amount', 'nullable', 'regex:/^[A-Z]{3}$/'],
            'earning_key' => [$required ? 'required' : 'required_with:amount', 'nullable', 'uuid'], 'description' => 'nullable|string|max:1000'];
    }

    public function earning(Request $request, Project $project, MissionProgress $progress): RedirectResponse
    {
        $progress->declareEarning($project, $request->user(), $request->validate(self::paymentRules(true)));

        return back()->with('status', 'Încasarea a fost înregistrată.');
    }

    public function landing(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isActive() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('aura.verify-email');
        }

        $data = $request->user()?->isActive() ? $this->dashboardData($request) : [
            'projects' => collect(), 'currentMission' => null, 'completedCount' => 0, 'missionCount' => 0, 'totals' => collect(),
            'offers' => $this->catalog->published(maxAccessLevel: 1)->orderByDesc('is_featured')->latest('id')->limit(3)->get(),
        ];

        return view('public.landing', $data + ['demoRoute' => 'home', 'publicOnly' => ! $request->user(), 'activePlans' => Plan::activeCatalog()]);
    }

    public function dashboard(Request $request): View
    {
        return view('dashboard.index', $this->dashboardData($request) + ['demoRoute' => 'dashboard']);
    }

    private function catalogLevel(User $user): int
    {
        return $this->access->current($user)?->terms['access_level'] ?? 1;
    }

    private function dashboardData(Request $request): array
    {
        $user = $request->user();
        $projects = $user->projects()->with('missions.steps')->latest('accepted_at')->limit(3)->get();
        $missions = ProjectMission::whereHas('project', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->where('status', '!=', 'completed');

        return ['projects' => $projects,
            'completedCount' => $user->projects()->where('status', 'completed')->count(), 'missionCount' => $missions->count(),
            'currentMission' => $missions->with('steps', 'project')->orderBy('id')->first(),
            'offers' => $this->catalog->published(maxAccessLevel: $this->catalogLevel($user))->orderByDesc('is_featured')->latest('id')->limit(3)->get(),
            'totals' => ExternalEarning::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
                ->where('status', 'confirmed')->groupBy('currency')->selectRaw('currency, SUM(amount_minor) AS total')->get()];
    }
}

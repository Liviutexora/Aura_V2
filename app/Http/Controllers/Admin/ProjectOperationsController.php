<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MissionSubmission;
use App\Models\MissionTemplate;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Models\User;
use App\Services\MissionProgress;
use App\Services\ProjectEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectOperationsController extends Controller
{
    public function form(Request $request): View
    {
        return view('admin.pages.project-allocation', ProjectCatalogController::page($request->route('entity')) + [
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'offers' => Opportunity::orderBy('title')->get(['id', 'title']),
            'templates' => MissionTemplate::where('status', 'active')->orderBy('opportunity_id')->orderBy('position')->get(['id', 'title', 'opportunity_id'])]);
    }

    public function allocate(Request $request, ProjectEnrollment $enrollment): RedirectResponse
    {
        $isMission = $request->route('entity') === 'missions';
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'opportunity_id' => 'required|integer|exists:opportunities,id',
            'template_id' => $isMission ? 'required|integer|exists:mission_templates,id' : 'prohibited',
            'participation_type' => 'sometimes|in:explorer,mentor']);
        $project = $enrollment->accept(User::findOrFail($data['user_id']), Opportunity::findOrFail($data['opportunity_id']), $request->user(), [],
            $isMission ? MissionTemplate::findOrFail($data['template_id']) : null, $data['participation_type'] ?? null);

        return to_route('aura.admin.projects.show', $project)->with('status', 'Alocarea a fost salvată.');
    }

    public function project(Project $project): View
    {
        $project->load('user', 'missions.steps', 'earnings');

        return view('admin.pages.project-progress', ProjectCatalogController::page('projects') + compact('project'));
    }

    public function mission(ProjectMission $mission): View
    {
        $mission->load('project.user', 'steps', 'submissions.reviewer');

        return view('admin.pages.project-progress', ProjectCatalogController::page('missions') + ['project' => $mission->project, 'mission' => $mission]);
    }

    public function deadline(Request $request, ProjectMission $mission, ProjectEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['due_on' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($mission, $request, $data, $enrollment) {
            $project = Project::lockForUpdate()->findOrFail($mission->project_id);
            $locked = $project->missions()->lockForUpdate()->findOrFail($mission->id);
            abort_if($locked->status === 'completed', 409);
            $old = $locked->effectiveDueOn()?->toDateString();
            if ($old !== $data['due_on']) {
                $locked->update(['due_on' => $data['due_on']]);
                $enrollment->event($project, $request->user(), 'deadline_changed', $locked, metadata: ['previous' => $old, ...$data]);
            }
        }, 3);

        return back()->with('status', 'Termen actualizat.');
    }

    public function verification(MissionSubmission $submission): View
    {
        $submission->load('mission.project.user', 'mission.submissions.reviewer', 'step', 'earning', 'reviewer');

        return view('admin.pages.project-review', ProjectCatalogController::page('verification') + compact('submission'));
    }

    public function review(Request $request, MissionSubmission $submission, MissionProgress $progress): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'review_note' => 'nullable|required_if:status,rejected|string|max:1000']);
        $progress->review($submission, $request->user(), $data['status'], $data['review_note'] ?? null);

        return back()->with('status', 'Decizia a fost înregistrată.');
    }
}

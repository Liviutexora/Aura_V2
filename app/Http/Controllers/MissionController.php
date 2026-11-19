<?php

namespace App\Http\Controllers;

use App\Models\MissionSubmission;
use App\Models\Project;
use App\Models\ProjectMission;
use App\Models\ProjectMissionStep;
use App\Services\MissionProgress;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissionController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->validate(['tab' => 'nullable|in:active,completed,history'])['tab'] ?? 'active';
        $query = ProjectMission::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))->with('steps', 'project')->latest('assigned_at');
        if ($tab !== 'history') {
            $query->where('status', $tab === 'completed' ? '=' : '!=', 'completed');
        }

        return view('missions.index', ['items' => $query->paginate(20)->withQueryString(), 'tab' => $tab, 'projects' => false]);
    }

    public function show(Request $request, ProjectMission $mission): View
    {
        $this->owner($request, $mission);
        $mission->load('steps', 'submissions.earning', 'reminder', 'project.earnings');

        return view('missions.show', compact('mission'));
    }

    private function owner(Request $request, ProjectMission $mission): void
    {
        abort_unless($mission->project->user_id === $request->user()->id, 404);
    }

    public function check(Request $request, ProjectMission $mission, ProjectMissionStep $step, MissionProgress $progress): RedirectResponse
    {
        $this->owner($request, $mission);
        abort_unless($step->mission_id === $mission->id, 404);
        abort_unless($step->verification_method === 'user_declaration', 422);
        $data = $request->validate(['checked' => 'required|boolean', 'request_key' => 'required|uuid'] + ProjectController::paymentRules(false));
        $data['checked'] = $request->boolean('checked');
        $progress->submit($request->user(), $mission, $step->id, $data);

        return back()->with('status', 'Progres actualizat.');
    }

    public function submit(Request $request, ProjectMission $mission, MissionProgress $progress): RedirectResponse
    {
        $this->owner($request, $mission);
        $data = $request->validate(['request_key' => 'required|uuid', 'step_id' => 'nullable|integer|min:1',
            'evidence_text' => 'nullable|string|max:5000', 'evidence' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:8192'] + ProjectController::paymentRules(false));
        $file = $request->file('evidence');
        if ($file) {
            $data['file_hash'] = hash_file('sha256', $file->getRealPath());
            $data['private_file_key'] = $file->store('mission-evidence', 'local');
            abort_unless($data['private_file_key'], 500);
        }
        try {
            $progress->submit($request->user(), $mission, $data['step_id'] ?? null, $data);
        } finally {
            if ($file && ! MissionSubmission::where('private_file_key', $data['private_file_key'])->exists()) {
                Storage::disk('local')->delete($data['private_file_key']);
            }
        }

        return back()->with('status', 'Confirmare înregistrată.');
    }

    public function evidence(Request $request, MissionSubmission $submission): StreamedResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $submission->mission->project->user_id === $request->user()->id, 404);
        abort_unless($submission->private_file_key && Storage::disk('local')->exists($submission->private_file_key), 404);

        return Storage::disk('local')->download($submission->private_file_key, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function reminder(Request $request, ProjectMission $mission): RedirectResponse
    {
        $this->owner($request, $mission);
        $zone = $request->user()->profile?->timezone ?: 'Europe/Bucharest';
        $date = null;
        if (! $request->isMethod('delete')) {
            $request->validate(['remind_at' => 'required|date_format:Y-m-d\TH:i']);
            $date = CarbonImmutable::parse($request->input('remind_at'), $zone)->utc();
            if ($date->isPast()) {
                throw ValidationException::withMessages(['remind_at' => 'Alege o dată viitoare.']);
            }
        }
        DB::transaction(function () use ($mission, $date, $zone) {
            Project::lockForUpdate()->findOrFail($mission->project_id);
            $locked = ProjectMission::lockForUpdate()->findOrFail($mission->id);
            $reminder = $locked->reminder()->first();
            if (! $date) {
                if ($reminder && $reminder->status !== 'cancelled') {
                    $reminder->update(['status' => 'cancelled', 'revision' => $reminder->revision + 1]);
                }
            } elseif (! $reminder || $reminder->status !== 'scheduled' || ! $reminder->remind_at->equalTo($date)) {
                $locked->reminder()->updateOrCreate([], ['status' => 'scheduled', 'remind_at' => $date, 'timezone' => $zone, 'sent_at' => null, 'revision' => 1 + (int) $reminder?->revision]);
            }
        }, 3);

        return back()->with('status', $date ? 'Memento programat.' : 'Memento anulat.');
    }
}

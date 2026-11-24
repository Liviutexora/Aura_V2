<?php

namespace App\Services;

use App\Models\Category;
use App\Models\EligibilityRule;
use App\Models\MissionTemplate;
use App\Models\MissionTemplateStep;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogEditor
{
    public const MODELS = ['categories' => Category::class, 'opportunities' => Opportunity::class, 'eligibility' => EligibilityRule::class, 'mission-templates' => MissionTemplate::class];

    public const TITLES = ['categories' => 'Categorii de oportunități', 'opportunities' => 'Oportunități', 'eligibility' => 'Reguli de eligibilitate', 'mission-templates' => 'Șabloane de misiuni', 'missions' => 'Misiuni & șabloane', 'verification' => 'Verificări misiuni', 'projects' => 'Proiectele utilizatorilor'];

    public const FIELDS = [
        'categories' => [
            'name' => ['Nume', 'required|string|max:255'],
            'slug' => ['Slug', 'nullable|alpha_dash|max:255'],
            'description' => ['Descriere', 'nullable|string|max:10000'],
            'color' => ['Culoare', 'nullable|hex_color'],
            'visibility' => ['Vizibilitate', 'required|in:all,premium'],
            'status' => ['Stare', 'required|in:draft,active,paused,archived'],
        ],
        'opportunities' => [
            'category_id' => ['Categorie', 'required|exists:categories,id'],
            'title' => ['Titlu', 'required|string|max:255'],
            'summary' => ['Descriere', 'required|string|max:10000'],
            'provider' => ['Furnizor', 'required|string|max:255'],
            'source_label' => ['Sursă', 'nullable|string|max:255'],
            'source_url' => ['Link sursă', 'nullable|url:http,https|max:2000'],
            'geography' => ['Geografie', 'nullable|string|max:255'],
            'eligibility_text' => ['Condiții de eligibilitate', 'nullable|string|max:10000'],
            'instructions' => ['Instrucțiuni', 'nullable|string|max:10000'],
            'terms' => ['Termeni și condiții', 'required|string|max:20000'],
            'difficulty' => ['Dificultate', 'required|in:Ușor,Mediu,Dificil'],
            'estimated_minutes' => ['Durată estimată (minute)', 'required|integer|min:0|max:1000000'],
            'icon' => ['Pictogramă', 'nullable|alpha_dash|max:40'],
            'image_path' => ['Imagine (cale publică sau adresă HTTPS)', 'nullable|string|max:255'],
            'is_featured' => ['Recomandată', 'boolean'],
            'starts_at' => ['Începutul înscrierilor', 'required|date_format:Y-m-d'],
            'ends_at' => ['Ultima zi de înscriere', 'nullable|date_format:Y-m-d|after_or_equal:starts_at'],
            'project_due_on' => ['Termen fix al proiectului', 'nullable|date_format:Y-m-d|prohibits:project_duration_days'],
            'project_duration_days' => ['Zile de la acceptare', 'nullable|integer|min:0|max:36500'],
            'participation_limit' => ['Limită participanți', 'nullable|integer|min:1|max:2147483647'],
            'reward_type' => ['Tip recompensă', 'required|in:fixed,percentage,cashback,external,non_cash'],
            'reward_unit' => ['Unitate', 'required|in:money,percent,non_cash'],
            'reward_amount_minor' => ['Recompensă în unități minime (bani)', 'nullable|required_if:reward_unit,money|integer|min:0|max:999999999999'],
            'reward_currency' => ['Monedă', 'nullable|required_if:reward_unit,money|regex:/^[A-Z]{3}$/'],
            'reward_bps' => ['Procent în puncte de bază (100 = 1%)', 'nullable|required_if:reward_unit,percent|integer|min:0|max:10000'],
            'reward_label' => ['Recompensă afișată', 'required|string|max:255'],
            'payer' => ['Plătitor', 'required|string|max:255'],
            'status' => ['Stare', 'required|in:draft,published,paused,archived'],
        ],
        'eligibility' => [
            'category_id' => ['Categorie', 'required|exists:categories,id'],
            'opportunity_id' => ['Oportunitate (opțional)', 'nullable|exists:opportunities,id'],
            'name' => ['Nume', 'required|string|max:255'],
            'conditions_text' => ['Condiții', 'required|string|max:10000'],
            'geography' => ['Geografie', 'nullable|string|max:255'],
            'country_code' => ['Cod țară', 'nullable|size:2|alpha'],
            'visibility' => ['Vizibilitate', 'required|in:all,premium'],
            'check_method' => ['Metodă', 'required|in:system,user_attestation,partner_review'],
            'criterion_code' => ['Criteriu de sistem', 'nullable|required_if:check_method,system|in:active_account,verified_email'],
            'status' => ['Stare', 'required|in:draft,active,paused,archived'],
        ],
        'mission-templates' => [
            'opportunity_id' => ['Oportunitate', 'required|exists:opportunities,id'],
            'title' => ['Titlu', 'required|string|max:255'],
            'description' => ['Descriere', 'nullable|string|max:10000'],
            'required_action' => ['Acțiune obligatorie', 'nullable|string|max:10000'],
            'verification_method' => ['Verificare', 'required|in:user_declaration,admin_approval,document_upload,provider_event'],
            'xp_points' => ['XP', 'nullable|integer|min:0|max:1000000'],
            'position' => ['Poziție', 'nullable|integer|min:1|max:1000000'],
            'assign_on_accept' => ['Alocare la acceptare', 'boolean'],
            'is_required' => ['Misiune obligatorie', 'boolean'],
            'status' => ['Stare', 'required|in:draft,active,archived'],
        ],
    ];

    public const DEADLINES = [
        'deadline_on' => ['Termen fix', 'nullable|date_format:Y-m-d'],
        'deadline_anchor' => ['Termen relativ la', 'nullable|in:accepted,step_checked,step_approved'],
        'deadline_after_days' => ['Număr de zile', 'nullable|integer|min:0|max:36500'],
        'anchor_step_id' => ['Pas de referință', 'nullable|integer|exists:mission_template_steps,id'],
    ];

    public const STEPS = [
        'id' => ['Identificator', 'nullable|integer|min:1'],
        'title' => ['Titlu', 'required|string|max:255'],
        'description' => ['Descriere', 'nullable|string|max:10000'],
        'instructions' => ['Instrucțiuni', 'nullable|string|max:10000'],
        'required_action' => ['Acțiune obligatorie', 'nullable|string|max:10000'],
        'verification_method' => ['Verificare', 'nullable|in:user_declaration,admin_approval,document_upload,provider_event'],
        'xp_points' => ['XP', 'nullable|integer|min:0|max:1000000'],
        'confirms_receipt' => ['Confirmă primirea recompensei', 'boolean'],
        'status' => ['Stare', 'required|in:active,draft,archived'],
    ];

    public static function fields(string $entity): array
    {
        return self::FIELDS[$entity] + ($entity === 'mission-templates' ? self::DEADLINES : []);
    }

    private function validateFields(array $fields, array $input): array
    {
        return Validator::make($input, array_map(fn (array $field) => $field[1], $fields))->validate();
    }

    public function save(string $entity, ?int $id, array $input, User $actor): Model
    {
        $class = self::MODELS[$entity];
        $fields = self::fields($entity);
        $data = $this->validateFields($fields, $input);
        if ($entity === 'opportunities') {
            $data += Validator::make($input, [
                'min_access_level' => 'sometimes|integer|between:1,1000000',
                'participation_mode' => 'sometimes|in:explorer,mentor,both',
            ])->validate();
        }

        return DB::transaction(function () use ($entity, $class, $id, $data, $input, $actor) {
            $offerId = $data['opportunity_id'] ?? ($entity === 'opportunities' ? $id : null);
            $categoryId = $data['category_id'] ?? ($entity === 'categories' ? $id : null);
            if ($categoryId) {
                Category::lockForUpdate()->findOrFail($categoryId);
            }
            $offer = $offerId ? Opportunity::lockForUpdate()->findOrFail($offerId) : null;
            $record = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            if ($record->exists && $record->version !== null) {
                abort_unless((int) ($input['version'] ?? 0) === (int) $record->version, 409, 'Înregistrarea s-a modificat. Reîncarcă formularul.');
            }
            if ($entity === 'categories') {
                $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
                foreach (['slug', 'name'] as $key) {
                    if (Category::where($key, $data[$key])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                        $this->invalid($key, 'Valoarea este deja folosită de altă categorie.');
                    }
                }
            } else {
                $data['version'] = 1 + (int) $record->version;
            }
            if ($entity === 'opportunities') {
                if (! empty($data['image_path'])) {
                    $path = $data['image_path'];
                    $remote = filter_var($path, FILTER_VALIDATE_URL) && parse_url($path, PHP_URL_SCHEME) === 'https';
                    $local = preg_match('~^/?[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_.-]+)*\.(?:png|jpe?g|webp|gif|avif|svg)$~iD', $path) && ! str_contains($path, '..');
                    if (! $remote && ! $local) {
                        $this->invalid('image_path', 'Introdu calea publică a imaginii sau o adresă HTTPS.');
                    }
                }
                if ($record->exists && $record->category_id != $categoryId && $record->rules()->exists()) {
                    $this->invalid('category_id', 'Mută mai întâi regulile specifice categoriei.');
                }
                foreach (['starts_at', 'ends_at'] as $field) {
                    if (! empty($data[$field])) {
                        $date = CarbonImmutable::parse($data[$field], OpportunityCatalog::TIMEZONE)->startOfDay();
                        $data[$field] = ($field === 'ends_at' ? $date->addDay() : $date)->utc();
                    }
                }
                foreach (['reward_amount_minor' => 'money', 'reward_currency' => 'money', 'reward_bps' => 'percent'] as $field => $unit) {
                    if ($data['reward_unit'] !== $unit) {
                        $data[$field] = null;
                    }
                }
                $data['updated_by'] = $actor->id;
                $data['created_by'] = $record->created_by ?? $actor->id;
            }
            if ($entity === 'eligibility' && $offer && $offer->category_id != $categoryId) {
                $this->invalid('opportunity_id', 'Oportunitatea trebuie să aparțină categoriei.');
            }
            if ($entity === 'mission-templates') {
                if ($record->exists && $record->opportunity_id != $offer->id) {
                    $this->invalid('opportunity_id', 'Șablonul aparține oportunității inițiale.');
                }
                $this->validateDeadline($data);
                $data['xp_points'] ??= 0;
                $data['position'] ??= $record->position ?? (1 + (int) $offer->templates()->max('position'));
                if ($offer->templates()->where('position', $data['position'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                    $this->invalid('position', 'Poziția este ocupată.');
                }
                if (($data['is_required'] ?? false) && ! ($data['assign_on_accept'] ?? false)) {
                    $this->invalid('assign_on_accept', 'O misiune obligatorie se alocă la acceptare.');
                }
            }
            $record->fill($data)->save();
            if ($entity === 'mission-templates') {
                if (($input['steps_complete'] ?? null) != 1) {
                    $this->invalid('steps', 'Lista pașilor este incompletă. Verifică limita câmpurilor formularului.');
                }
                $this->saveSteps($record, $input['steps'] ?? []);
                $this->validatePath($offer);
            } elseif ($entity === 'opportunities') {
                $this->validatePath($record);
            } elseif ($entity === 'eligibility' && $data['status'] === 'active') {
                $affected = Opportunity::where('category_id', $categoryId)->where('status', 'published')
                    ->when($offerId, fn ($q) => $q->whereKey($offerId))->get();
                foreach ($affected as $campaign) {
                    $this->validatePath($campaign);
                }
            }

            return $record;
        }, 3);
    }

    public function archive(string $entity, int $id, ?int $version, User $actor): void
    {
        $class = self::MODELS[$entity];
        DB::transaction(function () use ($class, $id, $version, $actor) {
            $record = $class::lockForUpdate()->findOrFail($id);
            if ($record->status === 'archived') {
                return;
            }
            $updates = ['status' => 'archived'];
            if ($record->version !== null) {
                abort_unless($version === (int) $record->version, 409, 'Reîncarcă înregistrarea înainte de arhivare.');
                $updates['version'] = $version + 1;
            }
            if ($record instanceof Opportunity) {
                $updates['updated_by'] = $actor->id;
            }
            $record->update($updates);
        }, 3);
    }

    private function saveSteps(MissionTemplate $template, mixed $rows): void
    {
        Validator::make(['steps' => $rows], ['steps' => 'array|max:150', 'steps.*' => 'array', 'steps.*.id' => 'nullable|integer|distinct'])->validate();
        $previous = $template->steps()->get()->keyBy('id');
        $offset = (int) $previous->max('position') + count($rows) + 1;
        $template->steps()->reorder()->increment('position', $offset);
        foreach (array_values($rows) as $index => $row) {
            $data = $this->validateFields(self::STEPS + self::DEADLINES, $row);
            $this->validateDeadline($data);
            $step = empty($data['id']) ? new MissionTemplateStep : $previous->pull($data['id']);
            abort_unless($step instanceof MissionTemplateStep, 422);
            $step->fill(Arr::except($data, ['id']) + ['template_id' => $template->id]);
            $step->xp_points = $data['xp_points'] ?? 0;
            $step->position = $index + 1;
            $step->save();
        }
        $position = count($rows);
        foreach ($previous as $step) {
            $step->update(['status' => 'archived', 'position' => ++$position]);
        }
        if ($template->status === 'active' && ! $template->steps()->where('status', 'active')->exists()) {
            $this->invalid('steps', 'Un șablon activ cere cel puțin un pas activ.');
        }
    }

    private function validateDeadline(array $data): void
    {
        $anchor = $data['deadline_anchor'] ?? null;
        if (($anchor && ! empty($data['deadline_on'])) || (bool) $anchor !== isset($data['deadline_after_days'])
            || in_array($anchor, ['step_checked', 'step_approved'], true) !== ! empty($data['anchor_step_id'])) {
            $this->invalid('deadline_anchor', 'Alege un termen fix sau completează reperul și numărul de zile pentru termenul relativ.');
        }
    }

    private function validatePath(Opportunity $offer): void
    {
        $templates = $offer->templates()->where('status', 'active')->with(['steps' => fn ($q) => $q->where('status', 'active')])->orderBy('position')->get();
        $seen = [];
        $receipt = false;
        $partner = false;
        foreach ($templates as $template) {
            foreach (collect([$template])->concat($template->steps) as $target) {
                if ($target->anchor_step_id && (! array_key_exists($target->anchor_step_id, $seen) || ($template->assign_on_accept && ! $seen[$target->anchor_step_id]))) {
                    $this->invalid('anchor_step_id', 'Alege un pas activ anterior, disponibil la alocare.');
                }
                if ($target instanceof MissionTemplateStep) {
                    $seen[$target->id] = $template->assign_on_accept;
                    if ($template->is_required && $template->assign_on_accept) {
                        $receipt = $receipt || $target->confirms_receipt;
                        $partner = $partner || in_array($target->verification_method, ['admin_approval', 'provider_event'], true);
                    }
                }
            }
        }
        if ($offer->status === 'published') {
            if ($offer->category->status !== 'active' || $offer->category->visibility !== 'all' || ! $receipt) {
                $this->invalid('status', 'Publicarea cere o categorie activă accesibilă tuturor și o misiune obligatorie cu un pas de confirmare a recompensei.');
            }
            if (! $partner && (new OpportunityCatalog)->rules($offer)->contains('check_method', 'partner_review')) {
                $this->invalid('status', 'Regula de partener cere un pas obligatoriu verificat.');
            }
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

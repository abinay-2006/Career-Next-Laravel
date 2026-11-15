<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\JobSeeker;
use App\Support\ProfileFieldRules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Copilot updates of the authenticated user's own plain profile fields.
 *
 * The model can only STAGE a change (request_profile_update). Laravel validates it with the same
 * rules as the profile page, shows the exact field changes, and applies them only after the user
 * replies "CONFIRM PROFILE UPDATE" — a message that is handled here and never sent to the model.
 * Only allowlisted columns of the authenticated account can ever be written.
 */
class CopilotProfileUpdateService
{
    public const CONFIRMATION_PHRASE = 'CONFIRM PROFILE UPDATE';
    private const PENDING_TTL_SECONDS = 300;
    private const MAX_VALUE_LENGTH = 1000;

    /** Copilot argument name => [column, label], per role. Nothing outside these maps is writable. */
    private const FIELDS = [
        'jobseeker' => [
            'full_name' => ['JobSeekerName', 'Full name'],
            'address' => ['Address', 'Address'],
            'city' => ['City', 'City'],
            'mobile' => ['Mobile', 'Mobile number'],
            'qualification' => ['Qualification', 'Qualification'],
            'gender' => ['Gender', 'Gender'],
            'birth_date' => ['BirthDate', 'Birth date'],
        ],
        'employer' => [
            'company_name' => ['CompanyName', 'Company name'],
            'contact_person' => ['ContactPerson', 'Contact person'],
            'address' => ['Address', 'Address'],
            'city' => ['City', 'City'],
            'mobile' => ['Mobile', 'Mobile number'],
            'business_area' => ['Area_Work', 'Business area'],
        ],
    ];

    /** Choices offered by the Job Seeker profile page's Gender select. */
    private const GENDERS = ['Male', 'Female', 'Other'];

    /** @return array<string, string> argument name => label, for tool declarations and messages */
    public static function editableFields(string $role): array
    {
        return array_map(fn (array $field) => $field[1], self::FIELDS[$role] ?? []);
    }

    /**
     * Validates and stages a change set for the authenticated account. Never writes the profile.
     *
     * @return array{confirmation_required: bool, changed: false, message: string, errors?: array<string, string>}
     */
    public function stage(Request $request, string $role, JobSeeker|Employer $actor, array $args): array
    {
        $fields = self::FIELDS[$role] ?? null;
        if ($fields === null || !$this->ownsSession($role, $actor)) {
            return $this->refusal('Profile updates are not available for this account.');
        }

        $this->forget($request, $role, $actor);

        $unknown = array_diff(array_keys($args), array_keys($fields));
        if ($unknown !== []) {
            return $this->refusal(
                'These details cannot be changed through Copilot: '.implode(', ', array_map(fn ($k) => $this->safeKey($k), $unknown)).'. '
                .'Copilot can update: '.implode(', ', array_values(self::editableFields($role))).'. '
                .'Email, username, password, photo and account status can only be changed on your Profile page (account status is managed by Career Next). No change was made.'
            );
        }

        if ($args === []) {
            return $this->refusal('Tell me which profile details to change and their new values. No change was made.');
        }

        $changes = [];
        $errors = [];
        foreach ($args as $key => $value) {
            [$column, $label] = $fields[$key];
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                $errors[$label] = 'must be text.';
                continue;
            }
            $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
            if ($value === '') {
                $errors[$label] = 'cannot be empty. To clear a field, use your Profile page.';
                continue;
            }
            if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                $errors[$label] = 'is too long.';
                continue;
            }
            $changes[$column] = $value;
        }

        if (isset($changes['Gender'])) {
            $match = array_values(array_filter(self::GENDERS, fn ($g) => strcasecmp($g, $changes['Gender']) === 0));
            if ($match === []) {
                $errors['Gender'] = 'must be Male, Female or Other.';
                unset($changes['Gender']);
            } else {
                $changes['Gender'] = $match[0];
            }
        }

        $rules = array_intersect_key($role === 'jobseeker' ? ProfileFieldRules::jobSeeker() : ProfileFieldRules::employer(), $changes);
        $validator = Validator::make($changes, $rules);
        foreach ($validator->errors()->messages() as $column => $messages) {
            $errors[$this->labelFor($role, $column)] = $messages[0];
        }

        if (isset($changes['BirthDate']) && !isset($errors['Birth date'])) {
            try {
                $date = Carbon::parse($changes['BirthDate'])->startOfDay();
                if ($date->isFuture()) {
                    $errors['Birth date'] = 'cannot be in the future.';
                } else {
                    $changes['BirthDate'] = $date->toDateString();
                }
            } catch (Throwable) {
                $errors['Birth date'] = 'is not a valid date.';
            }
        }

        if ($errors !== []) {
            $lines = collect($errors)->map(fn ($message, $label) => '- '.$label.': '.$this->sanitize((string) $message))->implode("\n");

            return $this->refusal("I couldn't prepare that profile update:\n".$lines."\nNo change was made.", $errors);
        }

        $before = [];
        foreach ($changes as $column => $value) {
            $current = $this->current($actor, $column);
            if ($current === $value) {
                unset($changes[$column]);
                continue;
            }
            $before[$column] = $current;
        }

        if ($changes === []) {
            return $this->refusal('Those details already match your profile. No change was needed.');
        }

        $request->session()->put($this->key($role, $actor), [
            'role' => $role,
            'account_id' => (int) $actor->getAuthIdentifier(),
            'changes' => $changes,
            'before' => $before,
            'expires_at' => now()->getTimestamp() + self::PENDING_TTL_SECONDS,
        ]);

        $summary = collect($changes)->map(fn ($new, $column) => '- '.$this->labelFor($role, $column).': '
            .$this->display($before[$column]).' → '.$this->display($new))->implode("\n");

        return [
            'confirmation_required' => true,
            'changed' => false,
            'message' => "Please review these changes to your profile:\n".$summary."\n"
                .'No change has been made yet. To apply them within five minutes, reply exactly: '.self::CONFIRMATION_PHRASE.'. Reply CANCEL to discard them.',
        ];
    }

    /**
     * Handles a user message before it reaches the model. Returns a reply when the message was a
     * confirmation/cancellation (or a confirmation with nothing pending), otherwise null.
     */
    public function handleMessage(Request $request, string $role, mixed $actor, string $message): ?string
    {
        if (!isset(self::FIELDS[$role]) || !($actor instanceof JobSeeker || $actor instanceof Employer)) {
            return null;
        }

        $key = $this->key($role, $actor);
        $pending = $request->session()->get($key);
        $isConfirmation = preg_match('/^\s*confirm\s+profile\s+update\s*[.!]?\s*$/i', $message) === 1;

        if (!is_array($pending)) {
            return $isConfirmation ? 'There is no pending profile update to confirm. No change was made.' : null;
        }

        // A pending change is single-use: any reply ends it.
        $request->session()->forget($key);

        if ((int) ($pending['expires_at'] ?? 0) <= now()->getTimestamp()
            || ($pending['role'] ?? null) !== $role
            || (int) ($pending['account_id'] ?? 0) !== (int) $actor->getAuthIdentifier()
            || !$this->ownsSession($role, $actor)) {
            return $isConfirmation ? 'That profile update confirmation has expired. No change was made. Please ask again.' : null;
        }

        if (preg_match('/^\s*(?:cancel|no|never mind)\s*[.!]?\s*$/i', $message) === 1) {
            return 'Profile update cancelled. No changes were made.';
        }

        return $isConfirmation ? $this->apply($role, $actor, $pending) : null;
    }

    /** @param array{changes: array<string, string>, before: array<string, ?string>} $pending */
    private function apply(string $role, JobSeeker|Employer $actor, array $pending): string
    {
        $fields = array_column(self::FIELDS[$role], 0);
        $changes = array_intersect_key((array) ($pending['changes'] ?? []), array_flip($fields));
        $rules = array_intersect_key($role === 'jobseeker' ? ProfileFieldRules::jobSeeker() : ProfileFieldRules::employer(), $changes);

        if ($changes === [] || Validator::make($changes, $rules)->fails()) {
            return 'That profile update could not be validated. No change was made.';
        }

        try {
            $applied = DB::transaction(function () use ($actor, $changes, $pending): bool {
                // Re-read the authenticated user's own row; the ID never comes from the model.
                $model = $actor::query()->whereKey($actor->getAuthIdentifier())->lockForUpdate()->first();
                if ($model === null) {
                    return false;
                }
                foreach ($changes as $column => $value) {
                    if ($this->current($model, $column) !== (($pending['before'] ?? [])[$column] ?? null)) {
                        return false; // changed elsewhere since the summary was shown
                    }
                }
                foreach ($changes as $column => $value) {
                    $model->setAttribute($column, $value); // explicit allowlisted columns only
                }

                return $model->save();
            });
        } catch (Throwable $exception) {
            report($exception);

            return 'I could not save your profile update. Please try again or use your Profile page. No change was confirmed.';
        }

        if (!$applied) {
            return 'Your profile changed after I prepared this update, so nothing was applied. Please ask again to review the latest values.';
        }

        $fresh = $actor::query()->whereKey($actor->getAuthIdentifier())->first();
        foreach ($changes as $column => $value) {
            if ($fresh === null || $this->current($fresh, $column) !== $value) {
                return 'I could not verify that your profile update was saved. Please check your Profile page.';
            }
            $actor->setAttribute($column, $value);
        }

        Log::info('Copilot profile update applied.', [
            'role' => $role,
            'account_id' => (int) $actor->getAuthIdentifier(),
            'fields' => array_keys($changes),
        ]);

        return 'Profile updated: '.collect(array_keys($changes))->map(fn ($c) => $this->labelFor($role, $c))->implode(', ').'.';
    }

    private function ownsSession(string $role, JobSeeker|Employer $actor): bool
    {
        $expected = $role === 'jobseeker' ? JobSeeker::class : Employer::class;
        $authenticated = Auth::guard($role)->user();

        return $actor instanceof $expected
            && $authenticated instanceof $expected
            && (int) $authenticated->getAuthIdentifier() === (int) $actor->getAuthIdentifier();
    }

    private function key(string $role, mixed $actor): string
    {
        return 'career_next_copilot_profile_update_'.$role.'_'.$actor->getAuthIdentifier();
    }

    private function forget(Request $request, string $role, mixed $actor): void
    {
        $request->session()->forget($this->key($role, $actor));
    }

    private function current(mixed $model, string $column): ?string
    {
        $value = $model->getAttribute($column);
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $column === 'BirthDate' ? substr(trim((string) $value), 0, 10) : trim((string) $value);
    }

    private function labelFor(string $role, string $column): string
    {
        foreach (self::FIELDS[$role] ?? [] as [$fieldColumn, $label]) {
            if ($fieldColumn === $column) {
                return $label;
            }
        }

        return $column;
    }

    private function display(?string $value): string
    {
        return $value === null ? '(empty)' : '"'.$this->sanitize($value).'"';
    }

    private function sanitize(string $value): string
    {
        return mb_substr(trim(strip_tags($value)), 0, 200);
    }

    private function safeKey(mixed $key): string
    {
        $key = is_string($key) ? preg_replace('/[^A-Za-z0-9_ -]/', '', $key) : '';

        return $key === '' ? 'unknown field' : mb_substr($key, 0, 40);
    }

    /** @return array{confirmation_required: false, changed: false, message: string, errors?: array<string, string>} */
    private function refusal(string $message, array $errors = []): array
    {
        return ['confirmation_required' => false, 'changed' => false, 'message' => $message] + ($errors === [] ? [] : ['errors' => $errors]);
    }
}

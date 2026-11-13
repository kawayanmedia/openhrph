<?php

namespace App\Livewire\Admin\Assistant;

use App\Ai\Agents\HrAssistant;
use App\Enums\Assistant\RequestType;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\Assistant\AttendanceHours;
use App\Services\Assistant\RequestApprovalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\PaginatesConversations;
use Laravel\Ai\Contracts\ResolvesPendingApprovals;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\StoredMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class Chat extends Component
{
    public string $variant = 'page';

    #[Url(as: 'chat', except: null)]
    public ?string $conversation = null;

    public string $draft = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $messages = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $pending = [];

    /**
     * @var array<string, string>
     */
    public array $decisions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $conversations = [];

    public ?string $error = null;

    public bool $showHistory = false;

    public function mount(string $variant = 'page'): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        $this->variant = $variant === 'panel' ? 'panel' : 'page';

        if ($this->conversation !== null && ! $this->ownsConversation($this->conversation)) {
            $this->conversation = null;
        }

        $this->loadConversations();
        $this->loadTranscript();
    }

    /**
     * Send the composer text to the assistant.
     */
    public function send(): void
    {
        $user = $this->authorizeAssistant();

        $message = trim($this->draft);

        if ($message === '') {
            return;
        }

        if (! $this->withinRateLimit($user->getKey())) {
            return;
        }

        if ($this->conversation !== null && ! $this->ownsConversation($this->conversation)) {
            $this->conversation = null;
        }

        $this->error = null;
        $this->draft = '';

        $tenantId = app(TenantContext::class)->id();
        $response = null;

        try {
            $response = $this->agent($user)
                ->continueOrStart($this->conversation, as: $user)
                ->prompt($message);
        } catch (Throwable $exception) {
            report($exception);

            $this->draft = $message;
            $this->error = 'The assistant could not complete that request. Please try again in a moment.';
        }

        $this->restoreTenant($tenantId);

        if ($response !== null) {
            $this->absorb($response);
        }

        $this->loadConversations();
        $this->loadTranscript();
        $this->dispatch('assistant-updated');
    }

    /**
     * Record a decision (approve / reject) for a pending tool call.
     */
    public function decide(string $id, string $action): void
    {
        $this->authorizeAssistant();

        if (! in_array($action, ['approve', 'reject'], true) || ! $this->hasPending($id)) {
            return;
        }

        $this->decisions[$id] = $action;
        $this->error = null;
    }

    /**
     * Quick-fill every undecided approval.
     */
    public function fillDecisions(string $action): void
    {
        $this->authorizeAssistant();

        if (! in_array($action, ['approve', 'reject'], true)) {
            return;
        }

        foreach ($this->pending as $approval) {
            $this->decisions[$approval['id']] = $action;
        }
    }

    /**
     * Resume the paused turn with the collected decisions.
     */
    public function applyDecisions(): void
    {
        $user = $this->authorizeAssistant();

        if ($this->conversation === null || ! $this->ownsConversation($this->conversation)) {
            $this->error = 'This conversation is no longer available.';

            return;
        }

        $ids = array_column($this->pending, 'id');

        if ($ids === []) {
            return;
        }

        $undecided = array_filter($ids, fn (string $id) => ! in_array($this->decisions[$id] ?? null, ['approve', 'reject'], true));

        if ($undecided !== []) {
            $this->error = 'Choose approve or reject for every pending action before continuing.';

            return;
        }

        if (! $this->withinRateLimit($user->getKey())) {
            return;
        }

        $map = [];

        foreach ($ids as $id) {
            $map[$id] = $this->decisions[$id] === 'reject'
                ? Decision::reject('The user declined this action.')
                : Decision::approve();
        }

        $this->error = null;

        $tenantId = app(TenantContext::class)->id();
        $response = null;

        try {
            $response = $this->agent($user)
                ->continue($this->conversation, as: $user)
                ->prompt(Decisions::from($map));
        } catch (Throwable $exception) {
            report($exception);

            $this->error = 'The decision was recorded but the assistant could not respond. Send a follow-up message to continue.';
        }

        $this->restoreTenant($tenantId);

        if ($response !== null) {
            $this->absorb($response);
        }

        $this->loadConversations();
        $this->loadTranscript();
        $this->dispatch('assistant-updated');
    }

    public function newChat(): void
    {
        $this->authorizeAssistant();

        $this->conversation = null;
        $this->messages = [];
        $this->pending = [];
        $this->decisions = [];
        $this->error = null;
        $this->draft = '';
        $this->showHistory = false;
    }

    public function openConversation(string $id): void
    {
        $this->authorizeAssistant();

        if (! $this->ownsConversation($id)) {
            return;
        }

        $this->conversation = $id;
        $this->messages = [];
        $this->pending = [];
        $this->decisions = [];
        $this->error = null;
        $this->showHistory = false;

        $this->loadTranscript();
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
    }

    public function useSuggestion(string $text): void
    {
        $this->draft = $text;
    }

    public function dismissError(): void
    {
        $this->error = null;
    }

    public function render()
    {
        $view = view('livewire.admin.assistant.chat', [
            'providerReady' => $this->providerReady(),
            'suggestions' => $this->suggestions(),
            'periodLabel' => $this->periodLabel(),
        ]);

        return $this->variant === 'page'
            ? $view->title('AI Assistant')
            : $view;
    }

    /**
     * @return array<int, string>
     */
    protected function suggestions(): array
    {
        return [
            'Who is under 80 hours this cut-off?',
            'Show pending leave requests',
            'Approve the pending shift adjustment requests',
            'How many hours has each employee logged this cut-off?',
        ];
    }

    protected function authorizeAssistant(): User
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        return Auth::user();
    }

    protected function agent(User $user): HrAssistant
    {
        return new HrAssistant($user);
    }

    protected function withinRateLimit(int|string $userId): bool
    {
        $key = 'assistant-prompts:'.$userId;
        $max = (int) config('assistant.prompts_per_minute', 20);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->error = 'You have reached the assistant limit. Try again in '.RateLimiter::availableIn($key).' seconds.';

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    /**
     * Tool executions dispatch queued jobs, and JobProcessed forgets the tenant,
     * so restore the request's tenant before reading data again.
     */
    protected function restoreTenant(?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $tenant = app(TenantContext::class);

        if ($tenant->id() !== $tenantId) {
            $tenant->set($tenantId);
        }
    }

    /**
     * The reporting window shown in the header, tolerant of an unresolved tenant.
     */
    protected function periodLabel(): ?string
    {
        try {
            return app(AttendanceHours::class)->period()['label'];
        } catch (Throwable) {
            return null;
        }
    }

    protected function providerReady(): bool
    {
        $provider = config('assistant.provider') ?: config('ai.default');

        if (! is_string($provider)) {
            return true;
        }

        return filled(config('ai.providers.'.$provider.'.key'));
    }

    /**
     * Keep the conversation id and pending approvals from a fresh response.
     */
    protected function absorb(AgentResponse $response): void
    {
        $this->conversation = $response->conversationId ?? $this->conversation;
        $this->decisions = [];
        $this->pending = array_map(
            fn (PendingApproval $approval) => $this->describeApproval($approval),
            $response->pendingApprovals->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeApproval(PendingApproval $approval): array
    {
        $arguments = $approval->arguments;
        $type = is_string($arguments['type'] ?? null) ? RequestType::tryFrom($arguments['type']) : null;
        $ids = is_array($arguments['ids'] ?? null) ? $arguments['ids'] : [];
        $rejecting = str_contains($approval->tool, 'reject');

        $rows = [];
        $missing = [];
        $label = null;

        if ($type !== null && $ids !== []) {
            $description = app(RequestApprovalService::class)->describe($type, $ids);
            $rows = $description['rows'];
            $missing = $description['missing'];
            $label = count($rows) === 1
                ? Str::lcfirst($type->label())
                : $type->pluralLabel();
        }

        return [
            'id' => $approval->id,
            'tool' => $approval->tool,
            'action' => $rejecting ? 'reject' : 'approve',
            'type' => $type?->value,
            'label' => $label,
            'reason' => $approval->reason,
            'note' => $arguments['reason'] ?? $arguments['remarks'] ?? null,
            'rows' => $rows,
            'missing' => $missing,
            'count' => count($rows),
        ];
    }

    protected function hasPending(string $id): bool
    {
        return in_array($id, array_column($this->pending, 'id'), true);
    }

    protected function ownsConversation(string $id): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $store = app(ConversationStore::class);

        if ($store instanceof VerifiesConversationOwnership) {
            return $store->conversationBelongsTo($id, $user->getMorphClass(), $user->getKey());
        }

        return Conversation::query()
            ->whereKey($id)
            ->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey())
            ->exists();
    }

    protected function loadConversations(): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->conversations = [];

            return;
        }

        $this->conversations = Conversation::query()
            ->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->limit((int) config('assistant.recent_conversations', 12))
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (Conversation $conversation) => [
                'id' => $conversation->id,
                'title' => filled($conversation->title) ? $conversation->title : 'Untitled conversation',
                'when' => $conversation->updated_at?->diffForHumans(),
                'active' => $conversation->id === $this->conversation,
            ])
            ->all();
    }

    protected function loadTranscript(): void
    {
        $this->decisions = [];

        if ($this->conversation === null) {
            $this->messages = [];
            $this->pending = [];

            return;
        }

        $store = app(ConversationStore::class);

        $this->messages = $store instanceof PaginatesConversations
            ? collect($store->paginateConversationMessages($this->conversation, perPage: 100)->items())
                ->reverse()
                ->values()
                ->map(fn (StoredMessage $message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'html' => $message->role === 'assistant' ? $this->markdown($message->content) : null,
                    'at' => $message->createdAt?->format('g:i A'),
                    'tools' => $this->toolLabels($message),
                    'failed' => $message->status->value === 'failed',
                ])
                ->filter(fn (array $message) => in_array($message['role'], ['user', 'assistant'], true)
                    && ($message['content'] !== '' || $message['tools'] !== []))
                ->values()
                ->all()
            : [];

        $storedPending = $store instanceof ResolvesPendingApprovals
            ? array_map(
                fn (PendingApproval $approval) => $this->describeApproval($approval),
                $store->pendingApprovalsFor($this->conversation),
            )
            : [];

        if ($storedPending !== []) {
            $this->pending = $storedPending;
        }
    }

    /**
     * Render an assistant reply. Raw HTML from the model is stripped so a reply
     * can never inject markup into the admin panel.
     */
    protected function markdown(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function toolLabels(StoredMessage $message): array
    {
        $labels = [
            'find_employees' => 'Employee lookup',
            'attendance_hours_report' => 'Hours report',
            'pending_requests' => 'Pending requests',
            'request_details' => 'Request details',
            'approve_requests' => 'Approval',
            'reject_requests' => 'Rejection',
        ];

        return collect($message->toolCalls())
            ->pluck('name')
            ->map(fn (string $name) => $labels[$name] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

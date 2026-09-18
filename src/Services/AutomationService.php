<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\AutomationRuleTriggered;
use JeffersonGoncalves\HelpDesk\Models\AutomationRule;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class AutomationService
{
    /**
     * A rule's `conditions` reach the query builder, so this is an allow-list
     * on principle -- see Ticket::assertSortable() for the same reasoning.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = ['last_replied_at', 'created_at', 'updated_at'];

    /** @var list<string> */
    private const ALLOWED_OPERATORS = ['older_than_hours'];

    /** @var list<string> */
    private const ALLOWED_ACTION_TYPES = ['change_status'];

    public function __construct(
        protected TicketService $ticketService,
    ) {}

    /**
     * @return list<array{rule: string, ticket: string, action: string}>
     */
    public function evaluate(bool $dryRun = false): array
    {
        $applied = [];

        /** @var AutomationRule $rule */
        foreach (AutomationRule::query()->active()->get() as $rule) {
            $this->assertValidConditions($rule->conditions);

            foreach ($this->matchingTickets($rule)->get() as $ticket) {
                if ($this->alreadyApplied($rule, $ticket)) {
                    continue;
                }

                foreach ($this->normalizeActions($rule->actions) as $action) {
                    $applied[] = [
                        'rule' => $rule->name,
                        'ticket' => $ticket->reference_number,
                        'action' => $action['type'] ?? 'unknown',
                    ];

                    if (! $dryRun) {
                        $this->applyAction($rule, $ticket, $action);
                    }
                }

                if (! $dryRun) {
                    $this->markApplied($rule, $ticket);
                }
            }

            if (! $dryRun) {
                $rule->update(['last_run_at' => now()]);
            }
        }

        return $applied;
    }

    /**
     * @return Builder<Ticket>
     */
    protected function matchingTickets(AutomationRule $rule): Builder
    {
        $conditions = $rule->conditions;

        $query = Ticket::query();

        if ($rule->department_id !== null) {
            $query->where('department_id', $rule->department_id);
        }

        if (! empty($conditions['status'])) {
            $query->whereIn('status', $conditions['status']);
        }

        $field = $conditions['field'];
        $value = $conditions['value'];

        match ($conditions['operator']) {
            'older_than_hours' => $query->where($field, '<', now()->subHours((int) $value)),
            default => throw new InvalidArgumentException(
                "Automation rule operator [{$conditions['operator']}] is not allowed. Allowed: ".implode(', ', self::ALLOWED_OPERATORS).'.'
            ),
        };

        return $query;
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    protected function assertValidConditions(array $conditions): void
    {
        $field = $conditions['field'] ?? null;
        $operator = $conditions['operator'] ?? null;

        if (! is_string($field) || ! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw new InvalidArgumentException(
                "Automation rule field [{$field}] is not allowed. Allowed: ".implode(', ', self::ALLOWED_FIELDS).'.'
            );
        }

        if (! is_string($operator) || ! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Automation rule operator [{$operator}] is not allowed. Allowed: ".implode(', ', self::ALLOWED_OPERATORS).'.'
            );
        }
    }

    /**
     * A rule's `actions` may be a single action object or a list of them, so
     * every caller works from a list.
     *
     * @param  array<array-key, mixed>  $actions
     * @return list<array<string, mixed>>
     */
    protected function normalizeActions(array $actions): array
    {
        return array_is_list($actions) ? $actions : [$actions];
    }

    /**
     * @param  array<string, mixed>  $action
     */
    protected function applyAction(AutomationRule $rule, Ticket $ticket, array $action): void
    {
        $type = $action['type'] ?? null;

        match ($type) {
            'change_status' => $this->ticketService->changeStatus($ticket, TicketStatus::from($action['value'])),
            default => throw new InvalidArgumentException(
                "Automation action type [{$type}] is not allowed. Allowed: ".implode(', ', self::ALLOWED_ACTION_TYPES).'.'
            ),
        };

        event(new AutomationRuleTriggered($rule, $ticket, $action));
    }

    protected function alreadyApplied(AutomationRule $rule, Ticket $ticket): bool
    {
        return DB::connection($ticket->getConnectionName())
            ->table('help_desk_ticket_automations_applied')
            ->where('ticket_id', $ticket->id)
            ->where('automation_rule_id', $rule->id)
            ->exists();
    }

    protected function markApplied(AutomationRule $rule, Ticket $ticket): void
    {
        DB::connection($ticket->getConnectionName())
            ->table('help_desk_ticket_automations_applied')
            ->insert([
                'ticket_id' => $ticket->id,
                'automation_rule_id' => $rule->id,
                'applied_at' => now(),
            ]);
    }
}

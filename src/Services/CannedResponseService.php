<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Models\CannedResponse;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class CannedResponseService
{
    /**
     * Consumer-registered resolvers for placeholders beyond the core set,
     * keyed by variable name without braces (e.g. "order_number").
     *
     * @var array<string, Closure(Ticket, ?Model): mixed>
     */
    protected static array $customResolvers = [];

    /**
     * Register a resolver for a custom placeholder, typically called from the
     * consuming application's service provider boot(). A name matching a core
     * placeholder (ticket_code, user_name, agent_name, department) is ignored
     * at render time -- the core substitution always wins, so canned response
     * bodies stay predictable across the application regardless of what a
     * package or app registers.
     *
     * @param  Closure(Ticket, ?Model): mixed  $resolver
     */
    public static function resolveVariable(string $name, Closure $resolver): void
    {
        static::$customResolvers[$name] = $resolver;
    }

    /**
     * Clears every registered custom resolver. Intended for test isolation
     * between cases that register one -- not part of normal application flow.
     */
    public static function flushCustomResolvers(): void
    {
        static::$customResolvers = [];
    }

    /**
     * Substitute the core placeholders and any registered custom ones a
     * canned response body may contain. An unrecognized placeholder is left
     * untouched in the output.
     */
    public function render(CannedResponse $response, Ticket $ticket, ?Model $agent = null): string
    {
        return strtr($response->body, $this->variables($ticket, $agent));
    }

    /**
     * @return array<string, string>
     */
    protected function variables(Ticket $ticket, ?Model $agent): array
    {
        $variables = $this->coreVariables($ticket, $agent);

        foreach (static::$customResolvers as $name => $resolver) {
            $placeholder = '{'.$name.'}';

            if (array_key_exists($placeholder, $variables)) {
                continue;
            }

            $variables[$placeholder] = (string) ($resolver($ticket, $agent) ?? '');
        }

        return $variables;
    }

    /**
     * @return array<string, string>
     */
    protected function coreVariables(Ticket $ticket, ?Model $agent): array
    {
        return [
            '{ticket_code}' => (string) $ticket->reference_number,
            '{user_name}' => (string) ($ticket->requester_name ?? ''),
            '{agent_name}' => (string) ($agent?->getAttribute('name') ?? ''),
            '{department}' => (string) $ticket->department->name,
        ];
    }
}

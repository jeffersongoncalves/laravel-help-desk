# Handoff: laravel-help-desk → filament-help-desk

**Data:** 2026-09-18
**Sessão origem:** `laravel-help-desk` (core), branch `master` @ `b3eced8`
**Destino:** `filament-help-desk` — requer sessão própria + autorização explícita antes de qualquer escrita lá (regra de escopo de repositório).

## Status do core

- `master` consolidada e sincronizada com `origin/master`.
- 375 testes Pest (sqlite local; CI cobre sqlite/mysql/pgsql), Pint e PHPStan (`--memory-limit=1G`) sem erros.
- Todos os 13 issues do plano de arquitetura fechados: pré-requisito de validação de transição (#44/#45), Epic 1 (canned response variables, #46/#47), Epic 2 (CSAT, #48/#49), Epic 3 (SLA, #50/#51/#52/#53), Epic 4 (Automação, #54/#55), Epic 5 (Knowledge Base, #56). Mais C1 (#58, pipeline steps).
- PRs #68 (#54+#55) e #69 (#56) mergeados nesta sessão; #69 exigiu merge de `master` na branch antes de fechar (conflito real em `HelpDeskServiceProvider.php` e `tests/TestCase.php` — ambas branches partiram do mesmo master antes de #68 fechar). Resolvido mantendo as duas listas de migrations/singletons lado a lado.

## Mudanças de domínio relevantes para o filament-help-desk

### Status & pipeline (destrava #61)

- `TicketStatus::pipelineSteps()` — `src/Enums/TicketStatus.php:46` — retorna a sequência linear `[Open, InProgress, Resolved, Closed]` pra render de stepper visual.
- `TicketStatus::pipelineStep()` — `src/Enums/TicketStatus.php:56` — mapeia `Pending`/`OnHold` pra `InProgress` (suspensões da linha, não passos dela).
- `allowedTransitions()`/`canTransitionTo()` continuam sendo a fonte de verdade pra transições reais — o pipeline é só apresentação.

### Knowledge Base (destrava #62)

- Migration `create_help_desk_kb_articles_table` — `department_id`/`category_id` nullable, `app_key` isolado no mesmo padrão de `tickets.app_key`, `slug` único, `body` longText, `is_published`, `views_count`, soft deletes.
- Model `KbArticle` — `src/Models/KbArticle.php` — `HasSlug` (fonte `title`), `SoftDeletes`, `UsesHelpDeskConnection`, `booted()` replica o isolamento multi-app do `Ticket`.
- `KnowledgeBaseService::search(string $term, ?int $departmentId = null): Collection` e `::recordView(KbArticle $article): void` — `src/Services/KnowledgeBaseService.php`. `search()` reusa exatamente o escaping de `Ticket::scopeSearch()` (`%`/`_`/`!` tratados como literal), filtra só publicados, escopa por `app_key` quando `help-desk.scope_to_app` ativo.
- **Sem tabela nova pra deflection.** Convenção documentada no README (seção "Knowledge Base" → "Knowledge base deflection"): gravar `metadata.suggested_articles` no `$data` passado pra `TicketService::create()` — zero método novo no core.
- Registrado no `HelpDeskServiceProvider` como singleton (`KnowledgeBaseService::class`).

### Automação (Epic 4, base pra qualquer regra futura no consumidor)

- `AutomationRule` model + tabelas `help_desk_automation_rules` / `help_desk_ticket_automations_applied` (guarda anti-loop).
- `AutomationService::evaluate(bool $dryRun = false): array` — `src/Services/AutomationService.php` — condições via allow-list de campo/operador (nunca interpola JSON em SQL raw), ações `change_status` e `notify` (esta última manda `TicketAutomationTriggeredNotification` pra `assigned_to`/`requester`/`department_operators`).
- Comando `help-desk:run-automations --dry-run`.
- `AutomationRuleTriggered(rule, ticket, action)` disparado por ação aplicada.

### SLA / breach (Epic 3, contexto se o filament for exibir prazos)

- `SlaPolicy`, colunas de SLA em `tickets` (`sla_policy_id`, `first_response_at`, `sla_*_due_at`, `sla_paused_at`, `total_sla_paused_minutes`, `sla_*_breached_at`).
- `SlaService`, `help-desk:check-sla-breaches --dry-run`, eventos `TicketSlaFirstResponseBreached`/`TicketSlaResolutionBreached`.

## Ações imediatas no filament-help-desk

1. `composer update jeffersongoncalves/laravel-help-desk` (ou apontar path/branch local durante o dev) pra puxar o core atualizado.
2. Implementar **#61** (`feat/ticket-status-stepper-view-ticket`) consumindo `TicketStatus::pipelineSteps()`/`pipelineStep()`.
3. Implementar **#62** (`feat/core-knowledge-base-provider`) — conforme a issue já registrada nesse repo (referenciada pela sessão do filament-help-desk como dependente do contrato `KnowledgeBaseProvider` da #73), consumindo `KnowledgeBaseService::search()`/`recordView()` e o model `KbArticle` deste core.

## Observação de processo

Hang intermitente do job `Tests - mysql` no CI deste repo (~3x nesta sessão, runner GitHub Actions, não é bug de código) — se aparecer no filament-help-desk também, `gh run cancel <id> && gh run rerun <id> --failed` resolve.

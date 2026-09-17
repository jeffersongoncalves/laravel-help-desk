<?php

namespace JeffersonGoncalves\HelpDesk\Api\Models;

use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;

class ApiTicketAttachment extends TicketAttachment
{
    use GuardsRelations;

    /**
     * @return array<string, string>
     */
    protected function apiAlternatives(): array
    {
        return [
            'ticket' => 'Keep the ticket you already have, or fetch it with HelpDesk::tickets()->findByUuid($uuid).',
            'comment' => 'Read $attachment->comment_id.',
            'uploadedBy' => 'Use $attachment->uploader_name and $attachment->uploader_email.',
        ];
    }

    /**
     * The file sits on the central application's disk, which the satellite has
     * no credentials for. Returning a URL that 404s for its users would be
     * worse than saying so.
     */
    public function getUrl(): ?string
    {
        throw HelpDeskApiException::noDisk('getUrl()');
    }

    public function getTemporaryUrl(int $minutes = 5): ?string
    {
        throw HelpDeskApiException::noDisk('getTemporaryUrl()');
    }
}

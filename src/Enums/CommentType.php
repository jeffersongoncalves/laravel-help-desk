<?php

namespace JeffersonGoncalves\HelpDesk\Enums;

enum CommentType: string
{
    case Reply = 'reply';
    case Note = 'note';
    case System = 'system';

    public function label(): string
    {
        return __('help-desk::comment_types.'.$this->value);
    }
}

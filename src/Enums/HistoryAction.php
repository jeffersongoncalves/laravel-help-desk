<?php

namespace JeffersonGoncalves\HelpDesk\Enums;

enum HistoryAction: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case DepartmentChanged = 'department_changed';
    case CategoryChanged = 'category_changed';
    case CommentAdded = 'comment_added';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case TitleChanged = 'title_changed';
    case Merged = 'merged';
}

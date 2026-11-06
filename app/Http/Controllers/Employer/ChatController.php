<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\ApplicationChatController;
use App\Models\ApplicationChatMessage;

/**
 * The employer's Messages inbox.
 *
 * Only threads whose EmployerId is this account are reachable, and the applicant
 * side of every thread is fixed by the application, so an employer can never join
 * a conversation about another company's listing.
 */
class ChatController extends ApplicationChatController
{
    protected function role(): string
    {
        return ApplicationChatMessage::ROLE_EMPLOYER;
    }
}

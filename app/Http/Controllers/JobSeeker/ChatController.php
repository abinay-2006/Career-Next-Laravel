<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\ApplicationChatController;
use App\Models\ApplicationChatMessage;

/**
 * The job seeker's Messages inbox.
 *
 * Threads are resolved through JobSeekId, so another applicant's conversation is not
 * found rather than merely forbidden, and the employer on the other side is always
 * the one who posted the job this application belongs to.
 */
class ChatController extends ApplicationChatController
{
    protected function role(): string
    {
        return ApplicationChatMessage::ROLE_JOBSEEKER;
    }
}

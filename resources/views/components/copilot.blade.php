@props(['role'])
<div class="cn-copilot" data-copilot data-role="{{ $role }}" data-message-url="{{ route($role.'.copilot.message') }}" data-reset-url="{{ route($role.'.copilot.reset') }}" data-csrf="{{ csrf_token() }}">
    <button class="cn-copilot-launch" type="button" aria-label="Open Career Next Copilot" aria-expanded="false"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><span>Career Copilot</span></button>
    <section class="cn-copilot-panel" aria-label="Career Next AI Copilot" aria-hidden="true">
        <header class="cn-copilot-head"><div><strong>Career Next Copilot</strong><small style="color: #ffff;">Your {{ $role }} assistant</small></div><div class="cn-copilot-actions"><button type="button" data-copilot-new aria-label="Start new conversation" title="New conversation"><i class="fas fa-rotate-right"></i></button><button type="button" data-copilot-close aria-label="Close Copilot"><i class="fas fa-xmark"></i></button></div></header>
        <div class="cn-copilot-messages" role="log" aria-live="polite" aria-relevant="additions">
            <div class="cn-copilot-message assistant">{{ $role === 'jobseeker' ? "Hello! I'm your Career Next Copilot. I can help you discover jobs, improve your resume and prepare for interviews." : ($role === 'employer' ? "Hello! I'm your Career Next Copilot. I can help you review your candidates and improve your recruitment process." : "Hello! I'm your Career Next Copilot. I can help you understand platform statistics and administrative activity.") }}</div>
            <div class="cn-copilot-suggestions">
                @if($role === 'jobseeker')
                    <button type="button">Find jobs that fit my skills</button><button type="button">How can I improve my resume?</button><button type="button">Help me prepare for an interview</button>
                @elseif($role === 'employer')
                    <button type="button">Summarize applications for my jobs</button><button type="button">Improve one of my job descriptions</button><button type="button">Suggest interview questions</button>
                @else
                    <button type="button">Summarize platform statistics</button><button type="button">How many documents need review?</button><button type="button">What should I review manually?</button>
                @endif
            </div>
        </div>
        <form class="cn-copilot-form"><textarea rows="2" maxlength="2000" placeholder="Ask Career Next Copilot…" aria-label="Message to Copilot" required></textarea><button type="submit" aria-label="Send message"><i class="fas fa-arrow-up"></i></button></form>
        <div class="cn-copilot-foot">AI suggestions can be imperfect. Verify important details.</div>
    </section>
</div>

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    public function test_copilot_javascript_parses_and_layouts_load_it_once(): void
    {
        $node = (new ExecutableFinder())->find('node');
        if ($node === null) $this->markTestSkipped('Node.js is required for the JavaScript syntax check.');
        $result = Process::run([$node, '--check', public_path('assets/js/copilot.js')]);
        $this->assertTrue($result->successful(), $result->errorOutput());
        foreach (['admin', 'employer', 'jobseeker'] as $layout) {
            $blade = (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertSame(1, substr_count($blade, "assets/js/copilot.js') }}?v="), $layout);
            $this->assertSame(1, substr_count($blade, '<x-copilot role="'.$layout.'"'), $layout);
        }
    }

    public static function frontendResponseScenarios(): array
    {
        $busy = 'The AI assistant is temporarily busy. Please try again in a moment.';
        return [
            'html error' => ['html500', $busy, true], 'network failure' => ['network', $busy, true],
            'aborted request' => ['aborted', $busy, true], 'safe server error' => ['json503', $busy, true],
            'provider rate limit' => ['json503rateLimit', 'The AI assistant is receiving more requests than its AI service currently allows. Please try again in about 48 minutes.', true],
            'bad gateway' => ['json502', 'The AI assistant could not process that request. Please try again later.', true],
            'session expired' => ['json401', 'Your session has ended. Please sign in again to use the AI assistant.', true],
            'successful response' => ['success', 'Hello there', false],
            'double submit' => ['doubleSubmit', 'Only once', false],
        ];
    }

    #[DataProvider('frontendResponseScenarios')]
    public function test_frontend_handles_server_response_and_restores_form(string $scenario, string $expected, bool $error): void
    {
        $node = (new ExecutableFinder())->find('node');
        if ($node === null) $this->markTestSkipped('Node.js is required for frontend behavior tests.');
        $result = Process::run([$node, base_path('tests/js/copilot-frontend.cjs'), public_path('assets/js/copilot.js'), $scenario]);
        $this->assertTrue($result->successful(), $result->errorOutput().$result->output());
        $state = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('crash', $state);
        $this->assertSame(1, $state['fetchCalls']);
        $this->assertTrue($state['duringRequest']['buttonDisabled']);
        $this->assertFalse($state['buttonDisabled']);
        $this->assertCount(2, $state['items']);
        $this->assertSame($expected, $state['items'][1]['text']);
        $this->assertSame($error, str_contains($state['items'][1]['className'], 'error'));
    }
}

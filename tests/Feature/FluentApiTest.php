<?php

namespace MartinRo\Herdr\Tests\Feature;

use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\Facades\Herdr;
use MartinRo\Herdr\Testing\FakeResponse;
use MartinRo\Herdr\Tests\TestCase;

class FluentApiTest extends TestCase
{
    public function test_it_lists_workspaces_as_live_objects(): void
    {
        Herdr::fake([
            'workspace.list' => [
                'workspaces' => [
                    ['workspace_id' => 'w1', 'label' => 'api', 'focused' => true],
                    ['workspace_id' => 'w2', 'label' => 'web'],
                ],
            ],
        ]);

        $workspaces = Herdr::workspaces()->all();

        $this->assertCount(2, $workspaces);
        $this->assertSame('api', $workspaces->first()->label());
        $this->assertSame('w1', Herdr::workspaces()->focused()->id());
    }

    public function test_running_a_command_sends_text_then_enter_scoped_to_the_pane(): void
    {
        Herdr::fake();

        Herdr::pane('w1:p1')->run('php artisan test');

        Herdr::assertSentCount(2);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.send_text'
            && $params['pane_id'] === 'w1:p1'
            && $params['text'] === 'php artisan test');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.send_keys'
            && $params['pane_id'] === 'w1:p1'
            && $params['keys'] === ['enter']);
    }

    public function test_pane_without_id_targets_the_focused_pane(): void
    {
        Herdr::fake();

        Herdr::pane()->sendText('ls');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.send_text'
            && ! array_key_exists('pane_id', $params)
            && $params['text'] === 'ls');
    }

    public function test_split_targets_the_pane_via_target_pane_id(): void
    {
        Herdr::fake([
            'pane.split' => ['type' => 'pane_info', 'pane' => ['pane_id' => 'w1:p2']],
        ]);

        $pane = Herdr::pane('w1:p1')->split('right', ratio: 0.33, options: ['env' => ['HERDR_ROLE' => 'tests']]);

        $this->assertInstanceOf(Pane::class, $pane);
        $this->assertSame('w1:p2', $pane->id());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.split'
            && $params['target_pane_id'] === 'w1:p1'
            && ! array_key_exists('pane_id', $params)
            && $params['direction'] === 'right'
            && $params['ratio'] === 0.33
            && $params['env'] === ['HERDR_ROLE' => 'tests']);
    }

    public function test_read_returns_a_permissive_result(): void
    {
        // pane.read nests its payload under a "read" key; since 0.7.5 the
        // payload carries text only (no lines array).
        Herdr::fake([
            'pane.read' => ['type' => 'pane_read', 'read' => ['text' => "one\ntwo", 'truncated' => false]],
        ]);

        $output = Herdr::pane('w1:p2')->read(lines: 50, source: 'recent');

        $this->assertSame(['one', 'two'], $output->lines());
        $this->assertSame("one\ntwo", $output->text());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.read'
            && $params['pane_id'] === 'w1:p2'
            && $params['lines'] === 50
            && $params['source'] === 'recent'
            && $params['format'] === 'text'
            && ! array_key_exists('strip_ansi', $params));
    }

    public function test_wait_for_output_sends_a_structured_substring_match(): void
    {
        Herdr::fake([
            'pane.wait_for_output' => [
                'type' => 'output_matched',
                'pane_id' => 'w1:p1',
                'matched_line' => 'Tests: 3 passed',
                'read' => ['text' => "...\nTests: 3 passed"],
                'revision' => 7,
            ],
        ]);

        $result = Herdr::pane('w1:p1')->waitForOutput('Tests:', ['timeout_ms' => 5000]);

        $this->assertSame('Tests: 3 passed', $result->matched_line);
        $this->assertSame("...\nTests: 3 passed", $result->text());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.wait_for_output'
            && $params['pane_id'] === 'w1:p1'
            && $params['source'] === 'recent'
            && $params['match'] === ['type' => 'substring', 'value' => 'Tests:']
            && $params['timeout_ms'] === 5000);
    }

    public function test_wait_for_pattern_sends_a_regex_match(): void
    {
        Herdr::fake();

        Herdr::pane('w1:p1')->waitForPattern('Tests:\s+\d+ passed', ['source' => 'visible']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.wait_for_output'
            && $params['source'] === 'visible'
            && $params['match'] === ['type' => 'regex', 'value' => 'Tests:\s+\d+ passed']);
    }

    public function test_zoom_sends_a_mode(): void
    {
        Herdr::fake();

        Herdr::pane('w1:p1')->zoom();
        Herdr::pane('w1:p1')->zoom('on');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.zoom'
            && $params['mode'] === 'toggle');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.zoom'
            && $params['mode'] === 'on');
    }

    public function test_swap_with_uses_explicit_pane_id_fields(): void
    {
        Herdr::fake();

        Herdr::pane('w1:p1')->swapWith('w1:p2');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.swap'
            && $params['source_pane_id'] === 'w1:p1'
            && $params['target_pane_id'] === 'w1:p2');
    }

    public function test_move_builds_destination_envelopes(): void
    {
        Herdr::fake();

        Herdr::pane('w1:p1')->moveToTab('w1:t2', 'down', ['ratio' => 0.5]);
        Herdr::pane('w1:p1')->moveToNewTab('w2', 'scratch');
        Herdr::pane('w1:p1')->moveToNewWorkspace('lab', focus: false);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.move'
            && $params['pane_id'] === 'w1:p1'
            && $params['destination'] === ['type' => 'tab', 'tab_id' => 'w1:t2', 'split' => 'down', 'ratio' => 0.5]);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.move'
            && $params['destination'] === ['type' => 'new_tab', 'workspace_id' => 'w2', 'label' => 'scratch']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.move'
            && $params['destination'] === ['type' => 'new_workspace', 'label' => 'lab']
            && $params['focus'] === false);
    }

    public function test_agent_start_targets_an_existing_pane(): void
    {
        Herdr::fake([
            'agent.start' => [
                'type' => 'agent_started',
                'agent' => ['name' => 'reviewer', 'agent' => 'claude', 'pane_id' => 'w1:p2', 'agent_status' => 'working'],
                'argv' => ['claude', '--continue'],
            ],
        ]);

        $agent = Herdr::agents()->start('claude', 'reviewer', 'w1:p2', ['args' => ['--continue']]);

        $this->assertSame('reviewer', $agent->name());
        $this->assertSame('claude', $agent->kind());
        $this->assertSame(['claude', '--continue'], $agent->argv);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'agent.start'
            && $params['kind'] === 'claude'
            && $params['name'] === 'reviewer'
            && $params['pane_id'] === 'w1:p2'
            && $params['args'] === ['--continue']);
    }

    public function test_agent_target_resolution_prefers_name_over_pane_id(): void
    {
        Herdr::fake();

        Herdr::agents()->all(); // no-op to init fake

        $agent = new \MartinRo\Herdr\Data\Agent([
            'name' => 'reviewer',
            'pane_id' => 'w1:p2',
            'terminal_id' => 'term_abc',
            'agent' => 'claude',
        ]);

        $this->assertSame('reviewer', $agent->target());

        $unnamed = new \MartinRo\Herdr\Data\Agent([
            'pane_id' => 'w1:p2',
            'terminal_id' => 'term_abc',
            'agent' => 'claude',
        ]);

        $this->assertSame('w1:p2', $unnamed->target());
    }

    public function test_agent_prompt_builds_wait_options(): void
    {
        Herdr::fake([
            'agent.prompt' => [
                'type' => 'agent_prompted',
                'agent' => ['name' => 'reviewer', 'pane_id' => 'w1:p2', 'agent_status' => 'idle'],
            ],
        ]);

        $agent = Herdr::agent('reviewer');

        $agent->prompt('fix the failing test', until: 'idle', timeoutMs: 60000);
        $this->assertSame('idle', $agent->status());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'agent.prompt'
            && $params['target'] === 'reviewer'
            && $params['text'] === 'fix the failing test'
            && $params['wait'] === ['until' => ['idle'], 'timeout_ms' => 60000]);

        $agent->prompt('carry on');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'agent.prompt'
            && $params['text'] === 'carry on'
            && ! array_key_exists('wait', $params));
    }

    public function test_agent_send_keys_and_wait(): void
    {
        Herdr::fake([
            'agent.wait' => [
                'type' => 'agent_info',
                'agent' => ['name' => 'reviewer', 'pane_id' => 'w1:p2', 'agent_status' => 'idle'],
            ],
        ]);

        $agent = Herdr::agent('reviewer');

        $agent->sendKeys('esc');
        $agent->wait('idle', 30000);

        $this->assertSame('idle', $agent->status());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'agent.send_keys'
            && $params['target'] === 'reviewer'
            && $params['keys'] === ['esc']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'agent.wait'
            && $params['target'] === 'reviewer'
            && $params['until'] === ['idle']
            && $params['timeout_ms'] === 30000);
    }

    public function test_plugin_calls_use_plugin_id_and_action_id(): void
    {
        Herdr::fake();

        Herdr::plugins()->enable('picker');
        Herdr::plugins()->invoke('jump-back', 'picker', ['workspace_id' => 'w1']);
        Herdr::plugins()->logs('picker', 10);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'plugin.enable'
            && $params['plugin_id'] === 'picker');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'plugin.action.invoke'
            && $params['action_id'] === 'jump-back'
            && $params['plugin_id'] === 'picker'
            && $params['context'] === ['workspace_id' => 'w1']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'plugin.log.list'
            && $params['plugin_id'] === 'picker'
            && $params['limit'] === 10);
    }

    public function test_notify_builds_the_expected_params(): void
    {
        Herdr::fake();

        Herdr::notify('build failed', 'api workspace', ['position' => 'top-left', 'sound' => 'request']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'notification.show'
            && $params['title'] === 'build failed'
            && $params['body'] === 'api workspace'
            && $params['position'] === 'top-left'
            && $params['sound'] === 'request');
    }

    public function test_errors_bubble_up_as_request_exceptions(): void
    {
        // Herdr namespaces not-found errors per resource, e.g. "pane_not_found".
        Herdr::fake([
            'pane.get' => FakeResponse::error('pane_not_found', 'pane w9:p9 not found'),
        ]);

        $this->assertNull(Herdr::panes()->find('w9:p9'));
    }

    public function test_event_stream_reads_pushed_envelopes(): void
    {
        Herdr::fake([
            'events.subscribe' => ['type' => 'subscription_started'],
        ]);

        Herdr::events()->subscribe([
            ['type' => 'pane.agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'blocked'],
        ]);

        // Since 0.7.5 pushed lines wrap the payload in {event, data}.
        Herdr::pushEvent([
            'event' => 'pane_agent_status_changed',
            'data' => [
                'type' => 'pane_agent_status_changed',
                'pane_id' => 'w1:p1',
                'workspace_id' => 'w1',
                'agent_status' => 'blocked',
                'agent' => 'claude',
            ],
        ]);

        $event = Herdr::events()->next();

        $this->assertNotNull($event);
        $this->assertSame('pane_agent_status_changed', $event->name());
        $this->assertTrue($event->is('pane_*'));
        $this->assertTrue($event->is('pane.agent_status_changed'));
        $this->assertSame('w1:p1', $event->paneId());
        $this->assertSame('blocked', $event->data()->agent_status);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'events.subscribe'
            && $params['subscriptions'][0]['type'] === 'pane.agent_status_changed'
            && $params['subscriptions'][0]['agent_status'] === 'blocked');
    }

    public function test_event_hydrates_embedded_pane_info(): void
    {
        Herdr::fake(['events.subscribe' => ['type' => 'subscription_started']]);

        Herdr::events()->subscribe(['pane.created']);

        Herdr::pushEvent([
            'event' => 'pane_created',
            'data' => [
                'type' => 'pane_created',
                'pane' => ['pane_id' => 'w1:p3', 'workspace_id' => 'w1', 'tab_id' => 'w1:t1', 'cwd' => '/tmp'],
            ],
        ]);

        $event = Herdr::events()->next();

        $pane = $event->pane();

        $this->assertInstanceOf(Pane::class, $pane);
        $this->assertSame('w1:p3', $pane->id());
        $this->assertSame('/tmp', $pane->cwd());
        $this->assertSame('w1:p3', $event->paneId());
    }

    public function test_events_wait_for_matches_server_side(): void
    {
        Herdr::fake([
            'events.wait' => [
                'type' => 'wait_matched',
                'event' => [
                    'event' => 'pane_agent_status_changed',
                    'data' => ['type' => 'pane_agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'idle'],
                ],
            ],
        ]);

        $event = Herdr::events()->waitFor(
            'pane_agent_status_changed',
            ['pane_id' => 'w1:p1', 'agent_status' => 'idle'],
            timeoutMs: 5000,
        );

        $this->assertSame('pane_agent_status_changed', $event->name());
        $this->assertSame('w1:p1', $event->paneId());
        $this->assertSame('idle', $event->data()->agent_status);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'events.wait'
            && $params['match_event'] === ['event' => 'pane_agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'idle']
            && $params['timeout_ms'] === 5000);
    }

    public function test_session_snapshot_unwraps_the_snapshot(): void
    {
        Herdr::fake([
            'session.snapshot' => [
                'type' => 'session_snapshot',
                'snapshot' => [
                    'protocol' => 17,
                    'version' => '0.7.5',
                    'workspaces' => [['workspace_id' => 'w1']],
                    'panes' => [],
                ],
            ],
        ]);

        $snapshot = Herdr::snapshot();

        $this->assertSame(17, $snapshot->protocol);
        $this->assertSame('w1', $snapshot->get('workspaces.0.workspace_id'));
    }

    public function test_layout_apply_and_integrations_send_expected_shapes(): void
    {
        Herdr::fake();

        Herdr::layouts()->apply(
            ['type' => 'split', 'direction' => 'right', 'ratio' => 0.5,
                'first' => ['type' => 'pane'], 'second' => ['type' => 'pane']],
            ['workspace_id' => 'w1', 'tab_label' => 'dev'],
        );

        Herdr::layouts()->setSplitRatio([false], 0.3, ['tab_id' => 'w1:t1']);

        Herdr::integrations()->install('claude');

        Herdr::closePopup();

        Herdr::assertSent(fn (string $method, array $params) => $method === 'layout.apply'
            && $params['root']['type'] === 'split'
            && $params['workspace_id'] === 'w1'
            && $params['tab_label'] === 'dev');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'layout.set_split_ratio'
            && $params['path'] === [false]
            && $params['ratio'] === 0.3
            && $params['tab_id'] === 'w1:t1');

        Herdr::assertSent(fn (string $method, array $params) => $method === 'integration.install'
            && $params['target'] === 'claude');

        Herdr::assertSent(fn (string $method) => $method === 'popup.close');
    }

    public function test_workspace_and_tab_move(): void
    {
        Herdr::fake();

        Herdr::workspace('w2')->move(0);
        Herdr::tab('w1:t2')->move(1);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'workspace.move'
            && $params['workspace_id'] === 'w2'
            && $params['insert_index'] === 0);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'tab.move'
            && $params['tab_id'] === 'w1:t2'
            && $params['insert_index'] === 1);
    }
}

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

    public function test_split_returns_the_new_pane(): void
    {
        Herdr::fake([
            'pane.split' => ['type' => 'pane_info', 'pane' => ['pane_id' => 'w1:p2']],
        ]);

        $pane = Herdr::pane('w1:p1')->split('right', ratio: 0.33, options: ['env' => ['HERDR_ROLE' => 'tests']]);

        $this->assertInstanceOf(Pane::class, $pane);
        $this->assertSame('w1:p2', $pane->id());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.split'
            && $params['pane_id'] === 'w1:p1'
            && $params['direction'] === 'right'
            && $params['ratio'] === 0.33
            && $params['env'] === ['HERDR_ROLE' => 'tests']);
    }

    public function test_read_returns_a_permissive_result(): void
    {
        // pane.read nests its payload under a "read" key, as the real server does.
        Herdr::fake([
            'pane.read' => ['type' => 'pane_read', 'read' => ['text' => "one\ntwo", 'lines' => ['one', 'two']]],
        ]);

        $output = Herdr::pane('w1:p2')->read(lines: 50, source: 'recent');

        $this->assertSame(['one', 'two'], $output->lines());
        $this->assertSame("one\ntwo", $output->text());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'pane.read'
            && $params['pane_id'] === 'w1:p2'
            && $params['lines'] === 50
            && $params['source'] === 'recent'
            && $params['format'] === 'text');
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

    public function test_event_stream_reads_pushed_events(): void
    {
        Herdr::fake([
            'events.subscribe' => ['type' => 'subscribed'],
        ]);

        Herdr::events()->subscribe([
            ['type' => 'pane.agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'blocked'],
        ]);

        Herdr::pushEvent(['type' => 'pane.agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'blocked']);

        $event = Herdr::events()->next();

        $this->assertNotNull($event);
        $this->assertTrue($event->is('pane.*'));
        $this->assertSame('w1:p1', $event->paneId());
        $this->assertSame('blocked', $event->agent_status);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'events.subscribe'
            && $params['subscriptions'][0]['agent_status'] === 'blocked');
    }
}

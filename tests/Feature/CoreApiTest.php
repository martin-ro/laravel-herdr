<?php

namespace MartinRo\Herdr\Tests\Feature;

use MartinRo\Herdr\Data\Terminal;
use MartinRo\Herdr\Exceptions\HerdrException;
use MartinRo\Herdr\Exceptions\RequestException;
use MartinRo\Herdr\Facades\Herdr;
use MartinRo\Herdr\Testing\FakeResponse;
use MartinRo\Herdr\Tests\TestCase;

class CoreApiTest extends TestCase
{
    public function test_it_lists_terminals_with_their_reported_status(): void
    {
        Herdr::fake([
            'terminal.list' => [
                'hidden_finished' => 0,
                'machines' => [],
                'terminals' => [
                    [
                        'address' => 't2s',
                        'state' => 'live',
                        'cwd' => '/home/me',
                        'turn' => 16,
                        'labels' => ['herdr.space' => 'sp1', 'where' => 'api / 1'],
                        'status' => ['app' => 'claude-code', 'state' => 'done'],
                        'foreground' => ['pid' => 7, 'name' => 'claude', 'argv' => ['claude'], 'cwd' => '/srv/api'],
                    ],
                    ['address' => 't1', 'state' => 'live', 'cwd' => '/home/me', 'labels' => [], 'status' => null],
                ],
            ],
        ]);

        [$agent, $shell] = Herdr::core()->terminals()->all()->all();

        $this->assertInstanceOf(Terminal::class, $agent);
        $this->assertSame('t2s', $agent->address());
        $this->assertTrue($agent->isLive());
        $this->assertSame('done', $agent->status());
        $this->assertSame('claude-code', $agent->app());
        $this->assertSame(16, $agent->turn());
        $this->assertSame('api / 1', $agent->label('where'));
        $this->assertSame('/srv/api', $agent->cwd());

        $this->assertNull($shell->status());
        $this->assertNull($shell->app());
        $this->assertSame(0, $shell->turn());
        $this->assertNull($shell->label('where'));
        $this->assertSame('/home/me', $shell->cwd());

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.list' && $params === []);
    }

    public function test_listing_passes_labels_and_finished_terminals_only_when_asked(): void
    {
        Herdr::fake();

        Herdr::core()->terminals()->all(['herdr.space' => 'sp1'], finished: true);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.list'
            && $params === ['labels' => ['herdr.space' => 'sp1'], 'all' => true]);
    }

    public function test_find_returns_null_for_an_unknown_terminal(): void
    {
        Herdr::fake([
            'terminal.get' => fn (array $params) => $params['terminal'] === 't7'
                ? FakeResponse::ok(['address' => 't7', 'state' => 'finished'])
                : FakeResponse::error('not_found', 'no terminal'),
        ]);

        $this->assertSame('t7', Herdr::core()->terminals()->find('t7')?->address());
        $this->assertFalse(Herdr::core()->terminals()->find('t7')?->isLive());
        $this->assertNull(Herdr::core()->terminals()->find('t9'));
    }

    public function test_find_rethrows_other_errors(): void
    {
        Herdr::fake(['terminal.get' => FakeResponse::error('unavailable', 'lost, waiting for its new shell')]);

        $this->expectException(RequestException::class);

        Herdr::core()->terminals()->find('t7');
    }

    public function test_reading_defaults_to_the_screen_only(): void
    {
        Herdr::fake(['terminal.read' => ['text' => "$ ls\nREADME.md", 'complete' => true, 'how' => 'screen']]);

        $this->assertSame("$ ls\nREADME.md", Herdr::core()->terminal('t7')->read()->text());

        Herdr::core()->terminal('t7')->read(lines: 100);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.read'
            && $params === ['terminal' => 't7', 'lines' => 0]);
        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.read'
            && $params === ['terminal' => 't7', 'lines' => 100]);
    }

    public function test_sending_text_forces_only_when_asked(): void
    {
        Herdr::fake(['terminal.send' => ['turn' => 3, 'status' => 'idle', 'started' => true]]);

        $result = Herdr::core()->terminal('t7')->send('Fix the test');
        Herdr::core()->terminal('t7')->send('yes', force: true);

        $this->assertSame(3, $result->get('turn'));

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.send'
            && $params === ['terminal' => 't7', 'text' => 'Fix the test']);
        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.send'
            && $params === ['terminal' => 't7', 'text' => 'yes', 'force' => true]);
    }

    public function test_keys_takes_one_key_or_a_list(): void
    {
        Herdr::fake();

        Herdr::core()->terminal('t7')->keys('shift+tab');
        Herdr::core()->terminal('t7')->keys(['down', 'enter']);

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.keys'
            && $params === ['terminal' => 't7', 'keys' => ['shift+tab']]);
        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.keys'
            && $params === ['terminal' => 't7', 'keys' => ['down', 'enter']]);
    }

    public function test_kill_fills_in_the_finished_terminal(): void
    {
        Herdr::fake(['terminal.kill' => ['address' => 't7', 'state' => 'finished', 'reason' => 'killed']]);

        $terminal = Herdr::core()->terminal('t7')->kill();

        $this->assertFalse($terminal->isLive());
        $this->assertSame('killed', $terminal->get('reason'));

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.kill' && $params === ['terminal' => 't7']);
    }

    public function test_run_starts_a_program_with_its_options(): void
    {
        Herdr::fake(['terminal.run' => ['address' => 't3a', 'state' => 'live', 'labels' => ['herdr.space' => 'sp1']]]);

        $terminal = Herdr::core()->terminals()->run(['claude', 'Fix the test'], [
            'cwd' => '/srv/api',
            'labels' => ['herdr.space' => 'sp1'],
            'keep' => true,
        ]);

        $this->assertSame('t3a', $terminal->address());
        $this->assertSame('sp1', $terminal->label('herdr.space'));

        Herdr::assertSent(fn (string $method, array $params) => $method === 'terminal.run' && $params === [
            'argv' => ['claude', 'Fix the test'],
            'cwd' => '/srv/api',
            'labels' => ['herdr.space' => 'sp1'],
            'keep' => true,
        ]);
    }

    public function test_it_reads_documents_and_the_server_status(): void
    {
        Herdr::fake([
            'doc.get' => ['value' => ['spaces' => [['id' => 'sp1', 'name' => 'api']]], 'version' => 4],
            'server.status' => ['version' => '0.1.16', 'live' => 2, 'finished' => 1],
        ]);

        $doc = Herdr::core()->doc('herdr-app');

        $this->assertSame(4, $doc->get('version'));
        $this->assertSame('api', $doc->get('value.spaces.0.name'));
        $this->assertSame('0.1.16', Herdr::core()->status()->get('version'));

        Herdr::assertSent(fn (string $method, array $params) => $method === 'doc.get' && $params === ['name' => 'herdr-app']);
    }

    public function test_a_terminal_without_an_address_cannot_act(): void
    {
        Herdr::fake();

        $this->expectException(HerdrException::class);

        Herdr::core()->terminal('')->kill();
    }
}

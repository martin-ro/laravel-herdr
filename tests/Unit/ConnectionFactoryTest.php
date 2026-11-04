<?php

namespace MartinRo\Herdr\Tests\Unit;

use MartinRo\Herdr\ConnectionFactory;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    private string|false $runtimeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeDirectory = getenv('XDG_RUNTIME_DIR');
    }

    protected function tearDown(): void
    {
        putenv($this->runtimeDirectory === false ? 'XDG_RUNTIME_DIR' : 'XDG_RUNTIME_DIR='.$this->runtimeDirectory);

        parent::tearDown();
    }

    public function test_a_configured_core_socket_wins(): void
    {
        putenv('XDG_RUNTIME_DIR=/run/user/1000');

        $factory = new ConnectionFactory(['core_socket_path' => '/tmp/hc.sock', 'core_session' => 'work']);

        $this->assertSame('/tmp/hc.sock', $factory->resolveCorePath());
    }

    public function test_the_core_socket_lives_in_the_runtime_directory(): void
    {
        putenv('XDG_RUNTIME_DIR=/run/user/1000/');

        $this->assertSame('/run/user/1000/herdr-core/default.sock', (new ConnectionFactory)->resolveCorePath());
        $this->assertSame(
            '/run/user/1000/herdr-core/work.sock',
            (new ConnectionFactory(['core_session' => 'work']))->resolveCorePath(),
        );
    }
}

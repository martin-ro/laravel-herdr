<?php

namespace MartinRo\Herdr\Facades;

use MartinRo\Herdr\Data\Agent;
use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\Data\Result;
use MartinRo\Herdr\Data\Tab;
use MartinRo\Herdr\Data\Workspace;
use MartinRo\Herdr\HerdrManager;
use MartinRo\Herdr\Resources\Agents;
use MartinRo\Herdr\Resources\EventStream;
use MartinRo\Herdr\Resources\Integrations;
use MartinRo\Herdr\Resources\Layouts;
use MartinRo\Herdr\Resources\Panes;
use MartinRo\Herdr\Resources\Plugins;
use MartinRo\Herdr\Resources\Server;
use MartinRo\Herdr\Resources\Tabs;
use MartinRo\Herdr\Resources\Workspaces;
use MartinRo\Herdr\Resources\Worktrees;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \MartinRo\Herdr\Connection connection()
 * @method static HerdrManager session(string $name)
 * @method static array call(string $method, array $params = [])
 * @method static Result result(string $method, array $params = [])
 * @method static Result ping()
 * @method static Result snapshot()
 * @method static Result closePopup()
 * @method static Workspaces workspaces()
 * @method static Workspace workspace(?string $id = null)
 * @method static Tabs tabs()
 * @method static Tab tab(?string $id = null)
 * @method static Panes panes()
 * @method static Pane pane(?string $id = null)
 * @method static Agents agents()
 * @method static Agent agent(?string $id = null)
 * @method static Worktrees worktrees()
 * @method static Plugins plugins()
 * @method static Layouts layouts()
 * @method static Integrations integrations()
 * @method static EventStream events()
 * @method static Server server()
 * @method static Result notify(string $title, ?string $body = null, array $options = [])
 * @method static Result windowTitle(?string $title)
 * @method static HerdrManager fake(array $stubs = [])
 * @method static HerdrManager pushEvent(array $event)
 * @method static array recorded(?callable $filter = null)
 * @method static HerdrManager assertSent(string|callable $callback)
 * @method static HerdrManager assertNotSent(string|callable $callback)
 * @method static HerdrManager assertSentCount(int $count)
 * @method static HerdrManager assertNothingSent()
 *
 * @see HerdrManager
 */
class Herdr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'herdr';
    }
}

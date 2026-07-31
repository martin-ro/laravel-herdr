# Laravel Herdr

A fluent, Laravel-style client for the [Herdr](https://herdr.dev) terminal
workspace **socket API**. It gives you an Eloquent-flavoured way to drive
workspaces, tabs, panes, agents and worktrees, and to react to Herdr events —
all from PHP.

> Herdr's socket API is **newline-delimited JSON over a local socket** (a Unix
> domain socket on Linux/macOS, a named pipe on Windows). There is no network
> access and no authentication — your code talks to the Herdr instance running
> on the same machine. This package speaks that protocol for you.

```php
use MartinRo\Herdr\Facades\Herdr;

// Split the focused pane, run the test suite in the new pane, read the output.
$pane = Herdr::pane()->split('right', ratio: 0.4);

$pane->run('php artisan test');

$output = $pane->waitForOutput('Tests:')->text();
```

---

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13
- A running Herdr instance on the same host

## Installation

This is a private package. Add the GitHub repository to your app's
`composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:martin-ro/laravel-herdr.git" }
    ]
}
```

Then require it:

```bash
composer require martin-ro/laravel-herdr:dev-main
```

The service provider and `Herdr` facade are auto-discovered. To tweak defaults,
publish the config:

```bash
php artisan vendor:publish --tag=herdr-config
```

## Configuration

`config/herdr.php` (all values are env-driven):

| Key               | Env                     | Default                          | Purpose |
|-------------------|-------------------------|----------------------------------|---------|
| `socket_path`     | `HERDR_SOCKET_PATH`     | —                                | Explicit socket/pipe path (highest precedence). |
| `session`         | `HERDR_SESSION`         | —                                | Named session to talk to. |
| `base_path`       | `HERDR_CONFIG_PATH`     | `~/.config/herdr`                | Where Herdr keeps its sockets. |
| `connect_timeout` | `HERDR_CONNECT_TIMEOUT` | `5.0`                            | Seconds to wait for the connection. |
| `read_timeout`    | `HERDR_READ_TIMEOUT`    | `null` (block)                   | Seconds a single read may block. |

**Socket resolution order** (mirrors Herdr's CLI): an explicit
`Herdr::session('name')` → `socket_path` → `session` → the default
`~/.config/herdr/herdr.sock`.

---

## Usage

Everything hangs off the `Herdr` facade (or the `herdr()` helper, or
type-hinting `MartinRo\Herdr\HerdrManager`).

### Workspaces, tabs, panes

```php
use MartinRo\Herdr\Facades\Herdr;

Herdr::workspaces()->all();              // Collection<Workspace>
Herdr::workspaces()->focused();          // ?Workspace
$ws = Herdr::workspaces()->create(['label' => 'api']);

$ws->rename('api-v2');
$ws->tabs();                             // Collection<Tab>
$ws->panes();                            // Collection<Pane>
$tab = $ws->createTab(['label' => 'tests']);

Herdr::panes()->all();                   // Collection<Pane>
Herdr::panes()->current();               // the focused pane
Herdr::panes()->withAgentStatus('blocked');
```

Objects returned from reads are **live** — they carry a connection, so you can
act on them directly:

```php
foreach (Herdr::panes()->withAgentStatus('blocked') as $pane) {
    $pane->sendText('continue')->enter();
}
```

### Driving a pane

```php
$pane = Herdr::pane('w1:p1');            // a handle by id
$pane = Herdr::pane();                   // ...or the focused pane

$pane->run('npm run build');             // sendText + enter
$pane->type('y')->enter();               // chainable
$pane->sendKeys('ctrl+c');               // key combos: enter, esc, ctrl+c, f1...
$pane->sendKeys(['g', 'g']);             // a sequence

$pane->read(lines: 100)->text();         // recent output as a string
$pane->read(source: 'recent_unwrapped')->lines();  // sources: visible|recent|recent_unwrapped|detection
$pane->read(format: 'ansi');                       // raw output with escape sequences
$pane->read(stripAnsi: false);                     // override the server's strip default

$pane->waitForOutput('Compiled successfully');            // literal substring
$pane->waitForPattern('Tests:\s+\d+ passed', [            // regex
    'timeout_ms' => 10000,
]);

$right = $pane->split('right', ratio: 0.33, options: [
    'env' => ['HERDR_ROLE' => 'tests'],
]);

$pane->zoom();                           // toggle
$pane->zoom('on');                       // or 'off'
$pane->focus();
$pane->rename('build');

$pane->moveToTab('w1:t2', 'down');       // move into an existing tab
$pane->moveToNewTab();                   // ...or a new tab
$pane->moveToNewWorkspace('lab');        // ...or a new workspace

$pane->layout();                         // the containing tab's layout snapshot
$pane->close();
```

### Agents

Agents are addressed by a **target**: their unique live **name**, or the id of
the **pane** hosting them. The `agent` field is the kind, e.g. `"claude"`.

```php
Herdr::agents()->all();                  // Collection<Agent>

// Start an agent inside an existing pane. The name becomes its target.
$pane = Herdr::pane()->split('right');
$agent = Herdr::agents()->start('claude', 'reviewer', $pane, [
    'args' => ['--continue'],
]);

// Prompt: Herdr types the text and presses Enter atomically. Optionally wait
// server-side until the agent settles.
$agent->prompt('summarise the failing test');
$agent->prompt('fix it', until: 'idle', timeoutMs: 120000);

Herdr::agent('reviewer')->sendKeys('esc');   // target by name
Herdr::agent('wA:p2')->wait('idle');         // ...or by pane id

$agent->read()->text();
$agent->explain();
$agent->focus();
$agent->kind();                          // "claude"
$agent->name();                          // "reviewer"
$agent->status();                        // idle|working|blocked|done|unknown
```

Declarative agent views filter and sort the sidebar's agent list:

```php
Herdr::agents()->setView('my-tool', [
    'label'  => 'Blocked',
    'filter' => ['op' => 'eq', 'field' => 'agent_status', 'value' => 'blocked'],
]);
Herdr::agents()->clearView('my-tool');
```

Report agent state for a pane you control (e.g. from a custom CI runner):

```php
Herdr::pane('w1:p1')->reportAgent([
    'source'  => 'custom:ci',
    'agent'   => 'ci-bot',
    'state'   => 'working',
    'message' => 'running tests',
]);
```

### Worktrees

```php
$ws = Herdr::workspaces()->focused();

$worktree = $ws->createWorktree('worktree/api', ['focus' => false]);
$ws->worktrees();                        // Collection<Worktree>
$worktree->open();
$worktree->remove();
```

### Notifications & window title

```php
Herdr::notify('Build failed', 'api workspace', [
    'position' => 'top-left',
    'sound'    => 'request',
]);

Herdr::windowTitle('Deploying…');
Herdr::windowTitle(null);                 // clear
```

### Events

Herdr pushes events over the socket after you subscribe. Because reading events
**blocks**, run an event loop on its own connection — typically an artisan
command or queue worker, not a web request.

Subscription types use dotted names (`pane.created`). The pushed envelopes use
underscore names and nest the payload under `data`, with full info objects
embedded where applicable; `$event->name()`, `$event->data()` and
`$event->pane()` unwrap this for you. Data-bearing subscriptions require scope
fields: `pane.agent_status_changed` and `pane.scroll_changed` require
`pane_id`; `pane.output_matched` requires `pane_id`, `source` and a `match`.

```php
Herdr::events()->listen(function (MartinRo\Herdr\Data\Event $event) {
    if ($event->is('pane_agent_status_changed') && $event->data()->agent_status === 'blocked') {
        logger()->warning("Agent blocked in {$event->paneId()}");
    }
    // return false to stop listening
}, subscriptions: [
    ['type' => 'pane.agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'blocked'],
    'pane.exited',                         // a bare type string also works
]);
```

Block until a single event arrives (server-side, on a one-shot request). As of
Herdr 0.7.5 the server only supports pane agent-status matches here:

```php
$event = Herdr::events()->waitFor('pane_agent_status_changed', [
    'pane_id'      => 'w1:p1',
    'agent_status' => 'idle',
], timeoutMs: 30000);
```

Or client-side over a subscription stream:

```php
$event = Herdr::events()->wait(
    [['type' => 'pane.agent_status_changed', 'pane_id' => 'w1:p1', 'agent_status' => 'done']],
);
```

### Session snapshot & layouts

```php
$snapshot = Herdr::snapshot();           // workspaces, tabs, panes, agents, layouts, focus
$snapshot->protocol;                     // 17

$layout = Herdr::layouts()->export();    // the focused tab's declarative layout
Herdr::layouts()->apply($layout->get('layout.root'), ['tab_label' => 'restored']);
Herdr::layouts()->setSplitRatio([false], 0.3, ['tab_id' => 'w1:t1']);
```

### Plugins & integrations

```php
Herdr::plugins()->all();
Herdr::plugins()->link('/path/to/plugin', ['enabled' => true]);
Herdr::plugins()->enable('picker');                       // by plugin id
Herdr::plugins()->actions();                              // declared actions (all plugins)
Herdr::plugins()->invoke('jump-back', 'picker');          // action id, plugin id
Herdr::plugins()->logs('picker', limit: 20);
Herdr::plugins()->openPane('picker', 'main', ['placement' => 'popup']);

Herdr::integrations()->install('claude');                 // built-in agent integrations
Herdr::closePopup();                                      // close an open popup pane
```

### Multiple sessions

```php
Herdr::session('staging')->panes()->all();
```

### Escape hatch

Every documented method is reachable, but for anything not yet wrapped (or new
Herdr methods) call the raw API directly:

```php
$result = Herdr::call('pane.process_info', ['pane_id' => 'w1:p1']);  // array
$result = Herdr::result('server.agent_manifests')->toArray();        // Result wrapper
```

Returned `Data`/`Result` objects are permissive: typed accessors exist for the
documented fields, and **every** field is reachable via `->field`, `['field']`,
`->get('dot.path')`, or `->toArray()` — so undocumented or newly-added fields
are never lost.

---

## Error handling

```php
use MartinRo\Herdr\Exceptions\ConnectionException; // socket couldn't open / closed / timed out
use MartinRo\Herdr\Exceptions\RequestException;    // Herdr replied with an { error } payload

try {
    Herdr::pane('w9:p9')->focus();
} catch (RequestException $e) {
    $e->code();         // e.g. "pane_not_found", "invalid_request"
    $e->isNotFound();   // true for not_found / *_not_found codes
    $e->getMessage();
}
```

`find()` helpers swallow not-found errors (Herdr namespaces them per resource,
e.g. `pane_not_found`, `workspace_not_found`) and return `null`:

```php
Herdr::panes()->find('w9:p9');           // ?Pane
```

---

## Testing

Fake the transport with `Herdr::fake()` — no socket required. Stub responses by
method name and assert what was sent, just like `Http::fake()`.

```php
use MartinRo\Herdr\Facades\Herdr;
use MartinRo\Herdr\Testing\FakeResponse;

Herdr::fake([
    'workspace.list' => ['workspaces' => [['workspace_id' => 'w1', 'label' => 'api']]],
    'pane.get'       => FakeResponse::error('pane_not_found', 'pane not found'),
    'pane.read'      => fn (array $params) => ['lines' => ["ran {$params['pane_id']}"]],
]);

$names = Herdr::workspaces()->all()->map->name();   // ['api']

Herdr::pane('w1:p1')->run('php artisan test');

Herdr::assertSent(fn ($method, $params) =>
    $method === 'pane.send_text' && $params['text'] === 'php artisan test');

Herdr::assertSentCount(2);
```

Drive the event stream in tests by queuing events:

```php
Herdr::fake(['events.subscribe' => ['type' => 'subscription_started']]);
Herdr::pushEvent([
    'event' => 'pane_exited',
    'data'  => ['type' => 'pane_exited', 'pane_id' => 'w1:p1'],
]);

$event = Herdr::events()->next();        // MartinRo\Herdr\Data\Event
```

Run the package's own suite:

```bash
composer install
vendor/bin/phpunit
```

---

## Architecture

```
HerdrManager ──┬─ ping()/call()/result()        low-level escape hatch
               ├─ snapshot()                      full session state in one call
               ├─ workspaces()/panes()/agents() … plural query resources
               ├─ pane()/workspace()/agent()    … live, actionable objects
               ├─ layouts()/plugins()/integrations() … declarative & extension APIs
               ├─ events()                        subscribe + stream, waitFor()
               └─ fake()/assertSent()             test doubles

Connection      one socket per request; assigns ids, correlates responses;
   │            holds a persistent socket for event streams
   └─ Transport (interface)
        ├─ SocketTransport   real Unix socket / named pipe, buffered line reader
        └─ FakeTransport     in-memory, for tests
```

- **One request per connection.** Herdr serves a single response per socket and
  then closes it, so each call opens a fresh connection. Subscriptions are the
  exception: the socket stays open and the server pushes events down it. The
  `Herdr` manager is a safe long-lived singleton — it opens connections on
  demand, so you never hold a dead socket.
- **Framing** is exact: one JSON object per line, terminated by `\n`; the reader
  buffers partial reads and splits multiple messages that arrive in one chunk.
- **Correlation**: each request gets a unique `id`; responses are matched by it,
  and any unsolicited event lines (which carry no `id`) are parked for the event
  stream.
- **Data objects** wrap raw attributes permissively, so the package keeps
  working even where Herdr's response fields are undocumented or evolve.

> Field names and the connection model in this package were verified against a
> live **Herdr 0.7.5** server (socket protocol 17).

## License

MIT.

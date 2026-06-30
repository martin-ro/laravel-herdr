<?php

namespace MartinRo\Herdr\Tests\Unit;

use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\Data\Result;
use MartinRo\Herdr\Data\Workspace;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    public function test_it_exposes_attributes_via_magic_array_and_dot_access(): void
    {
        $pane = new Pane([
            'pane_id' => 'w1:p1',
            'agent_status' => 'working',
            'focused' => true,
            'nested' => ['deep' => 'value'],
        ]);

        $this->assertSame('w1:p1', $pane->pane_id);
        $this->assertSame('w1:p1', $pane->paneId);          // camelCase access
        $this->assertSame('w1:p1', $pane['pane_id']);        // array access
        $this->assertSame('working', $pane->agentStatus());  // typed accessor
        $this->assertTrue($pane->isFocused());
        $this->assertSame('value', $pane->get('nested.deep')); // dot access
        $this->assertNull($pane->get('missing'));
        $this->assertSame('fallback', $pane->get('missing', 'fallback'));
    }

    public function test_from_result_unwraps_the_nested_object(): void
    {
        $pane = Pane::fromResult([
            'type' => 'pane_info',
            'pane' => ['pane_id' => 'w1:p2', 'revision' => 42],
        ]);

        $this->assertSame('w1:p2', $pane->id());
        $this->assertSame(42, $pane->revision);
    }

    public function test_collection_from_result_uses_declared_list_keys(): void
    {
        $workspaces = Workspace::collectionFromResult([
            'type' => 'workspace_list',
            'workspaces' => [
                ['workspace_id' => 'w1', 'label' => 'api'],
                ['workspace_id' => 'w2', 'label' => 'web'],
            ],
        ]);

        $this->assertCount(2, $workspaces);
        $this->assertSame('w1', $workspaces->first()->id());
        $this->assertSame('web', $workspaces->last()->label());
        $this->assertSame('web', $workspaces->last()->name()); // alias
    }

    public function test_collection_from_result_falls_back_to_the_first_list(): void
    {
        // No declared key match: should still find the list-shaped value.
        $panes = Pane::collectionFromResult([
            'type' => 'something',
            'items' => [['pane_id' => 'w1:p1']],
        ]);

        $this->assertCount(1, $panes);
        $this->assertSame('w1:p1', $panes->first()->id());
    }

    public function test_result_extracts_text_and_lines(): void
    {
        $this->assertSame("a\nb", (new Result(['text' => "a\nb"]))->text());
        $this->assertSame(['a', 'b'], (new Result(['lines' => ['a', 'b']]))->lines());
        $this->assertSame("a\nb", (new Result(['lines' => ['a', 'b']]))->text());
    }

    public function test_it_is_json_serialisable(): void
    {
        $pane = new Pane(['pane_id' => 'w1:p1']);

        $this->assertSame('{"pane_id":"w1:p1"}', json_encode($pane));
    }
}

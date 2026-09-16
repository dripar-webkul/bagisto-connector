<?php

namespace Webkul\Bagisto\Tests\Unit\Support;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Webkul\Bagisto\Support\CategoryScope;
use Webkul\Category\Models\Category;
use Webkul\Core\Models\Channel;

class CategoryScopeTest extends TestCase
{
    private string $channel;

    private string $inChannel;

    private string $outOfChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        $root = Category::create(['code' => 'scope_root_'.$suffix]);

        $this->inChannel = 'in_channel_'.$suffix;
        $this->outOfChannel = 'out_of_channel_'.$suffix;

        Category::create(['code' => $this->inChannel, 'parent_id' => $root->id]);
        Category::create(['code' => $this->outOfChannel]);

        $channel = Channel::first()->replicate();
        $channel->code = 'scope_channel_'.$suffix;
        $channel->root_category_id = $root->id;
        $channel->save();

        $this->channel = $channel->code;

        Cache::flush();
    }

    public function test_scoped_codes_keeps_only_codes_under_the_channel_root()
    {
        $this->assertSame(
            [$this->inChannel],
            CategoryScope::scopedCodes([$this->inChannel, $this->outOfChannel], [$this->channel])
        );
    }

    public function test_scoped_codes_matches_nothing_when_the_channel_reaches_no_root()
    {
        $this->assertSame(
            [],
            CategoryScope::scopedCodes([$this->inChannel], ['channel_that_does_not_exist'])
        );
    }

    public function test_scoped_codes_narrows_to_every_channel_root_when_none_is_selected()
    {
        $this->assertSame(
            [$this->inChannel],
            CategoryScope::scopedCodes([$this->inChannel, $this->outOfChannel], [])
        );
    }

    public function test_scoped_codes_leaves_the_codes_alone_when_no_channel_declares_a_root()
    {
        Channel::query()->update(['root_category_id' => null]);

        Cache::flush();

        $codes = [$this->inChannel, $this->outOfChannel];

        $this->assertSame($codes, CategoryScope::scopedCodes($codes, []));
    }

    public function test_scope_query_narrows_to_the_channel_root()
    {
        $codes = CategoryScope::scopeQuery(Category::query(), [$this->channel])->pluck('code')->all();

        $this->assertContains($this->inChannel, $codes);
        $this->assertNotContains($this->outOfChannel, $codes);
    }

    public function test_scope_query_matches_nothing_when_the_channel_reaches_no_root()
    {
        $this->assertSame(
            [],
            CategoryScope::scopeQuery(Category::query(), ['channel_that_does_not_exist'])->pluck('code')->all()
        );
    }

    public function test_scope_query_is_untouched_when_no_channel_declares_a_root()
    {
        Channel::query()->update(['root_category_id' => null]);

        Cache::flush();

        $sql = CategoryScope::scopeQuery(Category::query(), [])->toSql();

        $this->assertStringNotContainsString('1 = 0', $sql);
    }
}

<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Category;

use Mockery;
use Tests\TestCase;
use Webkul\Bagisto\Helpers\Exporters\Category\Exporter;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CategoryFieldMappingRepository;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Category\Models\Category;
use Webkul\Category\Repositories\CategoryFieldRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;

class CategorySelectionTest extends TestCase
{
    private Exporter $exporter;

    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new Exporter(
            Mockery::mock(JobTrackBatchRepository::class),
            Mockery::mock(FlatItemBuffer::class),
            Mockery::mock(BagistoDataMapping::class),
            Mockery::mock(CategoryFieldRepository::class),
            Mockery::mock(CategoryFieldMappingRepository::class),
            Mockery::mock(CredentialRepository::class),
        );

        $source = new \ReflectionProperty(get_parent_class($this->exporter), 'source');
        $source->setAccessible(true);
        $source->setValue($this->exporter, app(CategoryRepository::class));

        $this->buildTree();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function buildTree(): void
    {
        $suffix = uniqid();

        $parent = Category::whereIsRoot()->first();

        foreach (['men', 'shirts', 'formal'] as $segment) {
            $this->codes[$segment] = $segment.'_'.$suffix;

            $parent = Category::create([
                'code'      => $this->codes[$segment],
                'parent_id' => $parent->id,
            ]);
        }

        $this->codes['women'] = 'women_'.$suffix;

        Category::create([
            'code'      => $this->codes['women'],
            'parent_id' => Category::whereIsRoot()->first()->id,
        ]);
    }

    private function withAncestors(array $codes): array
    {
        $method = new \ReflectionMethod($this->exporter, 'withAncestorCodes');
        $method->setAccessible(true);

        $resolved = $method->invoke($this->exporter, $codes);

        sort($resolved);

        return $resolved;
    }

    public function test_it_pulls_in_every_ancestor_of_the_selected_category()
    {
        $resolved = $this->withAncestors([$this->codes['formal']]);

        $this->assertContains($this->codes['men'], $resolved);
        $this->assertContains($this->codes['shirts'], $resolved);
        $this->assertContains($this->codes['formal'], $resolved);
    }

    public function test_it_leaves_branches_that_were_not_selected_out()
    {
        $this->assertNotContains($this->codes['women'], $this->withAncestors([$this->codes['formal']]));
    }

    public function test_it_never_lists_a_category_twice()
    {
        $resolved = $this->withAncestors([$this->codes['formal'], $this->codes['shirts'], $this->codes['men']]);

        $this->assertSame($resolved, array_unique($resolved));
    }

    public function test_a_top_level_selection_needs_no_ancestors()
    {
        $resolved = $this->withAncestors([$this->codes['women']]);

        $this->assertSame([$this->codes['women']], array_values(array_diff($resolved, ['root'])));
    }
}

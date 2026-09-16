<?php

namespace Webkul\Bagisto\Tests\Unit\Enums;

use Tests\TestCase;
use Webkul\Bagisto\Enums\CredentialTab;

class CredentialTabTest extends TestCase
{
    public function test_it_lists_every_tab_once_in_order()
    {
        $this->assertSame(
            ['general', 'attribute_mapping', 'category_mapping'],
            array_column(CredentialTab::items(1), 'key')
        );
    }

    public function test_it_points_each_tab_at_its_own_page_for_the_credential()
    {
        $urls = array_column(CredentialTab::items(7), 'url', 'key');

        $this->assertStringEndsWith('/credentials/edit/7', $urls['general']);
        $this->assertStringEndsWith('/credentials/7/attribute-mapping', $urls['attribute_mapping']);
        $this->assertStringEndsWith('/credentials/7/category-mapping', $urls['category_mapping']);
    }

    public function test_it_gives_every_tab_a_translatable_label()
    {
        foreach (CredentialTab::items(1) as $tab) {
            $this->assertStringStartsWith('bagisto::app.bagisto.credentials.tabs.', $tab['label']);
        }
    }

    public function test_it_sends_history_to_the_credential_page_rather_than_the_current_tab()
    {
        $this->assertStringEndsWith('/credentials/edit/7?history=1', CredentialTab::historyUrl(7));
    }

    public function test_it_uses_one_history_url_no_matter_which_tab_asked_for_it()
    {
        $fromAnyTab = array_map(fn () => CredentialTab::historyUrl(7), CredentialTab::cases());

        $this->assertCount(1, array_unique($fromAnyTab));
    }

    public function test_it_keeps_one_credential_history_apart_from_another()
    {
        $this->assertNotSame(CredentialTab::historyUrl(7), CredentialTab::historyUrl(8));
    }
}

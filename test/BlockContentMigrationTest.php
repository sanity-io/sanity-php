<?php
namespace SanityTest;

use Sanity\BlockContent;

class BlockContentMigrationTest extends TestCase
{
    public function __construct() {
        BlockContent::$useStaticKeys = true;
    }

    public function testMigrateBoldUnderlineToV2()
    {
        $input = $this->loadFixture('bold-underline-text.json');

        $expected = [
            '_type' => 'block',
            'style' => 'normal',
            'markDefs' => [],
            'children' => [
                [
                    '_type' => 'span',
                    'text' => 'Normal',
                    'marks' => []
                ],
                [
                    '_type' => 'span',
                    'text' => 'only-bold',
                    'marks' => ['strong']
                ],
                [
                    '_type' => 'span',
                    'text' => 'bold-and-underline',
                    'marks' => ['strong', 'underline']
                ],
                [
                    '_type' => 'span',
                    'text' => 'only-underline',
                    'marks' => ['underline']
                ],
                [
                    '_type' => 'span',
                    'text' => 'normal',
                    'marks' => []
                ],
            ]
        ];

        $actual = BlockContent::migrateBlock($input);
        $this->assertEquals($expected, $actual);
    }

    public function testMigrateLinkToV2()
    {
        $input = $this->loadFixture('link-simple-text.json');

        $expected = [
            '_type' => 'block',
            'style' => 'normal',
            'markDefs' => [
                [
                    '_key' => '6721bbe',
                    '_type' => 'link',
                    'href' => 'http://icanhas.cheezburger.com/'
                ]
            ],
            'children' => [
                [
                    '_type' => 'span',
                    'text' => 'String before link ',
                    'marks' => []
                ],
                [
                    '_type' => 'span',
                    'text' => 'actual link text',
                    'marks' => ['6721bbe']
                ],
                [
                    '_type' => 'span',
                    'text' => ' the rest',
                    'marks' => []
                ],
            ]
        ];

        $actual = BlockContent::migrateBlock($input);
        $this->assertEquals($expected, $actual);
    }

    public function testMigrateCustomMarkToV2()
    {
        $input = $this->loadFixture('link-author-text.json');

        $expected = [
            '_type' => 'block',
            'style' => 'normal',
            'markDefs' => [
                [
                    '_key' => '6721bbe',
                    '_type' => 'link',
                    'href' => 'http://icanhas.cheezburger.com/'
                ],
                [
                    '_key' => 'a0cc21d',
                    '_type' => 'author',
                    'name' => 'Test Testesen'
                ]
            ],
            'children' => [
                [
                    '_type' => 'span',
                    'text' => 'String before link ',
                    'marks' => []
                ],
                [
                    '_type' => 'span',
                    'text' => 'actual link text',
                    'marks' => ['6721bbe', 'a0cc21d']
                ],
                [
                    '_type' => 'span',
                    'text' => ' the rest',
                    'marks' => []
                ],
            ]
        ];

        $actual = BlockContent::migrate($input);
        $this->assertEquals($expected, $actual);
    }
}

<?php
namespace SanityTest;

use Sanity\BlockContent;

class BlockContentTreeTest extends TestCase
{
    public function __construct()
    {
        BlockContent::$useStaticKeys = true;
    }

    public function testHandlesNormalTextBlock()
    {
        $input = $this->loadFixture('normal-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'Normal string of text.',
            ]
        ];

        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesItalicizedText()
    {
        $input = $this->loadFixture('italicized-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'String with an ',
                [
                    'type' => 'span',
                    'mark' => 'em',
                    'content' => [
                        'italicized'
                    ]
                ],
                ' word.'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesUnderlineText()
    {
        $input = $this->loadFixture('underlined-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'String with an ',
                [
                    'type' => 'span',
                    'mark' => 'underline',
                    'content' => [
                        'underlined'
                    ]
                ],
                ' word.'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesBoldUnderlineText()
    {
        $input = $this->loadFixture('bold-underline-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'Normal',
                [
                    'type' => 'span',
                    'mark' => 'strong',
                    'content' => [
                        'only-bold',
                        [
                            'type' => 'span',
                            'mark' => 'underline',
                            'content' => [
                                'bold-and-underline'
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'span',
                    'mark' => 'underline',
                    'content' => [
                        'only-underline'
                    ]
                ],
                'normal'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testDoesNotCareAboutSpanMarksOrder()
    {
        $orderedInput = $this->loadFixture('marks-ordered-text.json');
        $reorderedInput = $this->loadFixture('marks-reordered-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'Normal',
                [
                    'type' => 'span',
                    'mark' => 'strong',
                    'content' => [
                        'strong',
                        [
                            'type' => 'span',
                            'mark' => 'underline',
                            'content' => [
                                'strong and underline',
                                [
                                    'type' => 'span',
                                    'mark' => 'em',
                                    'content' => [
                                        'strong and underline and emphasis'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'span',
                    'mark' => 'em',
                    'content' => [
                        [
                            'type' => 'span',
                            'mark' => 'underline',
                            'content' => [
                                'underline and emphasis'
                            ]
                        ]
                    ]
                ],
                'normal again'
            ]
        ];
        $this->assertEquals($expected, BlockContent::ToTree($orderedInput));
        $this->assertEquals($expected, BlockContent::ToTree($reorderedInput));
    }


    public function testHandlesMessyText()
    {
        $input = $this->loadFixture('messy-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'Hacking ',
                [
                    'type' => 'span',
                    'mark' => 'code',
                    'content' => [
                        'teh codez'
                    ]
                ],
                ' is ',
                [
                    'type' => 'span',
                    'mark' => 'strong',
                    'content' => [
                        'all ',
                        [
                            'type' => 'span',
                            'mark' => 'underline',
                            'content' => [
                                'fun'
                            ]
                        ],
                        ' and ',
                        [
                            'type' => 'span',
                            'mark' => 'em',
                            'content' => [
                                'games'
                            ]
                        ],
                        ' until'
                    ]
                ],
                ' someone gets p0wn3d.'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesSimpleLinkText()
    {
        $input = $this->loadFixture('link-simple-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'String before link ',
                [
                    'type' => 'span',
                    'mark' => [
                        '_type' => 'link',
                        '_key' => '6721bbe',
                        'href' => 'http://icanhas.cheezburger.com/'
                    ],
                    'content' => [
                        'actual link text'
                    ]
                ],
                ' the rest'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesMessyLinkText()
    {
        $input = $this->loadFixture('link-messy-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'String with link to ',
                [
                    'type' => 'span',
                    'mark' => [
                        '_type' => 'link',
                        '_key' => '6721bbe',
                        'href' => 'http://icanhas.cheezburger.com/'
                    ],
                    'content' => [
                        'internet ',
                        [
                            'type' => 'span',
                            'mark' => 'em',
                            'content' => [
                                [
                                    'type' => 'span',
                                    'mark' => 'strong',
                                    'content' => [
                                        'is very strong and emphasis'
                                    ]
                                ],
                                ' and just emphasis'
                            ]
                        ]
                    ]
                ],
                '.'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesMessyLinkTextWithNewStructure()
    {
        $input = $this->loadFixture('link-messy-text-new.json');
        $expected = [
            'type' => 'block',
            'style' => 'normal',
            'content' => [
                'String with link to ',
                [
                    'type' => 'span',
                    'mark' => [
                        '_type' => 'link',
                        '_key' => 'zomgLink',
                        'href' => 'http://icanhas.cheezburger.com/'
                    ],
                    'content' => [
                        'internet ',
                        [
                            'type' => 'span',
                            'mark' => 'em',
                            'content' => [
                                [
                                    'type' => 'span',
                                    'mark' => 'strong',
                                    'content' => [
                                        'is very strong and emphasis'
                                    ]
                                ],
                                ' and just emphasis'
                            ]
                        ]
                    ]
                ],
                '.'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesNumberedList()
    {
        $input = $this->loadFixture('list-numbered-blocks.json');
        $expected = [[
            'type' => 'list',
            'itemStyle' => 'number',
            'items' => [
                [
                    'type' => 'block',
                    'style' => 'normal',
                    'content' => [
                        'One'
                    ]
                ],
                [
                    'type' => 'block',
                    'style' => 'normal',
                    'content' => [
                        'Two has ',
                        [
                            'type' => 'span',
                            'mark' => 'strong',
                            'content' => [
                                'bold'
                            ]
                        ],
                        ' word'
                    ]
                ],
                [
                    'type' => 'block',
                    'style' => 'h2',
                    'content' => [
                        'Three'
                    ]
                ]
            ]
        ]];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }


    public function testHandlesBulletedList()
    {
        $input = $this->loadFixture('list-bulleted-blocks.json');
        $expected = [[
            'type' => 'list',
            'itemStyle' => 'bullet',
            'items' => [
                [
                    'type' => 'block',
                    'style' => 'normal',
                    'content' => [
                        'I am the most'
                    ]
                ],
                [
                    'type' => 'block',
                    'style' => 'normal',
                    'content' => [
                        'expressive',
                        [
                            'type' => 'span',
                            'mark' => 'strong',
                            'content' => [
                                'programmer'
                            ]
                        ],
                        'you know.'
                    ]
                ],
                [
                    'type' => 'block',
                    'style' => 'normal',
                    'content' => [
                        'SAD!'
                    ]
                ]
            ]
        ]];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesMultipleLists()
    {
        $input = $this->loadFixture('list-both-types-blocks.json');
        $expected = [
            [
                'type' => 'list',
                'itemStyle' => 'bullet',
                'items' => [
                    [
                        'type' => 'block',
                        'style' => 'normal',
                        'content' => [
                            'A single bulleted item'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'list',
                'itemStyle' => 'number',
                'items' => [
                    [
                        'type' => 'block',
                        'style' => 'normal',
                        'content' => [
                            'First numbered'
                        ]
                    ],
                    [
                        'type' => 'block',
                        'style' => 'normal',
                        'content' => [
                            'Second numbered'
                        ]
                    ]
                ]
            ],
            [
                'type' => 'list',
                'itemStyle' => 'bullet',
                'items' => [
                    [
                        'type' => 'block',
                        'style' => 'normal',
                        'content' => [
                            'A bullet with',
                            [
                                'type' => 'span',
                                'mark' => 'strong',
                                'content' => [
                                    'something strong'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesPlainH2Block()
    {
        $input = $this->loadFixture('h2-text.json');
        $expected = [
            'type' => 'block',
            'style' => 'h2',
            'content' => [
                'Such h2 header, much amaze'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }


    public function testHandlesNonBlockType()
    {
        $input = $this->loadFixture('non-block.json');
        $expected = [
            'type' => 'author',
            'attributes' => [
                'name' => 'Test Person'
            ]
        ];
        $actual = BlockContent::ToTree($input);
        $this->assertEquals($expected, $actual);
    }

    public function testCanBeCalledAsInvokable()
    {
        $input = $this->loadFixture('non-block.json');
        $expected = [
            'type' => 'author',
            'attributes' => [
                'name' => 'Test Person'
            ]
        ];

        $treeBuilder = new BlockContent\TreeBuilder();
        $actual = $treeBuilder($input);
        $this->assertEquals($expected, $actual);
    }
}

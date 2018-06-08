<?php
namespace SanityTest;

use Sanity\BlockContent;
use Sanity\BlockContent\HtmlBuilder;
use Sanity\BlockContent\Serializers\DefaultSpan;
use SanityTest\Serializers\MyCustomImageSerializer;

class BlockContentHtmlTest extends TestCase
{
    private $htmlBuilder;
    private $customHtmlBuilder;

    public function __construct()
    {
        BlockContent::$useStaticKeys = true;

        $defaultSpan = new DefaultSpan();
        $serializers = [
            'author' => function ($author) {
                return '<div>' . $author['attributes']['name'] . '</div>';
            },
            'block' => function ($block) {
                return $block['style'] === 'h2'
                    ? '<div class="big-heading">' . implode('', $block['children']) . '</div>'
                    : '<p class="foo">' . implode('', $block['children']) . '</p>';
            },
            'list' => function($list) {
                $style = isset($list['itemStyle']) ? $list['itemStyle'] : 'default';
                $tagName = $style === 'number' ? 'ol' : 'ul';
                return '<' . $tagName . ' class="foo">' . implode('', $list['children']) . '</' . $tagName . '>';
            },
            'listItem' => function($item) {
                return '<li class="foo">' . implode('', $item['children']) . '</li>';
            },

            'marks' => [
                'em' => null,
                'author' => function ($mark, $children) {
                    return '<div>' . $mark['name'] . '</div>' . implode('', $children);
                },
                'link' => [
                    'head' => function ($mark) {
                        return '<a class="foo" href="' . $mark['href'] . '">';
                    },
                    'tail' => '</a>'
                ]
            ]
        ];
        $this->customHtmlBuilder = new HtmlBuilder(['serializers' => $serializers]);
        $this->htmlBuilder = new HtmlBuilder([
            'projectId' => 'abc123',
            'dataset' => 'prod',
            'imageOptions' => ['fit' => 'crop', 'w' => 320, 'h' => 240]
        ]);
    }

    public function testHandlesPlainStringBlock()
    {
        $input = BlockContent::toTree($this->loadFixture('normal-text.json'));
        $expected = '<p>Normal string of text.</p>';
        $actual = $this->htmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesPlainStringBlockWithCustomSerializer()
    {
        $input = BlockContent::toTree($this->loadFixture('normal-text.json'));
        $expected = '<p class="foo">Normal string of text.</p>';
        $actual = $this->customHtmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesItalicizedText()
    {
        $input = BlockContent::toTree($this->loadFixture('italicized-text.json'));
        $expected = '<p>String with an <em>italicized</em> word.</p>';
        $actual = $this->htmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesItalicizedTextCustomHandlerRemovesEmMarkIfMappedToNull()
    {
        $input = BlockContent::toTree($this->loadFixture('italicized-text.json'));
        $expected = '<p class="foo">String with an italicized word.</p>';
        $actual = $this->customHtmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testHandlesUnderlinedText()
    {
        $input = BlockContent::toTree($this->loadFixture('underlined-text.json'));
        $expected = '<p>String with an <span style="text-decoration: underline;">underlined</span> word.</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesBoldUnderlinedText()
    {
        $input = BlockContent::toTree($this->loadFixture('bold-underline-text.json'));
        $expected = '<p>Normal<strong>only-bold<span style="text-decoration: underline;">bold-and-underline</span></strong><span style="text-decoration: underline;">only-underline</span>normal</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testDoesNotCareAboutSpanMarksOrder()
    {
        $orderedInput = BlockContent::toTree($this->loadFixture('marks-ordered-text.json'));
        $reorderedInput = BlockContent::toTree($this->loadFixture('marks-reordered-text.json'));
        $expected = '<p>Normal<strong>strong<span style="text-decoration: underline;">strong and underline<em>strong and underline and emphasis</em></span></strong>'
            . '<em><span style="text-decoration: underline;">underline and emphasis</span></em>normal again</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($orderedInput));
        $this->assertEquals($expected, $this->htmlBuilder->build($reorderedInput));
    }


    public function testHandlesMessyText()
    {
        $input = BlockContent::toTree($this->loadFixture('messy-text.json'));
        $expected = '<p>Hacking <code>teh codez</code> is <strong>all <span style="text-decoration: underline;">fun</span>'
            . ' and <em>games</em> until</strong> someone gets p0wn3d.</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesSimpleLinkText()
    {
        $input = BlockContent::toTree($this->loadFixture('link-simple-text.json'));
        $expected = '<p>String before link <a href="http://icanhas.cheezburger.com/">actual link text</a> the rest</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesSimpleLinkTextWithCustomSerializer()
    {
        $input = BlockContent::toTree($this->loadFixture('link-simple-text.json'));
        $expected = '<p class="foo">String before link <a class="foo" href="http://icanhas.cheezburger.com/">actual link text</a> the rest</p>';
        $this->assertEquals($expected, $this->customHtmlBuilder->build($input));
    }

    public function testHandlesSimpleLinkTextWithSeveralAttributesWithCustomSerializer()
    {
        $input = BlockContent::toTree($this->loadFixture('link-author-text.json'));
        $expected = '<p class="foo">String before link '
            . '<a class="foo" href="http://icanhas.cheezburger.com/"><div>Test Testesen</div>actual link text</a> the rest</p>';
        $this->assertEquals($expected, $this->customHtmlBuilder->build($input));
    }

    public function testHandlesImages()
    {
        $input = BlockContent::toTree($this->loadFixture('images.json'));
        $expected = '<p>Also, images are pretty common.</p>'
            . '<figure><img src="https://cdn.sanity.io/images/abc123/prod/YiOKD0O6AdjKPaK24WtbOEv0-3456x2304.jpg?fit=crop&w=320&h=240" /></figure>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testAllowsOverridingImageSerializer()
    {
        $htmlBuilder = new HtmlBuilder([
            'serializers' => ['image' => new MyCustomImageSerializer()],
            'projectId' => 'abc123',
            'dataset' => 'prod',
            'imageOptions' => ['fit' => 'crop', 'w' => 320, 'h' => 240]
        ]);

        $input = BlockContent::toTree($this->loadFixture('image-with-caption.json'));
        $url = 'https://cdn.sanity.io/images/abc123/prod/YiOKD0O6AdjKPaK24WtbOEv0-3456x2304.jpg?fit=crop&w=320&h=240';
        $expected = '<p>Also, images are pretty common.</p>'
            . '<figure><img src="' . $url . '" />'
            . '<figcaption>Now ==&gt; THIS &lt;== is a caption</figcaption>'
            . '</figure>';
        $this->assertEquals($expected, $htmlBuilder->build($input));
    }

    public function testHandlesMessyLinkText()
    {
        $input = BlockContent::toTree($this->loadFixture('link-messy-text.json'));
        $expected = '<p>String with link to <a href="http://icanhas.cheezburger.com/">internet <em><strong>is very strong and emphasis</strong> and just emphasis</em></a>.</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesMessyLinkTextWithNewStructure()
    {
        $input = BlockContent::toTree($this->loadFixture('link-messy-text-new.json'));
        $expected = '<p>String with link to <a href="http://icanhas.cheezburger.com/">internet <em><strong>is very strong and emphasis</strong> and just emphasis</em></a>.</p>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesNumberedList()
    {
        $input = BlockContent::toTree($this->loadFixture('list-numbered-blocks.json'));
        $expected = '<ol><li><p>One</p></li><li><p>Two has <strong>bold</strong> word</p></li><li><h2>Three</h2></li></ol>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesNumberedListWithCustomSerializer()
    {
        $input = BlockContent::toTree($this->loadFixture('list-numbered-blocks.json'));
        $expected = '<ol class="foo"><li class="foo"><p class="foo">One</p></li>'
            . '<li class="foo"><p class="foo">Two has <strong>bold</strong> word</p></li>'
            . '<li class="foo"><div class="big-heading">Three</div></li></ol>';
        $this->assertEquals($expected, $this->customHtmlBuilder->build($input));
    }


    public function testHandlesBulletedList()
    {
        $input = BlockContent::toTree($this->loadFixture('list-bulleted-blocks.json'));
        $expected = '<ul><li><p>I am the most</p></li><li><p>expressive<strong>programmer</strong>you know.</p>'
            . '</li><li><p>SAD!</p></li></ul>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesMultipleLists()
    {
        $input = BlockContent::toTree($this->loadFixture('list-both-types-blocks.json'));
        $expected = '<ul><li><p>A single bulleted item</p></li></ul>'
            . '<ol><li><p>First numbered</p></li><li><p>Second numbered</p></li></ol>'
            . '<ul><li><p>A bullet with<strong>something strong</strong></p></li></ul>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesPlainH2Block()
    {
        $input = BlockContent::toTree($this->loadFixture('h2-text.json'));
        $expected = '<h2>Such h2 header, much amaze</h2>';
        $this->assertEquals($expected, $this->htmlBuilder->build($input));
    }

    public function testHandlesPlainH2BlockWithCustomSerializer()
    {
        $input = BlockContent::toTree($this->loadFixture('h2-text.json'));
        $expected = '<div class="big-heading">Such h2 header, much amaze</div>';
        $this->assertEquals($expected, $this->customHtmlBuilder->build($input));
    }

    /**
     * @expectedException Sanity\Exception\ConfigException
     * @expectedExceptionMessage No serializer registered for node type "author"
     */
    public function testThrowsErrorOnCustomBlockTypeWithoutRegisteredHandler()
    {
        $input = BlockContent::toTree($this->loadFixture('custom-block.json'));
        $this->htmlBuilder->build($input);
    }

    public function testHandlesCustomBlockTypeWithCustomRegisteredHandler()
    {
        $input = BlockContent::toTree($this->loadFixture('custom-block.json'));
        $expected = '<div>Test Person</div>';
        $actual = $this->customHtmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testEscapesHtmlCharacters()
    {
        $input = BlockContent::toTree($this->loadFixture('dangerous-text.json'));
        $expected = '<p>I am 1337 &lt;script&gt;alert(&#039;//haxxor&#039;);&lt;/script&gt;</p>';
        $actual = $this->htmlBuilder->build($input);
        $this->assertEquals($expected, $actual);
    }

    public function testEscapesCharactersInNonUnicodeCharsets()
    {
        $input = BlockContent::toTree($this->loadFixture('dangerous-text.json'));
        $expected = '<p>I am 1337 &lt;script&gt;alert(&#039;//haxxor&#039;);&lt;/script&gt;</p>';
        $actual = BlockContent::toHtml($input, ['charset' => 'iso-8859-1']);
        $this->assertEquals($expected, $actual);
    }

    public function testEscapesCharactersForCharsetsThatNeedsConversionToUnicode()
    {
        $input = BlockContent::toTree($this->loadFixture('dangerous-text.json'));
        $expected = '<p>I am 1337 &lt;script&gt;alert(&#039;//haxxor&#039;);&lt;/script&gt;</p>';
        $actual = BlockContent::toHtml($input, ['charset' => 'ASCII']);
        $this->assertEquals($expected, $actual);
    }

    public function testCanBeCalledStaticallyWithArray()
    {
        $expected = '<p>Hacking <code>teh codez</code> is <strong>all <span style="text-decoration: underline;">fun</span>'
            . ' and <em>games</em> until</strong> someone gets p0wn3d.</p>';
        $this->assertEquals($expected, BlockContent::toHtml($this->loadFixture('messy-text.json')));
    }

    public function testCanBeCalledStaticallyWithTree()
    {
        $expected = '<p>Hacking <code>teh codez</code> is <strong>all <span style="text-decoration: underline;">fun</span>'
            . ' and <em>games</em> until</strong> someone gets p0wn3d.</p>';
        $tree = BlockContent::toTree($this->loadFixture('messy-text.json'));
        $this->assertEquals($expected, BlockContent::toHtml($tree));
    }

    public function testCanBeCalledAsFunction()
    {
        $input = BlockContent::toTree($this->loadFixture('link-simple-text.json'));
        $expected = '<p>String before link <a href="http://icanhas.cheezburger.com/">actual link text</a> the rest</p>';
        $htmlBuilder = $this->htmlBuilder;
        $this->assertEquals($expected, $htmlBuilder($input));
    }

    public function testCanSerializeDocument() {
        $input = $this->loadFixture('document.json')['body'];
        $expected = '<p>Integer leo sapien, aliquet nec sodales et, fermentum id arcu. Sed vitae fermentum erat. Cras vitae fermentum neque. Nunc condimentum justo ut est rutrum, nec varius lectus aliquam. Nunc vulputate nunc scelerisque, pulvinar odio quis, pulvinar tortor. Mauris iaculis enim non nibh condimentum bibendum. Proin imperdiet ligula sed neque laoreet gravida. Proin non lorem a leo venenatis efficitur sit amet et arcu. Suspendisse potenti. Praesent tempus vitae elit vel blandit. Vestibulum sollicitudin metus vel urna sollicitudin egestas.</p><p>Maecenas massa dui, pretium ac quam sed, euismod viverra risus. Nam vehicula, libero eget tincidunt ullamcorper, nibh mauris auctor ex, quis vulputate felis massa ac libero. Praesent eget auctor justo. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas nec purus vel magna pellentesque aliquet. Vestibulum sit amet enim nec nulla tempus maximus. Proin maximus elementum maximus. Pellentesque quis interdum nisl. </p>';
        $this->assertEquals($expected, BlockContent::toHtml($input));
    }
}

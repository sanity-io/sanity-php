<?php
namespace Sanity\BlockContent\Serializers;

class DefaultList
{
    public function __invoke($list)
    {
        $style = isset($list['itemStyle']) ? $list['itemStyle'] : 'default';
        $tagName = $style === 'number' ? 'ol' : 'ul';
        return '<' . $tagName . '>' . implode('', $list['children']) . '</' . $tagName . '>';
    }
}

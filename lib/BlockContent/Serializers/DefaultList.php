<?php

namespace Sanity\BlockContent\Serializers;

class DefaultList
{
    public function __invoke($list)
    {
        $style = $list['itemStyle'] ?? 'default';
        $tagName = $style === 'number' ? 'ol' : 'ul';
        return '<' . $tagName . '>' . implode('', $list['children']) . '</' . $tagName . '>';
    }
}

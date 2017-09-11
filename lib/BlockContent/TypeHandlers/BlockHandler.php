<?php
namespace Sanity\BlockContent\TypeHandlers;

class BlockHandler
{
    public function __invoke($block, $treeBuilder)
    {
        return [
            'type' => 'block',
            'style' => isset($block['style']) ? $block['style'] : 'normal',
            'content' => isset($block['children']) ? $treeBuilder->parseSpans($block['children'], $block) : [],
        ];
    }
}

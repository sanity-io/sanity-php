<?php
namespace Sanity\BlockContent\TypeHandlers;

class BlockHandler
{
    public function __invoke($block, $treeBuilder) {
        return [
            'type' => 'block',
            'style' => $block['style'],
            'content' => isset($block['spans']) ? $treeBuilder->parseSpans($block['spans']) : [],
        ];
    }
}

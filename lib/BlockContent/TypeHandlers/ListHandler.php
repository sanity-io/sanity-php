<?php

namespace Sanity\BlockContent\TypeHandlers;

class ListHandler
{
    public function __invoke($blocks, $treeBuilder)
    {
        $mapItems = function ($item) use ($treeBuilder) {
            return $treeBuilder->typeHandlers['block']($item, $treeBuilder);
        };

        return [
            'type' => 'list',
            'itemStyle' => $blocks[0]['listItem'] ?? '',
            'items' => array_map($mapItems, $blocks),
        ];
    }
}

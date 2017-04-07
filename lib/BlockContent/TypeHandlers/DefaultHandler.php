<?php
namespace Sanity\BlockContent\TypeHandlers;

class DefaultHandler
{
    public function __invoke($item, $treeBuilder)
    {
        $type = $item['_type'];
        $attributes = $item;
        unset($attributes['_type']);

        return [
            'type' => $type,
            'attributes' => $attributes,
        ];
    }
}

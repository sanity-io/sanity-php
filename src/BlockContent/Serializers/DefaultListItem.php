<?php
namespace Sanity\BlockContent\Serializers;

class DefaultListItem
{
    public function __invoke($item)
    {
        return '<li>' . implode('', $item['children']) . '</li>';
    }
}

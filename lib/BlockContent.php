<?php
namespace Sanity;

use Sanity\BlockContent\TreeBuilder;

class BlockContent
{
    public static function toTree($content)
    {
        $treeBuilder = new TreeBuilder();
        return $treeBuilder->build($content);
    }

    public static function toHtml($content)
    {
        $tree = $this->isTree($content) ? $content : static::toTree($content);
    }

    public static function isTree($tree)
    {
        return !isset($tree['_type']);
    }
}

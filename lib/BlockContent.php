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
}

<?php
namespace Sanity;

use Sanity\BlockContent\TreeBuilder;
use Sanity\BlockContent\HtmlBuilder;

class BlockContent
{
    public static function toTree($content)
    {
        $treeBuilder = new TreeBuilder();
        return $treeBuilder->build($content);
    }

    public static function toHtml($content, $options = [])
    {
        $htmlBuilder = new HtmlBuilder($options);
        $tree = static::isTree($content) ? $content : static::toTree($content);
        return $htmlBuilder->build($tree);
    }

    public static function isTree($tree)
    {
        return !isset($tree['_type']);
    }
}

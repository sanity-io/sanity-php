<?php
namespace Sanity;

use Sanity\Exception\InvalidArgumentException;
use Sanity\BlockContent\TreeBuilder;
use Sanity\BlockContent\HtmlBuilder;

class BlockContent
{
    public static $useStaticKeys = false;

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
        return !isset($tree['_type']) && !isset($tree[0]['_type']);
    }

    public static function migrate($content, $options = [])
    {
        if (isset($content['_type'])) {
            return self::migrateBlock($content, $options);
        }

        if (is_array($content) && isset($content[0]['_type'])) {
            return array_map(function ($block) use ($options) {
                return self::migrateBlock($block, $options);
            }, $content);
        }

        throw new InvalidArgumentException('Unrecognized data structure');
    }

    public static function migrateBlock($content, $options = [])
    {
        $defaults = ['version' => 2];

        $options = array_merge($defaults, $options);
        $keyGenerator = self::$useStaticKeys
            ? function ($item) {
                return substr(md5(serialize($item)), 0, 7);
            }
            : function () {
                return uniqid();
            };

        if ($options['version'] != 2) {
            throw new InvalidArgumentException('Unsupported version');
        }

        // We only support v1 to v2 for now, so no need for a switch
        if (!isset($content['spans'])) {
            return $content;
        }

        $migrated = $content;
        $markDefs = [];
        $migrated['children'] = array_map(
            function ($child) use (&$markDefs, $keyGenerator) {
                $knownKeys = ['_type', 'text', 'marks'];
                $unknownKeys = array_diff(array_keys($child), $knownKeys);

                foreach ($unknownKeys as $key) {
                    $markKey = $keyGenerator($child[$key]);
                    $child['marks'][] = $markKey;
                    $markDefs[] = array_merge($child[$key], [
                        '_key' => $markKey,
                        '_type' => $key,
                    ]);

                    unset($child[$key]);
                }

                return $child;
            },
            $content['spans']
        );

        $migrated['markDefs'] = $markDefs;
        unset($migrated['spans']);

        return $migrated;
    }
}

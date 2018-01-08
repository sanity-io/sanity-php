<?php
namespace Sanity\BlockContent;

use Sanity\Exception\ConfigException;

class HtmlBuilder
{
    private $serializers;
    private $charset;
    private $projectId;
    private $dataset;
    private $imageOptions;

    public function __construct($options = [])
    {
        $serializers = isset($options['serializers']) ? $options['serializers'] : [];
        $this->serializers = array_replace_recursive($this->getDefaultSerializers(), $serializers);
        $this->charset = isset($options['charset']) ? $options['charset'] : 'utf-8';
        $this->imageOptions = isset($options['imageOptions']) ? $options['imageOptions'] : [];
        $this->projectId = isset($options['projectId']) ? $options['projectId'] : null;
        $this->dataset = isset($options['dataset']) ? $options['dataset'] : null;
    }

    public function build($content, $parent = null)
    {
        if (is_string($content)) {
            return $this->escape($content);
        }

        $nodes = isset($content['type']) ? [$content] : $content;
        $html = '';
        foreach ($nodes as $node) {
            $children = [];
            $content = [];
            if (isset($node['content'])) {
                $content = $node['content'];
            } elseif (isset($node['items'])) {
                $content = $this->wrapInListItems($node['items']);
            }

            foreach ($content as $child) {
                $children[] = $this->build($child, $node);
            }

            $values = $node;
            $values['children'] = $children;

            if (!isset($this->serializers[$node['type']])) {
                throw new ConfigException('No serializer registered for node type "' . $node['type'] . '"');
            }

            $serializer = $this->serializers[$node['type']];
            $serialized = call_user_func($serializer, $values, $parent, $this);
            $html .= $serialized;
        }

        return $html;
    }

    public function escape($string, $charset = null)
    {
        $charset = $charset ?: $this->charset;
        return Escaper::escape($string, $charset);
    }

    public function __invoke($content)
    {
        return $this->build($content);
    }

    public function getMarkSerializer($mark)
    {
        $markName = isset($mark['_type']) ? $mark['_type'] : $mark;

        return isset($this->serializers['marks'][$markName])
            ? $this->serializers['marks'][$markName]
            : null;
    }

    public function getImageOptions()
    {
        return $this->imageOptions;
    }

    public function getProjectId()
    {
        return $this->projectId;
    }

    public function getDataset()
    {
        return $this->dataset;
    }

    private function wrapInListItems($items)
    {
        return array_map(function ($item) {
            return ['type' => 'listItem', 'content' => [$item]];
        }, $items);
    }

    private function getDefaultSerializers()
    {
        return [
            'block' => new Serializers\DefaultBlock(),
            'list' => new Serializers\DefaultList(),
            'listItem' => new Serializers\DefaultListItem(),
            'span' => new Serializers\DefaultSpan(),
            'image' => new Serializers\DefaultImage(),
            'marks' => [
                'em' => 'em',
                'code' => 'code',
                'strong' => 'strong',
                'underline' => [
                    'head' => '<span style="text-decoration: underline;">',
                    'tail' => '</span>',
                ],
                'link' => [
                    'head' => function ($mark) {
                        return '<a href="' . $mark['href'] . '">';
                    },
                    'tail' => '</a>'
                ]
            ]
        ];
    }
}

<?php
namespace Sanity\BlockContent;

class Node
{
    public $type;
    public $mark;
    public $markKey;
    public $content = [];

    public function __construct($node = null)
    {
        if (!$node) {
            return;
        }

        $this->type = $node['type'];
        $this->mark = $node['mark'];
        $this->markKey = $node['markKey'];
        $this->content = $node['content'];
    }

    public function addContent($node)
    {
        $this->content[] = $node;
        return $this;
    }

    public function serialize()
    {
        $node = [];

        if ($this->type) {
            $node['type'] = $this->type;
        }

        if ($this->mark) {
            $node['mark'] = $this->mark;
        }

        if (!empty($this->content)) {
            $node['content'] = array_map(function ($child) {
                return $child instanceof Node ? $child->serialize() : $child;
            }, $this->content);
        }

        return $node;
    }
}

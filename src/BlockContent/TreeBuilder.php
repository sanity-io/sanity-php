<?php
namespace Sanity\BlockContent;

use Sanity\BlockContent;

class TreeBuilder
{
    public $typeHandlers = [];

    public function __construct()
    {
        $this->typeHandlers = [
            'block' => new TypeHandlers\BlockHandler(),
            'list' => new TypeHandlers\ListHandler(),
            'default' => new TypeHandlers\DefaultHandler(),
        ];
    }

    public function __invoke($content)
    {
        return $this->build($content);
    }

    public function build($content)
    {
        $content = BlockContent::migrate($content);
        $isArray = !isset($content['_type']);
        return $isArray
            ? $this->parseArray($content)
            : $this->parseBlock($content);
    }

    public function parseSpans($spans, $parent)
    {
        $unwantedKeys = array_flip(['_type', 'text', 'marks']);

        $nodeStack = [new Node()];

        foreach ($spans as $span) {
            $attributes = array_diff_key($span, $unwantedKeys);
            $marksAsNeeded = $span['marks'];
            sort($marksAsNeeded);

            $stackLength = count($nodeStack);
            $pos = 1;

            // Start at position one. Root is always plain and should never be removed. (?)
            if ($stackLength > 1) {
                for (; $pos < $stackLength; $pos++) {
                    $mark = $nodeStack[$pos]->markKey;
                    $index = array_search($mark, $marksAsNeeded);

                    if ($index === false) {
                        break;
                    }

                    unset($marksAsNeeded[$index]);
                }
            }

            // Keep from beginning to first miss
            $nodeStack = array_slice($nodeStack, 0, $pos);

            // Add needed nodes
            $nodeIndex = count($nodeStack) - 1;
            foreach ($marksAsNeeded as $mark) {
                $node = new Node([
                    'content' => [],
                    'mark' => $this->findMark($mark, $parent),
                    'markKey' => $mark,
                    'type' => 'span',
                ]);

                $nodeStack[$nodeIndex]->addContent($node);
                $nodeStack[] = $node;
                $nodeIndex++;
            }

            if (empty($attributes)) {
                $nodeStack[$nodeIndex]->addContent($span['text']);
            } else {
                $nodeStack[$nodeIndex]->addContent([
                    'type' => 'span',
                    'attributes' => $attributes,
                    'content' => [$span['text']],
                ]);
            }
        }

        $serialized = $nodeStack[0]->serialize();
        return $serialized['content'];
    }

    private function findMark($mark, $parent)
    {
        $markDefs = isset($parent['markDefs']) ? $parent['markDefs'] : [];
        foreach ($markDefs as $markDef) {
            if (isset($markDef['_key']) && $markDef['_key'] === $mark) {
                return $markDef;
            }
        }

        return $mark;
    }

    public function parseArray($blocks)
    {
        $parsedData = [];
        $listBlocks = [];
        foreach ($blocks as $index => $block) {
            if (!$this->isList($block)) {
                $parsedData[] = $this->parseBlock($block);
                continue;
            }

            // Each item in a list comes in its own block.
            // We bundle these together in a single list object
            $listBlocks[] = $block;
            $nextBlock = isset($blocks[$index + 1]) ? $blocks[$index + 1] : null;

            // If next block is not a similar list object, this list is complete
            if (!isset($nextBlock['listItem']) || ($nextBlock['listItem'] !== $block['listItem'])) {
                $parsedData[] = $this->typeHandlers['list']($listBlocks, $this);
                $listBlocks = [];
            }
        }

        return $parsedData;
    }

    public function parseBlock($block)
    {
        $type = $block['_type'];
        $typeHandler = isset($this->typeHandlers[$type])
            ? $this->typeHandlers[$type]
            : $this->typeHandlers['default'];

        return $typeHandler($block, $this);
    }

    public function isList($item)
    {
        $type = isset($item['_type']) ? $item['_type'] : null;
        $listItem = isset($item['listItem']);
        return $type === 'block' && $listItem;
    }
}

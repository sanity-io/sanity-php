<?php
namespace Sanity\BlockContent\Serializers;

class DefaultSpan
{
    public function __invoke($span, $parent, $htmlBuilder)
    {
        $head = '';
        $tail = '';
        $markTag = isset($span['mark']) ? $htmlBuilder->getMarkSerializer($span['mark']) : null;
        if ($markTag) {
            $head .= is_array($markTag) ? $markTag['head'] : '<' . $markTag . '>';
            $tail .= is_array($markTag) ? $markTag['tail'] : '</' . $markTag . '>';
        }

        if (isset($span['attributes']['link']['href'])) {
            $head .= '<a href="' . $span['attributes']['link']['href'] . '">';
            $tail = '</a>' . $tail;
        }

        return $head . implode('', $span['children']) . $tail;
    }
}

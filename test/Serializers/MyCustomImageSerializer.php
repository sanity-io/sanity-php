<?php
namespace SanityTest\Serializers;

use Sanity\BlockContent\Serializers\DefaultImage;

class MyCustomImageSerializer extends DefaultImage {
    public function __invoke($item, $parent, $htmlBuilder)
    {
        $caption = $item['attributes']['caption'] ?? false;
        $url = $this->getImageUrl($item, $htmlBuilder);
        $html = '<figure>';
        $html .= '<img src="' . $url . '" />';
        $html .= $caption ? '<figcaption>' . $htmlBuilder->escape($caption) . '</figcaption>' : '';
        $html .= '</figure>';
        return $html;
    }
}

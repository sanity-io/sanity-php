<?php
namespace Sanity\BlockContent\Serializers;

use Sanity\Exception\ConfigException;

class DefaultImage
{
    public function __invoke($item, $parent, $htmlBuilder)
    {
        $url = $this->getImageUrl($item, $htmlBuilder);
        return '<figure><img src="' . $url . '" /></figure>';
    }

    protected function getImageUrl($item, $htmlBuilder)
    {
        $helpUrl = 'https://github.com/sanity-io/sanity-php#rendering-block-content';

        $projectId = $htmlBuilder->getProjectId();
        $dataset = $htmlBuilder->getDataset();
        $imageOptions = $htmlBuilder->getImageOptions();

        $node = $item['attributes'];
        $asset = isset($node['asset']) ? $node['asset'] : null;

        if (!$asset) {
            throw new ConfigException('Image does not have required `asset` property');
        }

        $qs = http_build_query($imageOptions);
        if (!empty($qs)) {
            $qs = '?' . $qs;
        }

        if (isset($asset['url'])) {
            return $asset['url'] . $qs;
        }

        $ref = isset($asset['_ref']) ? $asset['_ref'] : null;
        if (!$ref) {
            throw new ConfigException('Invalid image reference in block, no `_ref` found on `asset`');
        }

        if (!$projectId || !$dataset) {
            throw new ConfigException(
                '`projectId` and/or `dataset` missing from block content config, see ' . $helpUrl
            );
        }

        $parts = explode('-', $ref);
        $url = 'https://cdn.sanity.io/'
            . $parts[0] . 's/' // Asset type, pluralized
            . $projectId . '/'
            . $dataset  . '/'
            . $parts[1] . '-'  // Asset ID
            . $parts[2] . '.'  // Dimensions
            . $parts[3]        // File extension
            . $qs;             // Query string

        return $url;
    }
}

<?php
namespace SanityTest;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for Sanity test cases, provides utility methods and shared logic.
 */
class TestCase extends BaseTestCase
{
    public function loadFixture($fixtureName)
    {
        $content = file_get_contents(__DIR__ . '/fixtures/' . $fixtureName);
        return json_decode($content, true);
    }
}

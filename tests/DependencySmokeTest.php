<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\CarveConverter;
use MediaEmbed\MediaEmbed;
use PHPUnit\Framework\TestCase;

class DependencySmokeTest extends TestCase
{
    /**
     * @return void
     */
    public function testDependenciesAutoload(): void
    {
        $this->assertTrue(class_exists(CarveConverter::class));
        $this->assertTrue(class_exists(MediaEmbed::class));
    }

    /**
     * @return void
     */
    public function testCarveConvertsPlainText(): void
    {
        $converter = new CarveConverter();
        $html = $converter->convert('Hello');
        $this->assertStringContainsString('Hello', $html);
    }
}

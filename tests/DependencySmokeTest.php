<?php

declare(strict_types=1);

namespace MarkupCarve\MediaEmbed\Test;

use MarkupCarve\Carve\Carve;
use MediaEmbed\MediaEmbed;
use PHPUnit\Framework\TestCase;

class DependencySmokeTest extends TestCase
{
    /**
     * @return void
     */
    public function testDependenciesAutoload(): void
    {
        $this->assertTrue(class_exists(Carve::class));
        $this->assertTrue(class_exists(MediaEmbed::class));
    }

    /**
     * @return void
     */
    public function testCarveConvertsPlainText(): void
    {
        $html = Carve::render('Hello');
        $this->assertStringContainsString('Hello', $html);
    }
}

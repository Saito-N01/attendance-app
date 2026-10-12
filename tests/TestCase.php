<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 画面を描画するテストで、ビルド済みのVite資産（public/build）が無くても動くようにする
        $this->withoutVite();
    }
}

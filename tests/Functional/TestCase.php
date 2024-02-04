<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase as BaseTestCase;

#[Group('phplrt/compiler'), Group('functional')]
abstract class TestCase extends BaseTestCase {}

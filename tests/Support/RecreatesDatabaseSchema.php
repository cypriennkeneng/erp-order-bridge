<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Fresh SQLite schema per test: slower than transactions, but every test
 * starts from a known state and nothing leaks between tests.
 */
trait RecreatesDatabaseSchema
{
    protected static function recreateSchema(EntityManagerInterface $em): void
    {
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected static function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}

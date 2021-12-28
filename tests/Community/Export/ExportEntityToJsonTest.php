<?php

namespace Bouncey\Neo4j\OGM\Tests\Community;

use Bouncey\Neo4j\OGM\Tests\Community\Issue21\TestUser;
use Bouncey\Neo4j\OGM\Tests\Integration\IntegrationTestCase;

/**
 * Class ExportEntityToJsonTest
 * @package Bouncey\Neo4j\OGM\Tests\Community
 *
 * @group export
 */
class ExportEntityToJsonTest extends IntegrationTestCase
{
    public function testBasicEntityCanBeSerializedToJson()
    {
        $this->clearDb();
        $user = new TestUser("me");
        $this->em->persist($user);
        $this->em->flush();

        $this->em->clear();
        $repository = $this->em->getRepository(TestUser::class);
        $all = $repository->findAll();

        $json = json_encode($all);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('id', $decoded[0]);
        $this->assertArrayHasKey('name', $decoded[0]);
    }
}

<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\OGM\Tests\Integration;

use GraphAware\Neo4j\OGM\Tests\Integration\Models\RelationshipCollection\Building;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\RelationshipCollection\Floor;

/**
 * Class EntityWithSimpleRelationshipCollectionTest.
 *
 * @group entity-simple-relcollection
 */
class EntityWithSimpleRelationshipCollectionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearDb();
    }

    public function testBuildingCanBeCreated()
    {
        $building = new Building();
        $this->em->persist($building);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building) RETURN n');
        $this->assertSame(1, $result->count());
    }

    public function testBuildingWithFloorsCanBeCreated()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building)-[:HAS_FLOOR]->(f:Floor {level: 1}) RETURN n, f');
        $this->assertSame(1, $result->count());
    }

    public function testBuildingWithFloorsCanBeLoaded()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $this->em->clear();

        $entities = $this->em->getRepository(Building::class)->findAll();
        /** @var Building $b */
        $b = $entities[0];
        $this->assertInstanceOf(Building::class, $b);
        $floors = $b->getFloors();
        $this->assertCount(1, $floors);
        /** @var Floor $floor */
        $floor = $floors[0];
        $this->assertSame(spl_object_hash($b), spl_object_hash($floor->getBuilding()));
    }

    public function testBuildingWithFloorsCanAddFloorWithoutClear()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $floor2 = new Floor(2);
        $building->getFloors()->add($floor2);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building)-[:HAS_FLOOR]->(f:Floor) RETURN n, f');
        $this->assertSame(2, $result->count());
    }

    public function testBuildingWithFloorsCanAddFloorWithClear()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $this->em->clear();

        $entities = $this->em->getRepository(Building::class)->findAll();
        /** @var Building $building */
        $building = $entities[0];
        $floor2 = new Floor(2);
        $building->getFloors()->add($floor2);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building)-[:HAS_FLOOR]->(f:Floor) RETURN n, f');
        $this->assertSame(2, $result->count());
    }

    public function testBuildingCanBeRetrievedFromFloor()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $this->em->clear();

        /** @var Floor[] $floors */
        $floors = $this->em->getRepository(Floor::class)->findAll();

        foreach ($floors as $floor) {
            $buildingFromFloor = $floor->getBuilding();
            $this->assertInstanceOf(Building::class, $buildingFromFloor);
            $this->assertSame($buildingFromFloor->getId(), $building->getId());
        }
    }

    public function testFloorLevelCanBeChangedWithoutClear()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $floor2 = new Floor(2);
        $building->getFloors()->add($floor2);
        $this->em->flush();
        $floor2->setLevel(5);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building)-[:HAS_FLOOR]->(f:Floor {level: 5}) RETURN n, f');
        $this->assertSame(1, $result->count());
    }

    /**
     * @group lazy
     */
    public function testFloorLevelCanBeChangedWithClear()
    {
        $building = new Building();
        $floor1 = new Floor(1);
        $building->getFloors()->add($floor1);
        $this->em->persist($building);
        $this->em->flush();
        $this->em->clear();

        $entities = $this->em->getRepository(Building::class)->findAll();
        /** @var Building $building */
        $building = $entities[0];
        $floor1 = $building->getFloors()->get(0);
        $floor1->setLevel(5);
        $floor2 = new Floor(2);
        $building->getFloors()->add($floor2);
        $this->em->flush();

        $result = $this->client->run('MATCH (n:Building)-[:HAS_FLOOR]->(f:Floor {level: 5}) RETURN n, f');
        $this->assertSame(1, $result->count());
    }
}

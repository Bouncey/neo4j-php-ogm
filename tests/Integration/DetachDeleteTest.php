<?php

namespace GraphAware\Neo4j\OGM\Tests\Integration;

use Laudis\Neo4j\Exception\Neo4jException;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\MoviesDemo\Person;

/**
 *
 * @group detach-delete-it
 */
class DetachDeleteTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->playMovies();
    }

    public function testRemovingEntityWithoutDetachDeleteThrowsException()
    {
        $actor = $this->em->getRepository(Person::class)->findOneBy(['name' => 'Al Pacino']);
        $this->em->remove($actor);
        $this->expectException(Neo4jException::class);
        $this->em->flush();
    }

    public function testExceptionMessageIsOk()
    {
        $actor = $this->em->getRepository(Person::class)->findOneBy(['name' => 'Al Pacino']);
        $this->em->remove($actor);
        $exceptionMessage = null;
        try {
            $this->em->flush();
        } catch (Neo4jException $e) {
            $exceptionMessage = $e->getMessage();
        }
        $this->assertNotNull($exceptionMessage);
        echo $exceptionMessage . PHP_EOL;
        $this->assertTrue(strpos($exceptionMessage, 'still has relationships') !== false);
    }

    public function testCanDetachDeleteWithEntityManagerRemove()
    {
        $actor = $this->em->getRepository(Person::class)->findOneBy(['name' => 'Al Pacino']);
        $this->em->remove($actor, true);
        $this->em->flush();
        $this->assertGraphNotExist('(p:Person {name:"Al Pacino"})');
    }

    public function testCanDetachDeleteByReferenceRemoval()
    {
        // remove the DIRECTED relationship from Tom Hanks to simulate the use case
        $this->client->run('MATCH (n:Person {name:"Tom Hanks"})-[r:DIRECTED]->() DELETE r');
        /** @var Person $actor */
        $actor = $this->em->getRepository(Person::class)->findOneBy(['name' => 'Tom Hanks']);
        foreach ($actor->getMovies() as $movie) {
            $movie->getActors()->removeElement($actor);
        }
        $actor->getMovies()->clear();
        $this->em->remove($actor);
        $this->em->flush();
        $this->assertGraphNotExist('(p:Person {name:"Tom Hanks"})');
    }
}

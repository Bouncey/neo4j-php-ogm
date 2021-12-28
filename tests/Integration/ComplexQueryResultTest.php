<?php

namespace GraphAware\Neo4j\OGM\Tests\Integration;

use GraphAware\Neo4j\OGM\Query;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\MoviesDemo\Movie;
use GraphAware\Neo4j\OGM\Tests\Integration\Models\MoviesDemo\Person;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @group complex-query
 */
class ComplexQueryResultTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearDb();
        $this->playMovies();
    }

    public function testQueryReturningMap()
    {
        $q = $this->em->createQuery('MATCH (n:Person)-[r:ACTED_IN]->(m) RETURN n, {movie: m, roles: r.roles} AS actInfo LIMIT 2');

        $q->addEntityMapping('n', Person::class);
        $q->addEntityMapping('actInfo', null, Query::HYDRATE_MAP);
        $q->addEntityMapping('movie', Movie::class);

        $result = $q->getResult();
        $this->assertCount(2, $result);
        $row = $result[0];


        /** @var CypherMap $actorInfo */
        $actorInfo = $row['actInfo'];
        $this->assertInstanceOf(Person::class, $row['n']);
        $this->assertInstanceOf(CypherMap::class, $actorInfo);
        $this->assertTrue($actorInfo->hasKey('movie'));
        $this->assertTrue($actorInfo->hasKey('roles'));
        $this->assertInstanceOf(Movie::class, $actorInfo->get('movie'));
        $this->assertInstanceOf(CypherList::class, $actorInfo->get('roles'));
    }

    public function testQueryReturningMapCollectionMixed()
    {
        $this->clearDb();
        $this->playMovies();
        $q = $this->em->createQuery('MATCH (n:Person {name:"Tom Hanks"})-[r:ACTED_IN]->(m) 
        WITH n, {roles: r.roles, movie: m} AS actInfo 
        RETURN n, collect(actInfo) AS actorInfos LIMIT 2');

        $q->addEntityMapping('n', Person::class);
        $q->addEntityMapping('actorInfos', null, Query::HYDRATE_MAP_COLLECTION);
        $q->addEntityMapping('movie', Movie::class);

        $result = $q->getResult();
        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['actorInfos']);
        $this->assertInstanceOf(Movie::class, $result[0]['actorInfos'][0]['movie']);
        $this->assertCount(12, $result[0]['actorInfos']);
    }

    public function testQueryReturningCollectionOfEntitiesInMap()
    {
        $q = $this->em->createQuery('MATCH (n:Person)-[r:ACTED_IN]->(m) 
        RETURN n, {score: size((n)-[:ACTED_IN]->()), movies: collect(m)} AS infos LIMIT 10');

        $q->addEntityMapping('n', Person::class);
        $q->addEntityMapping('infos', null, Query::HYDRATE_MAP);
        $q->addEntityMapping('movies', Movie::class, Query::HYDRATE_COLLECTION);

        $result = $q->getResult();

        $this->assertCount(10, $result);
        $row = $result[0];
        /** @var CypherMap $actorInfo */
        $infos = $row['infos'];
        $this->assertInstanceOf(Person::class, $row['n']);
        $this->assertInstanceOf(CypherMap::class, $infos);
        $this->assertEquals($infos->get('score'), count($infos->get('movies')));
        foreach ($infos->get('movies') as $movie) {
            $this->assertInstanceOf(Movie::class, $movie);
        }
    }

    public function testQueryReturningMapAsOnlyColumn()
    {
        $q = $this->em->createQuery('MATCH (n:Person)-[r:ACTED_IN]->(m) 
        RETURN {user: n, score: size((n)-[:ACTED_IN]->()), movies: collect(m)} AS infos LIMIT 10');

        $q->addEntityMapping('user', Person::class);
        $q->addEntityMapping('infos', null, Query::HYDRATE_MAP);
        $q->addEntityMapping('movies', Movie::class, Query::HYDRATE_COLLECTION);

        $result = $q->execute();

        $this->assertCount(10, $result);
        $row = $result[0];
        /** @var CypherMap $actorInfo */
        $infos = $row['infos'];
        $this->assertEquals(1, count(array_keys($row)));
        $this->assertInstanceOf(Person::class, $infos->get('user'));
        $this->assertEquals($infos->get('score'), count($infos->get('movies')));
        foreach ($infos->get('movies') as $movie) {
            $this->assertInstanceOf(Movie::class, $movie);
        }
    }
}

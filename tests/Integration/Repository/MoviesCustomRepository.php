<?php

namespace Bouncey\Neo4j\OGM\Tests\Integration\Repository;

use Bouncey\Neo4j\OGM\Repository\BaseRepository;
use Bouncey\Neo4j\OGM\Tests\Integration\Models\MoviesDemo\Movie;

class MoviesCustomRepository extends BaseRepository
{
    public function findAllWithScore()
    {
        $query = $this->entityManager->createQuery('MATCH (n:Movie) RETURN n, size((n)<-[:ACTED_IN]-()) AS score ORDER BY score DESC');
        $query->addEntityMapping('n', Movie::class);

        return $query->getResult();
    }
}

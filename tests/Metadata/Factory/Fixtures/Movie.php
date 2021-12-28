<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bouncey\Neo4j\OGM\Tests\Metadata\Factory\Fixtures;

use Bouncey\Neo4j\OGM\Annotations as OGM;

/**
 * @OGM\Node(label="Movie",repository="Bouncey\Neo4j\OGM\Tests\Metadata\Factory\Fixtures\MovieRepository")
 */
class Movie
{
    /**
     * @OGM\GraphId()
     */
    private $id;

    /**
     * @OGM\Property(type="string",nullable=true)
     *
     * @var string
     */
    private $name;

    /**
     * @OGM\Relationship(type="ACTED_IN", direction="OUTGOING", targetEntity="Person", collection=true)
     *
     * @var Person[]
     */
    private $actors;
}

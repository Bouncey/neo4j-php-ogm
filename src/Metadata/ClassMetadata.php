<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bouncey\Neo4j\OGM\Metadata;

use Bouncey\Neo4j\OGM\Annotations\Entity;
use Bouncey\Neo4j\OGM\Annotations\Node;
use Bouncey\Neo4j\OGM\Annotations\RelationshipEntity;
use Bouncey\Neo4j\OGM\Exception\MappingException;

final class ClassMetadata
{
    /**
     * @var \Bouncey\Neo4j\OGM\Annotations\Entity|\Bouncey\Neo4j\OGM\Annotations\Node|\Bouncey\Neo4j\OGM\Annotations\RelationshipEntity
     */
    protected $entityAnnotation;

    /**
     * @param \Bouncey\Neo4j\OGM\Annotations\Entity $entityAnnotation
     */
    public function __construct(Entity $entityAnnotation)
    {
        $this->entityAnnotation = $entityAnnotation;
    }

    /**
     * @return bool
     */
    public function isNodeEntity()
    {
        return $this->entityAnnotation instanceof Node;
    }

    /**
     * @return bool
     */
    public function isRelationshipEntity()
    {
        return $this->entityAnnotation instanceof RelationshipEntity;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        if (!$this->isNodeEntity()) {
            throw new MappingException(sprintf('This class metadata is not for a node entity'));
        }

        return $this->entityAnnotation->label;
    }

    /**
     * @return string
     */
    public function getRelationshipType()
    {
        if (!$this->isRelationshipEntity()) {
            throw new MappingException(sprintf('This class metadata is not for a relationship entity'));
        }

        return $this->entityAnnotation->type;
    }
}

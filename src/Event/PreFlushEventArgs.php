<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bouncey\Neo4j\OGM\Event;

use Doctrine\Common\EventArgs;
use Bouncey\Neo4j\OGM\EntityManager;

class PreFlushEventArgs extends EventArgs
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * PreFlushEventArgs constructor.
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }
}

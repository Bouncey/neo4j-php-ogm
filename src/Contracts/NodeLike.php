<?php

namespace GraphAware\Neo4j\OGM\Contracts;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * A Node class representing a Node in cypher.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 */
interface NodeLike {
    public function id(): int;
    public function getId(): int;

    public function labels(): CypherList;
    public function getLabels(): CypherList;

    public function properties(): CypherMap;
    public function getProperties(): CypherMap;

    /**
     * @return OGMTypes
     */
    public function getProperty(string $key);

    /**
     * @return array{id: int, labels: CypherList<string>, properties: CypherMap<OGMTypes>}
     */
    public function toArray(): array;
}

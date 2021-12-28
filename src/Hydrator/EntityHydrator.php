<?php

/*
 * This file is part of the GraphAware Neo4j PHP OGM package.
 *
 * (c) GraphAware Ltd <info@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bouncey\Neo4j\OGM\Hydrator;

use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Bouncey\Neo4j\OGM\Common\Collection;
use Bouncey\Neo4j\OGM\Enum\Direction;
use Bouncey\Neo4j\OGM\Converters\Converter;
use Bouncey\Neo4j\OGM\EntityManager;
use Bouncey\Neo4j\OGM\Exception\MappingException;
use Bouncey\Neo4j\OGM\Metadata\NodeEntityMetadata;
use Bouncey\Neo4j\OGM\Metadata\RelationshipEntityMetadata;
use Bouncey\Neo4j\OGM\Util\DirectionUtils;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;

class EntityHydrator
{
    /**
     * @var EntityManager
     */
    private $_em;

    /**
     * @var NodeEntityMetadata class meta data of the entity that is to be hydrated by this instance.
     */
    private $_classMetadata;

    public function __construct($className, EntityManager $em)
    {
        $this->_em = $em;
        $this->_classMetadata = $this->_em->getClassMetadataFor($className);
    }

    /**
     * @param Result $dbResult
     *
     * @return array
     */
    public function hydrateAll(SummarizedResult $dbResult)
    {
        $result = [];

        foreach ($dbResult as $results) {
            $this->hydrateRecord($results, $result);
        }

        return $result;
    }

    /**
     * @param Result $dbResult
     * @param object $sourceEntity
     * @param mixed  $alias
     */
    public function hydrateSimpleRelationship($alias, SummarizedResult $dbResult, $sourceEntity)
    {
        if (0 === $dbResult->count()) {
            return;
        }

        $relationshipMetadata = $this->_classMetadata->getRelationship($alias);
        $targetHydrator = $this->_em->getEntityHydrator($relationshipMetadata->getTargetEntity());
        $targetMeta = $this->_em->getClassMetadataFor($relationshipMetadata->getTargetEntity());
        $hydrated = $targetHydrator->hydrateAll($dbResult);

        $o = $hydrated[0];
        $relationshipMetadata->setValue($sourceEntity, $o);

        $mappedBy = $relationshipMetadata->getMappedByProperty();
        if ($mappedBy) {
            $targetRel = $targetMeta->getRelationship($mappedBy);
            if ($targetRel->isCollection()) {
                $targetRel->addToCollection($o, $sourceEntity);
            } else {
                $targetRel->setValue($o, $sourceEntity);
            }
        }
        $this->_em->getUnitOfWork()->addManagedRelationshipReference($sourceEntity, $o, $relationshipMetadata->getPropertyName(), $relationshipMetadata);
    }

    public function hydrateSimpleRelationshipCollection($alias, SummarizedResult $dbResult, $sourceEntity)
    {
        $relationshipMetadata = $this->_classMetadata->getRelationship($alias);
        $this->initRelationshipCollection($alias, $sourceEntity);
        /** @var Collection $coll */
        $coll = $relationshipMetadata->getValue($sourceEntity);
        $targetHydrator = $this->_em->getEntityHydrator($relationshipMetadata->getTargetEntity());
        $targetMeta = $this->_em->getClassMetadataFor($relationshipMetadata->getTargetEntity());
        foreach ($dbResult as $result) {
            $node = $result->get($targetMeta->getEntityAlias());
            $item = $targetHydrator->hydrateNode($node, $relationshipMetadata->getTargetEntity());
            $coll->add($item);
            $mappedBy = $relationshipMetadata->getMappedByProperty();
            if ($mappedBy) {
                $mappedRel = $targetMeta->getRelationship($mappedBy);
                if ($mappedRel->isCollection()) {
                    $mappedRel->initializeCollection($item);
                    $this->_em->getUnitOfWork()->addManagedRelationshipReference($sourceEntity, $item, $relationshipMetadata->getPropertyName(), $relationshipMetadata);
                } else {
                    $mappedRel->setValue($item, $sourceEntity);
                    $this->_em->getUnitOfWork()->addManagedRelationshipReference($sourceEntity, $item, $relationshipMetadata->getPropertyName(), $relationshipMetadata);
                }
            }
        }
    }

    public function hydrateRelationshipEntity($alias, SummarizedResult $dbResult, $sourceEntity)
    {
        $relationshipMetadata = $this->_classMetadata->getRelationship($alias);
        /** @var RelationshipEntityMetadata $relationshipEntityMetadata */
        $relationshipEntityMetadata = $this->_em->getClassMetadataFor($relationshipMetadata->getRelationshipEntityClass());
        $otherClass = $this->guessOtherClassName($alias);
        $otherMetadata = $this->_em->getClassMetadataFor($otherClass);
        $otherHydrator = $this->_em->getEntityHydrator($otherClass);

        // initialize collection on source entity to avoid it being null
        if ($relationshipMetadata->isCollection()) {
            $relationshipMetadata->initializeCollection($sourceEntity);
        }

        if ($relationshipEntityMetadata->getStartNodeClass() == $relationshipEntityMetadata->getEndNodeClass()) {

            // handle relationships between nodes of the same type

            if ($relationshipMetadata->getDirection() === Direction::OUTGOING) {
                $startNodeIsSourceEntity = true;
            } else if ($relationshipMetadata->getDirection() === Direction::INCOMING) {
                $startNodeIsSourceEntity = false;
            } else {
                throw new MappingException('Invalid Relationship Entity annotations. Direction BOTH not supported on'
                    . 'RelationshipEntities where startNode and endNode are of the same class');
            }
        } else if ($relationshipEntityMetadata->getStartNodeClass() === $this->_classMetadata->getClassName()) {
            $startNodeIsSourceEntity = true;
        } else {
            $startNodeIsSourceEntity = false;
        }

        
        $k = $relationshipMetadata->getAlias();
        foreach ($dbResult as $node) {
            /** @var Relationship $relationship */
            $relationship = $node->get('rel');

            // hydrate the target node :
            $targetEntity = $otherHydrator->hydrateNode($node->get('target'));

            // create the relationship entity
            $entity = $this->_em->getUnitOfWork()->createRelationshipEntity(
                $relationship,
                $relationshipEntityMetadata->getClassName(),
                $sourceEntity,
                $relationshipMetadata->getPropertyName()
            );

            // set properties on the relationship entity
            foreach ($relationshipEntityMetadata->getPropertiesMetadata() as $key => $propertyMetadata) {
                $fieldKey = $key;

                if ($propertyMetadata->getPropertyAnnotationMetadata()->hasCustomKey()) {
                    $fieldKey = $propertyMetadata->getPropertyAnnotationMetadata()->getKey();
                }

                if ($propertyMetadata->hasConverter()) {
                    $converter = Converter::getConverter($propertyMetadata->getConverterType(), $fieldKey);
                    $value = $converter->toPHPValue($relationship->getProperties()->toArray(), $propertyMetadata->getConverterOptions());
                    $propertyMetadata->setValue($entity, $value);
                } else {
                    $v = $relationship->getProperties()->hasKey($fieldKey) ? $relationship->getProperty($fieldKey) : null;
                    $propertyMetadata->setValue($entity, $v);
                }
            }

            // set the start and end node
            if ($startNodeIsSourceEntity) {
                $relationshipEntityMetadata->setStartNodeProperty($entity, $sourceEntity);
                $relationshipEntityMetadata->setEndNodeProperty($entity, $targetEntity);
            } else {
                $relationshipEntityMetadata->setStartNodeProperty($entity, $targetEntity);
                $relationshipEntityMetadata->setEndNodeProperty($entity, $sourceEntity);
            }

            // set the relationship entity on the source entity
            if (!$relationshipMetadata->isCollection()) {
                $relationshipMetadata->setValue($sourceEntity, $entity);
            } else {
                $relationshipMetadata->initializeCollection($sourceEntity);
                $relationshipMetadata->addToCollection($sourceEntity, $entity);
            }

            // detect the name of the property on the other node to populate reverse relation
            foreach ($otherMetadata->getRelationships() as $rel) {
                if ($rel->isRelationshipEntity() && $rel->getRelationshipEntityClass() === $relationshipEntityMetadata->getClassName()) {
                    // if relation direction is not the opposite, do not populate
                    if (($direction = $rel->getDirection()) !== Direction::BOTH && $direction === $relationshipMetadata->getDirection()) {
                        continue;
                    }
                    if (!$rel->isCollection()) {
                        $rel->setValue($targetEntity, $entity);
                    } else {
                        $rel->initializeCollection($targetEntity);
                        $rel->addToCollection($targetEntity, $entity);
                    }
                }
            }
        }
    }

    public function hydrateRecord(CypherMap $record, array &$result, $collection = false)
    {
        $cqlAliasMap = $this->getAliases();

        foreach ($record->keys() as $cqlAlias) {
            $data = $record->get($cqlAlias);
            $entityName = $cqlAliasMap[$cqlAlias];
            $data = $collection ? $data : [$data];
            foreach ($data as $node) {
                $id = $node->getId();

                // Check the entity is not managed yet by the uow
                if (null !== $entity = $this->_em->getUnitOfWork()->getEntityById($id)) {
                    $result[] = $entity;
                    continue;
                }

                // create the entity
                $entity = $this->_em->getUnitOfWork()->createEntity($node, $entityName, $id);
                $this->hydrateProperties($entity, $node);
                $this->hydrateLabels($entity, $node);
                $this->_em->getUnitOfWork()->addManaged($entity);

                $result[] = $entity;
            }
        }
    }

    public function hydrateNode(Node $node, $class = null)
    {
        $cm = null === $class ? $this->_classMetadata->getClassName() : $class;
        $id = $node->getId();

        // Check the entity is not managed yet by the uow
        if (null !== $entity = $this->_em->getUnitOfWork()->getEntityById($id)) {
            return $entity;
        }

        // create the entity
        $entity = $this->_em->getUnitOfWork()->createEntity($node, $cm, $id);
        $this->hydrateProperties($entity, $node);
        $this->hydrateLabels($entity, $node);

        return $entity;
    }

    /**
     * @param Node $node
     * @param object $entity
     */
    public function refresh(Node $node, $entity)
    {
        $this->hydrateProperties($entity, $node);
        $this->hydrateLabels($entity, $node);
    }

    protected function hydrateProperties($object, Node $node)
    {
        foreach ($this->_classMetadata->getPropertiesMetadata() as $key => $metadata) {
            $fieldKey = $key;

            if ($metadata->getPropertyAnnotationMetadata()->hasCustomKey()) {
                $fieldKey = $metadata->getPropertyAnnotationMetadata()->getKey();
            }

            if ($metadata->hasConverter()) {
                $converter = Converter::getConverter($metadata->getConverterType(), $fieldKey);
                $value = $converter->toPHPValue($node->getProperties()->toArray(), $metadata->getConverterOptions());
                $metadata->setValue($object, $value);
            } else {
                $v = $node->getProperties()->hasKey($fieldKey) ? $node->getProperty($fieldKey) : null;
                $metadata->setValue($object, $v);
            }
        }
    }

    protected function hydrateLabels($object, Node $node)
    {
        foreach ($this->_classMetadata->getLabeledProperties() as $labeledProperty) {
            if ($node->getLabels()->hasValue($labeledProperty->getLabelName())) {
                $labeledProperty->setLabel($object, true);
            } else {
                $labeledProperty->setLabel($object, false);
            }
        }
    }

    protected function getAliases()
    {
        return [$this->_classMetadata->getEntityAlias() => $this->_classMetadata->getClassName()];
    }

    private function guessOtherClassName($alias)
    {
        $relationshipMetadata = $this->_classMetadata->getRelationship($alias);
        /** @var RelationshipEntityMetadata $relationshipEntityMetadata */
        $relationshipEntityMetadata = $this->_em->getClassMetadataFor($relationshipMetadata->getRelationshipEntityClass());
        $inversedSide = $relationshipEntityMetadata->getOtherClassNameForOwningClass($this->_classMetadata->getClassName());
        /* @todo will not work for Direction.BOTH */
        return $inversedSide;
    }

    private function initRelationshipCollection($alias, $sourceEntity)
    {
        $this->_classMetadata->getRelationship($alias)->initializeCollection($sourceEntity);
    }
}

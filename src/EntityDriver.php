<?php

namespace Kerasai\DrupalEntityDriver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Work with Drupal's entity system.
 */
class EntityDriver {

  /**
   * Create an entity.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity to create.
   * @param array $values
   *   Values for the entity.
   *
   * @return array
   *   The created entity's data array.
   */
  public function createEntity($entity_type_id, array $values) {
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->create($values);
    $entity->save();
    return $this->toArray($entity);
  }

  /**
   * Load an entity.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity to create.
   * @param mixed $entity_id
   *   The ID of the entity.
   *
   * @return array
   *   Data for the entity, or FALSE if not available.
   */
  public function loadEntity($entity_type_id, $entity_id) {
    $entities = $this->loadEntities($entity_type_id, [$entity_id]);
    return reset($entities);
  }

  /**
   * Load a set of entities.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity to create.
   * @param array $entity_ids
   *   The IDs of the entities.
   *
   * @return array
   *   Data for the entities.
   */
  public function loadEntities($entity_type_id, array $entity_ids) {
    $entities = \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->loadMultiple($entity_ids);
    return array_map([$this, 'toArray'], $entities);
  }

  /**
   * Query entity data.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity to create.
   * @param array $conditions
   *   Conditions to apply to the entity query.
   * @param string $conjunction
   *   (optional) The logical operator for the query, either:
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return array
   *   Data for the entities.
   */
  public function queryEntities($entity_type_id, array $conditions, $conjunction = 'AND') {
    $query = \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)->getQuery($conjunction);
    foreach ($conditions as $condition) {
      $condition = array_pad($condition, 4, NULL);
      list($field, $value, $operator, $langcode) = $condition;
      $query->condition($field, $value, $operator, $langcode);
    }
    $ids = $query->accessCheck(FALSE)->execute();
    return $ids ? $this->loadEntities($entity_type_id, $ids) : [];
  }

  /**
   * Create an array from the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return array
   *   Array of the entity's data.
   */
  protected function toArray(EntityInterface $entity) {
    $values = $entity->toArray();
    $values['_meta'] = $this->getEntityMeta($entity);
    $this->setEntityRefMeta($entity, $values);
    return $values;
  }

  /**
   * Get metadata for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The entity metadata.
   */
  protected function getEntityMeta(EntityInterface $entity) {
    $values = [
      'id' => $entity->id(),
      'label' => (string) $entity->label(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ];

    if ($entity instanceof RevisionableInterface) {
      $values['revision_id'] = $entity->getRevisionId();
    }

    if ($entity instanceof AccountInterface) {
      $values['display_name'] = (string) $entity->getDisplayName();
    }

    $values['links'] = [];
    foreach ($entity->getEntityType()->getLinkTemplates() as $name => $path) {
      try {
        $values['links'][$name] = $entity->toUrl($name)->toString();
      }
      catch (\Exception $e) {
        $values['links'][$name] = NULL;
      }
    }

    return $values;
  }

  /**
   * Set entity meta data on referenced entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $values
   *   The entity values.
   *
   * @return array
   *   Entity values with meta data on referenced entities.
   */
  protected function setEntityRefMeta(EntityInterface $entity, array $values) {
    if (!$entity instanceof FieldableEntityInterface) {
      return $values;
    }

    // Replace referenced entities with their meta.
    foreach ($entity->getFieldDefinitions() as $fieldDefinition) {
      $field = $entity->get($fieldDefinition->getName());
      $refs = [];
      if ($field instanceof EntityReferenceFieldItemListInterface) {
        foreach ($field->referencedEntities() as $ref) {
          $refs[] = $this->getEntityMeta($ref);
        }
      }
      $values[$fieldDefinition->getName()] = $refs;
    }

    return $values;
  }

}

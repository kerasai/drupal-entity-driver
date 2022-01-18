<?php

namespace Kerasai\DrupalEntityDriver;

/**
 * Trait to use the entity driver.
 */
trait EntityDriverTrait {

  /**
   * The entity driver, use ::getEntityDriver method.
   *
   * @var EntityD\Kerasai\DrupalEntityDriver\EntityDriverriver
   */
  protected $entityDriver;

  /**
   * Gets the entity driver.
   *
   * @return \Kerasai\DrupalEntityDriver\EntityDriver
   *   The entity driver.
   */
  protected function getEntityDriver() {
    if (!$this->entityDriver) {
      $this->entityDriver = new EntityDriver();
    }
    return $this->entityDriver;
  }

}

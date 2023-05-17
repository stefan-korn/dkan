<?php

namespace Drupal\datastore_mysql_import\Storage;

use Drupal\datastore\Storage\DatabaseTable;

/**
 * MySQL import database table.
 */
class MySqlDatabaseTable extends DatabaseTable {

  /**
   * Create the table in the db if it does not yet exist.
   *
   * @throws \Exception
   *   Can throw any DB-related exception. Notably, can throw
   *   \Drupal\Core\Database\SchemaObjectExistsException if the table already
   *   exists when we try to create it.
   */
  protected function setTable() {
    // Never check for pre-existing table, never catch exceptions.
    if ($this->schema) {
      $this->tableCreate($this->getTableName(), $this->schema);
    }
    else {
      throw new \Exception("Could not instantiate the table due to a lack of schema.");
    }
  }

}
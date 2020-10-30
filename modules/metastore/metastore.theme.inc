<?php

use Drupal\node\Entity\Node;

/**
 * Preprocess.
 *
 * @param ) ($variables
 *
 * @return [type]
 */
function metastore_preprocess_node__data(&$variables) {
  $json = $variables['node']->get('field_json_metadata')->value;
  $metadata = json_decode($json);
  ksm($metadata);
  $variables['foo'] = 'bar';
}
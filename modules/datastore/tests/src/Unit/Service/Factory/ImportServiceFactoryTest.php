<?php

namespace Drupal\Tests\datastore\Unit\Service\Factory;

use Drupal\datastore\Storage\DatabaseTableFactory;
use Drupal\datastore\Service\Factory\ImportServiceFactory;
use Drupal\datastore\Storage\ImportJobStoreFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Drupal\datastore\Service\Factory\ImportServiceFactory
 * @coversDefaultClass \Drupal\datastore\Service\Factory\ImportServiceFactory
 *
 * @group dkan
 * @group datastore
 */
class ImportServiceFactoryTest extends TestCase {

  /**
   * @covers ::getInstance
   */
  public function testGetInstanceException() {
    $factory = new ImportServiceFactory(
      $this->getMockBuilder(ImportJobStoreFactory::class)
        ->disableOriginalConstructor()
        ->getMock(),
      $this->getMockBuilder(DatabaseTableFactory::class)
        ->disableOriginalConstructor()
        ->getMock()
    );

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("config['resource'] is required");
    $factory->getInstance('id', []);
  }

}

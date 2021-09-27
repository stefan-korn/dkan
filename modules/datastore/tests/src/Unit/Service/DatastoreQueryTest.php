<?php

namespace Drupal\Tests\datastore\Unit\Service;

use Drupal\common\Resource;
use Drupal\Core\DependencyInjection\Container;
use Drupal\common\Storage\JobStoreFactory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Tests\datastore\Traits\TestHelperTrait;
use MockChain\Chain;
use MockChain\Options;
use Drupal\datastore\Service;
use Drupal\datastore\Service\Factory\Import;
use Drupal\datastore\Service\ResourceLocalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\datastore\Service\DatastoreQuery;
use Drupal\datastore\Service\Import as ServiceImport;
use Drupal\datastore\Service\Info\ImportInfoList;
use Drupal\datastore\Storage\DatabaseTable;
use Drupal\datastore\Storage\QueryFactory;
use Drupal\metastore\Storage\Data;
use Drupal\metastore\Storage\DataFactory;
use Drupal\Tests\common\Unit\Storage\QueryDataProvider as QueryData;

/**
 * @group dkan
 */
class DatastoreQueryTest extends TestCase {
  use TestHelperTrait;

  /**
   * @test
   *
   * @dataProvider queryCompareProvider()
   */
  public function testQueryCompare($testName) {
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson($testName);
    $storageMap = $datastoreService->getQueryStorageMap($datastoreQuery);
    $dkanQuery = QueryFactory::create($datastoreQuery, $storageMap);
    $dkanQueryCompare = QueryData::$testName(QueryData::QUERY_OBJECT);
    $dkanQueryCompare->showDbColumns = TRUE;
    // $this->assertEquals(serialize($dkanQuery), serialize($dkanQueryCompare));
    $this->assertEquals(json_encode($dkanQuery, JSON_PRETTY_PRINT), json_encode($dkanQueryCompare, JSON_PRETTY_PRINT));
    $result = $datastoreService->runQuery($datastoreQuery);
    $this->assertIsArray($result->{"$.results"});
    $this->assertIsNumeric($result->{"$.count"});
    $this->assertIsArray($result->{"$.schema"});
    $this->assertIsArray($result->{"$.query"});
  }

  /**
   * Test a basic datastore query and response for expected properties.
   */
  public function testResultsQuery() {
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("propertiesQuery");
    $response = $datastoreService->runQuery($datastoreQuery);
    $this->assertIsArray($response->{"$.results[0]"});
    $this->assertEquals(123, $response->{"$.count"});
    $this->assertIsArray($response->{"$.schema"}["asdf"]["fields"]);
    $this->assertIsArray($response->{"$.query"});
  }

  /**
   * Test no keys behavior (array instead of keyed object).
   */
  public function testNoKeysQuery() {
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("propertiesQuery");
    $datastoreQuery->{"$.keys"} = FALSE;
    $response = $datastoreService->runQuery($datastoreQuery);
    $this->assertIsArray($response->{"$.results[0]"});
  }

  public function testBadCondition() {
    $this->expectExceptionMessage("Invalid condition");
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("badConditionQuery");
    $datastoreService->runQuery($datastoreQuery);
  }

  public function testBadQueryProperty() {
    $this->expectExceptionMessage("JSON Schema validation failed.");
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("badPropertyQuery");
    $datastoreService->runQuery($datastoreQuery);
  }

  public function testTooManyResourcesQuery() {
    $this->expectExceptionMessage("Too many resources specified.");
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("tooManyResourcesQuery");
    $datastoreService->runQuery($datastoreQuery);
  }

  public function testInvalidQueryAgainstSchema() {
    $this->expectExceptionMessage("JSON Schema validation failed");
    $container = $this->getCommonMockChain();
    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());
    $datastoreQuery = $this->getDatastoreQueryFromJson("invalidQuerySchema");
    $datastoreService->runQuery($datastoreQuery);
  }

  public function testRowIdsQuery() {
    $container = $this->getCommonMockChain()
      ->add(DatabaseTable::class, "getSchema", [
        "fields" => [
          "record_number" => 1,
          "a" => "a",
          "b" => "b",
          ],
        "primary key" => ["record_number"],
      ]);

    \Drupal::setContainer($container->getMock());
    $datastoreService = Service::create($container->getMock());

    $datastoreQuery = $this->getDatastoreQueryFromJson('rowIdsQuery');
    $result = $datastoreService->runQuery($datastoreQuery);
    $this->assertEmpty($container->getStoredInput('DatabaseTableQuery')[0]->properties);
    $this->assertArrayHasKey('record_number', $result->{"$.schema"}["asdf"]["fields"]);

    $datastoreQuery = $this->getDatastoreQueryFromJson('defaultQuery');
    $result = $datastoreService->runQuery($datastoreQuery);
    $this->assertEquals(
      ["a", "b"],
      $container->getStoredInput('DatabaseTableQuery')[0]->properties
    );
    $this->assertArrayNotHasKey('record_number', $result->{"$.schema"}["asdf"]["fields"]);
  }

  /**
   * Data provider for query compare tests.
   */
  public function queryCompareProvider() {
    return [
      ["propertiesQuery"],
      ["expressionQuery"],
      ["arrayConditionQuery"],
      ["likeConditionQuery"],
      ["nestedExpressionQuery"],
      ["nestedConditionGroupQuery"],
      ["sortQuery"],
      ["joinWithPropertiesFromBothQuery"],
    ];
  }

  private function getDatastoreQueryFromJson($payloadName): DatastoreQuery {
    $payload = file_get_contents(__DIR__ . "/../../../data/query/$payloadName.json");
    return new DatastoreQuery($payload);
  }

  /**
   * Build our mockChain.
   */
  public function getCommonMockChain() {

    $options = (new Options())
      ->add("dkan.datastore.service", Service::class)
      ->add('dkan.datastore.service.resource_localizer', ResourceLocalizer::class)
      ->add("dkan.datastore.service.factory.import", Import::class)
      ->add('queue', QueueFactory::class)
      ->add('request_stack', RequestStack::class)
      ->add('dkan.common.job_store', JobStoreFactory::class)
      ->add('dkan.metastore.storage', DataFactory::class)
      ->add('dkan.datastore.import_info_list', ImportInfoList::class)
      ->index(0);

    $resource_metadata = '{"data":{"%Ref:downloadURL":[{"data":{"identifier":"qwerty","version":"uiop"}}]}}';
    $resource = new Resource('http://example.org', 'text/csv');
    $queryResult = [(object) ["expression" => 123]];

    return (new Chain($this))
      ->add(Container::class, "get", $options)
      ->add(RequestStack::class, 'getCurrentRequest', Request::class)
      ->add(DataFactory::class, "getInstance", Data::class)
      ->add(Data::class, "retrieve", $resource_metadata)
      ->add(QueueFactory::class, "get", [])
      ->add(ResourceLocalizer::class, "get", $resource)
      ->add(Import::class, "getInstance", ServiceImport::class)
      ->add(ServiceImport::class, "getStorage", DatabaseTable::class)
      ->add(DatabaseTable::class, "query", $queryResult, 'DatabaseTableQuery')
      ->add(DatabaseTable::class, "getSchema", ["fields" => ["a" => "a", "b" => "b"]])
      ->add(DatabaseTable::class, "getTableName", "table2");

  }

}

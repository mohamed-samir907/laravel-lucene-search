<?php
namespace tests\functional;

use tests\models\Product;
use Search;

/**
 * Class SearchEventsTest
 * @package tests\functional
 */
class SearchEventsTest extends BaseTestCase
{
    public function testCreate()
    {
        $this->assertEquals(0, Search::find('observer')->count());

        $p = new Product;
        $p->name = 'observer';
        $p->publish = 1;
        $p->save();

        $this->assertEquals(1, Search::find('observer')->count());
    }

    public function testUpdate()
    {
        $this->assertEquals(0, Search::find('observer')->count());

        $p = Product::first();
        $p->name = 'observer';
        $p->save();

        $this->assertEquals(1, Search::find('observer')->count());
    }

    public function testDelete()
    {
        $this->assertEquals(0, Search::find('observer')->count());

        $p = Product::first();
        $p->name = 'observer';
        $p->save();

        $this->assertEquals(1, Search::find('observer')->count());

        $p->delete();

        $this->assertEquals(0, Search::find('observer')->count());
    }

} 
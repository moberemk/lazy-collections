<?php
namespace moberemk\LazyCollection\Test;

use moberemk\LazyCollection\Vector;

class VectorTest extends \PHPUnit_Framework_TestCase {
    protected $data;

    protected $collection;

    public function setUp() {
        $this->data = [4, 5, 3, 1, 2];

        $this->collection = new Vector($this->data);
    }

    public function testQueueing() {
        $collection = $this->collection->map([$this, 'doubleInteger']);
        $this->assertNotEquals($collection, $collection->map([$this, 'doubleInteger']));
    }

    public function testMap() {
        $collection = $this->collection->map([$this, 'doubleInteger'])->map([$this, 'incrementInteger']);

        $this->assertEquals([9, 11, 7, 3, 5], $collection->execute());
    }

    public function testFilter() {
        $this->assertEquals([4, 2], $this->collection->filter([$this, 'isEvenInteger'])->execute());
    }

    public function testReject() {
        $this->assertEquals([5, 3, 1], $this->collection->filter([$this, 'isEvenInteger'])->execute());
    }

    public function testTake() {
        $this->markTestIncomplete();
    }

    public function testSort() {
        $this->markTestIncomplete();
    }

    public function testGroupBy() {
        $this->markTestIncomplete();
    }

    public function testFind() {
        $this->markTestIncomplete();
    }

    public function testExecute() {
        $this->markTestIncomplete();
    }

    public function testGetIterator() {
        $this->markTestIncomplete();
    }

    public function testFirst() {
        $this->markTestIncomplete();
    }

    public function testEvery() {
        $this->markTestIncomplete();
    }

    public function testSome() {
        $this->markTestIncomplete();
    }

    public function testReduce() {
        $this->markTestIncomplete();
    }

    /**
     * Helper method to double the passed-in value
     * @param  integer $value The original value
     * @return integer        The doubled value
     */
    public static function doubleInteger($value) {
        return $value * 2;
    }

    /**
     * Helper method to increment the passed-in value
     * @param  integer $value The original value
     * @return integer        The value incremented by 1
     */
    public static function incrementInteger($value) {
        return $value + 1;
    }

    /**
     * Given an integer, check if it's an even number
     * @param  intger  $value The passed value
     * @return boolean        True if the value is even, false otherwise
     */
    public static function isEvenInteger($value) {
        return $value % 2 === 0;
    }
}
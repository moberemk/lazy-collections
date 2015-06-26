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
        $this->assertEquals([8, 4], $this->collection->filter([$this, 'isEvenInteger'])->map([$this, 'doubleInteger'])->execute());
    }

    public function testReject() {
        $this->assertEquals([5, 3, 1], $this->collection->reject([$this, 'isEvenInteger'])->execute());
        $this->assertEquals([10, 6, 2], $this->collection->reject([$this, 'isEvenInteger'])->map([$this, 'doubleInteger'])->execute());
    }

    public function testTake() {
        $this->assertEquals([4, 5, 3], $this->collection->take(3)->execute());
        $this->assertEquals([5, 3, 1], $this->collection->reject([$this, 'isEvenInteger'])->take(3)->execute());
        $this->assertEquals([8, 10, 6], $this->collection->map([$this, 'doubleInteger'])->take(3)->execute());
    }

    public function testSort() {
        $this->assertEquals([1, 2, 3, 4, 5], $this->collection->sort([$this, 'compareIntegers'])->execute());
        $this->assertEquals([2, 4, 6, 8, 10], $this->collection->map([$this, 'doubleInteger'])->sort([$this, 'compareIntegers'])->execute());
    }

    public function testGroupBy() {
        $this->assertEquals(['even' => [4, 2], 'odd' => [5, 3, 1]], $this->collection->groupBy([$this, 'integerType']));

        $this->assertEquals(['even' => [6, 4, 2], 'odd' => [5, 3]], $this->collection->map([$this, 'incrementInteger'])->groupBy([$this, 'integerType']));

        $this->assertEquals(['even' => [4, 2]], $this->collection->filter([$this, 'isEvenInteger'])->groupBy([$this, 'integerType']));
    }

    public function testFind() {
        $this->assertEquals(2, $this->collection->find(function($value) {
            return $value === 2;
        }));
    }

    public function testGetIterator() {
        $this->markTestIncomplete();
    }

    public function testFirst() {
        $this->assertEquals($this->data[0], $this->collection->first());
        $this->assertEquals(null, (new Vector([]))->first());
    }

    public function testEvery() {
        $this->assertFalse($this->collection->every([$this, 'isEvenInteger']));
        $this->assertTrue($this->collection->every('is_int'));
    }

    public function testSome() {
        $this->assertTrue($this->collection->some([$this, 'isEvenInteger']));
        $this->assertTrue($this->collection->some('is_int'));
    }

    public function testReduce() {
        // Validate that it will reduce the collection to a value
        $this->assertEquals(15, $this->collection->reduce([$this, 'sumIntegers']));

        // Validate that it will reduce a mapped collection
        $this->assertEquals(30, $this->collection->map([$this, 'doubleInteger'])->reduce([$this, 'sumIntegers']));

        // Validate that it will reduce only a filtered collection
        $this->assertEquals(6, $this->collection->filter([$this, 'isEvenInteger'])->reduce([$this, 'sumIntegers']));
    }

    /**
     * Sum two passed integers
     * @param  int ...$args An argument list comprised of integers to add together
     * @return int          The sum of all integers passed to this function
     */
    public static function sumIntegers() {
        $sum = 0;

        foreach (func_get_args() as $value) {
            $sum += $value;
        }

        return $sum;
    }

    /**
     * Compare two passed integers
     * @param  int $a The first compared integer
     * @param  int $b The first compared integer
     * @return int    A comparison value for the passed integers
     */
    public static function compareIntegers($a, $b) {
        return $a - $b;
    }

    /**
     * Determines the type of the passed integer
     * @param  int    $value The integer to examine
     * @return string        String 'even' for even integers, 'odd' otherwise
     */
    public static function integerType($value) {
        return $this->isEvenInteger($value) ? 'even' : 'odd';
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
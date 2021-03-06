<?php
namespace LazyCollection\Test;

use LazyCollection\IteratorWrapper;

class IteratorWrapperTest extends \PHPUnit_Framework_TestCase {
    protected $data;

    protected $collection;

    public function setUp() {
        $this->data = [4, 5, 3, 1, 2];

        $this->collection = new IteratorWrapper($this->data);
    }

    public function testQueueing() {
        $collection = $this->collection->map([$this, 'doubleInteger']);
        $this->assertNotEquals($collection, $collection->map([$this, 'doubleInteger']));
    }

    public function testMap() {
        $collection = $this->collection->map([$this, 'doubleInteger'])->map([$this, 'incrementInteger']);

        $this->assertEquals([9, 11, 7, 3, 5], $collection->execute());

        // Validate that the chain is executed the same way consistently
        $this->assertEquals([9, 11, 7, 3, 5], $collection->execute());
    }

    public function testEach() {
        $count = 0;
        $collection = $this->collection->each(function() use (&$count) {
            $count++;
        });

        $this->assertEquals($count, count($this->data));
        $this->assertEquals($this->collection, $collection);
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
        $grouped = $this->collection->groupBy([$this, 'integerType']);
        $this->assertArrayHasKey('even', $grouped);
        $this->assertArrayHasKey('odd', $grouped);
        $this->assertEquals([5, 3, 1], iterator_to_array($grouped['odd']));
        $this->assertEquals([4, 2], iterator_to_array($grouped['even']));

        $grouped = $this->collection->filter([$this, 'isEvenInteger'])->groupBy([$this, 'integerType']);
        $this->assertArrayHasKey('even', $grouped);
        $this->assertEquals([4, 2], iterator_to_array($grouped['even']));
    }

    public function testIndexBy() {
        $grouped = $this->collection->indexBy([$this, 'doubleInteger']);

        foreach ($grouped as $key => $value) {
            $this->assertEquals($key, $value * 2);
        }
    }

    public function find() {
        $this->assertEquals(2, $this->collection->find(function($value) {
            return $value === 2;
        }));

        $this->assertNull($this->collection->find(function($value) {
            return $value === 'a';
        }));
    }

    public function testFind() {
        $this->assertEquals(2, $this->collection->find(function($value) {
            return $value === 2;
        }));
    }

    public function testGetIterator() {
        $count = 0;
        // Can iterate over the collection and execute will be called implicitly
        foreach ($this->collection->map([$this, 'doubleInteger']) as $key => $value) {
            $this->assertNotNull($key);
            $this->assertEquals($this->doubleInteger($this->data[$key]), $value);
            $count++;
        }

        $this->assertEquals(count($this->data), $count);
    }

    public function testFirst() {
        $this->assertEquals($this->data[0], $this->collection->first());
        $this->assertEquals(null, (new IteratorWrapper([]))->first());
    }

    public function testEvery() {
        $this->assertFalse($this->collection->every([$this, 'isEvenInteger']));
        $this->assertTrue($this->collection->every([$this, 'isInteger']));
    }

    public function testSome() {
        $this->assertTrue($this->collection->some([$this, 'isEvenInteger']));
        $this->assertTrue($this->collection->some([$this, 'isInteger']));
    }

    public function testReduce() {
        // Validate that it will reduce the collection to a value
        $this->assertEquals(15, $this->collection->reduce([$this, 'sumIntegers'], 0));

        // Validate that it will reduce a mapped collection
        $this->assertEquals(30, $this->collection->map([$this, 'doubleInteger'])->reduce([$this, 'sumIntegers']));

        // Validate that it will reduce only a filtered collection
        $this->assertEquals(6, $this->collection->filter([$this, 'isEvenInteger'])->reduce([$this, 'sumIntegers']));
    }

    public function testToArray() {
        $returned = $this->collection->toArray();
        $this->assertCount(count($this->data), $returned);
    }

    public function testDebugInfo() {
        $collection = $this->collection->map([$this, 'doubleInteger']);
        $debug_info = $collection->__debugInfo();

        $this->assertArrayHasKey('queue', $debug_info);
        $this->assertArrayHasKey('data', $debug_info);
        $this->assertCount(1, $debug_info['queue']);
        $this->assertEquals($this->data, $debug_info['data']);
    }

    /**
     * Sum two passed integers
     * @param  int $a An integer value
     * @param  int $b An integer value
     * @return int    The sum of both integers passed to this function
     */
    public static function sumIntegers($a, $b) {
        return $a + $b;
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
        return self::isEvenInteger($value) ? 'even' : 'odd';
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

    /**
     * Check if the passed value is an integer
     * @param  mixed  $value Some kind of value
     * @return boolean       True if an integer was passed in, false otherwise
     */
    public static function isInteger($value) {
        return is_int($value);
    }
}

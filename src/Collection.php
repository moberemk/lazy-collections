<?php
namespace LazyCollection;

use IteratorAggregate;
use Countable;
use JsonSerializable;

interface Collection extends IteratorAggregate, Countable, JsonSerializable {
    /**
     * Base constructor function to create a new LazyCollection with the passed queue (if any)
     * @param Traversable $data  The traversable data object for this collection
     * @param array       $queue The current method queue
     */
    public function __construct($data, $queue = [], $limit = null);

    /**
     * Iterate over every value in the collection using a callback function;
     * the callback is called with the value and the current key as arguments
     * and the value returned by it will be added to the final collection which
     * is returned to the user
     * @param  callable $callback  A callback function which should return the new value;
     *                             the function will be passed the value and the key
     * @return Collection          The modified collection
     */
    public function map(callable $callback);

    /**
     * Iterate over every value in the collection; unlike map, will not modify
     * the collection based on the returned values. Executes immediately and not
     * lazily
     * @param  callable $callback A callback function which should return the new value;
     *                            the function will be passed the value and the key
     * @return Collection         The original collection (e.g. this)
     */
    public function each(callable $callback);

    /**
     * Filter this collection based on the passed expressions
     * @param  callable  $callback A callback function which is passed the current value
     * @return Collection          A filtered version of this collection's data (e.g. elements for which $filter returned true)
     */
    public function filter(callable $callback);

    /**
     * Filter this collection based on the passed expressions
     * @param  callable  $callback A callback function which is passed the current value
     * @return Collection          A filtered version of this collection's data (e.g. elements for which $filter returned false)
     */
    public function reject(callable $callback);

    /**
     * Take a slice of the collection starting from zero
     * @param  int    $limit The number of elements to slice from the collection
     * @return Collection    A reduced collection
     */
    public function take($limit = 1);

    /**
     * Executes the current method queue and then sorts the resulting array by using the passed
     * callback method
     * @param  callable $callback A callback function which is passed two values to compare
     * @return Collection         A sorted collection
     */
    public function sort(callable $callback);

    /**
     * Executes the current method queue and then groups the collection based on the return value
     * of the callback function
     * @param  callable $callback A callback function which is passed the current value and should return
     *                            the key this value should be stored by
     * @return array              An array of collection elements grouped by callback return values
     */
    public function groupBy(callable $callback);

    /**
     * Executes the current method queue and passes the resulting collection through the callback
     * function and returns the first value for which that function returns true
     * @param  callable $callback A callback function which is passed the current value and should return a loosely boolean value
     * @return mixed              Either the first collection value for which $callback returns truthy or null if none
     */
    public function find(callable $callback);

    /**
     * Execute the current method queue and return the concrete array produced
     * @return array The concrete array which the execution chain produced
     */
    public function execute();

    /**
     * Executes the current method queue and returns an ArrayIterator wrapping the result
     * @return ArrayIterator The ArrayIterator wrapping the result of the execution chain
     */
    public function getIterator();

    /**
     * Executes the current method queue and returns the first value
     * @return mixed The first value of the concrete array the execution chain produced
     */
    public function first();

    /**
     * Executes the current method queue and returns true if the passed callback is true for all values
     * @param  callable $callback A callback function which is passed the current value
     * @return bool               True if the callback passes for all values
     */
    public function every(callable $callback);

    /**
     * Executes the current method queue and returns true if the passed callback is true for all values
     * @param  callable $callback A callback function which is passed the current value
     * @return bool               True if the callback passes for any value in the collection
     */
    public function some(callable $callback);

    /**
     * Executes the current method queue and then runs the callback for all values, passing in
     * the current value and an accumulator value
     * @param  callable $callback A callback function which is passed the current value; should return
     *                            the current value of $accumulator
     * @return mixed              The final value of $accumulator
     */
    public function reduce(callable $callback, $accumulator = null);
}

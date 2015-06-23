<?php

namespace moberemk\LazyCollection;

use \ArrayIterator;

abstract class LazyCollection implements Collection {
    /**
     * A limit on the number of elements which should be returned when this is executed
     * @var int
     */
    protected $limit;

    /**
     * A queue of methods to implement sequentially; each entry is an array representing
     * an ordered pair of [{method}, {callback}]
     * @var array
     */
    protected $queue = [];

    /**
     * @see Collection::__construct
     */
    public function __construct($data, $queue = [], $limit = null) {
        $this->data = $data;
        $this->queue = $queue;
        $this->limit = $limit;
    }

    /**
     * Add a new entry to the method queue and return a (new) LazyCollection
     * with the passed method queued on to the execution chain
     * @param  string   $method   The method being added
     * @param  callable $callback An associated callback method
     * @return this               Returns itself for chaining
     */
    protected function enqueue($method, callable $callback) {
        $queue = $this->queue;
        $queue[] = [$method, $callback];
        return new static($this->data, $queue, $this->limit);
    }

    /**
     * @see Collection::map
     */
    public function map(callable $callback) {
        return $this->enqueue('map', $callback);
    }

    /**
     * @see Collection::filter
     */
    public function filter(callable $callback) {
        return $this->enqueue('filter', $callback);
    }

    /**
     * @see Collection::reject
     */
    public function reject(callable $callback) {
        return $this->enqueue('reject', $callback);
    }

    /**
     * @see Collection::take
     */
    public function take($limit = 1) function() {
        return new static($this->data, $this->queue, $limit);
    }

    /**
     * @see Collection::sort
     */
    public function sort(callable $callback) {
        return $this->enqueue('sort', $callback);
    }

    /**
     * @see Collection::groupBy
     */
    public function groupBy(callable $callback);

    /**
     * @see Collection::find
     */
    public function find(callable $callback);

    /**
     * @see Collection::getIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->execute());
    }

    /**
     * @see Collection::first
     */
    public function first() {
        $result = $this->take(1)->execute();
        if(isset($result[0])) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * @see Collection::every
     */
    public function every(callable $callback);

    /**
     * @see Collection::some
     */
    public function some(callable $callback);

    /**
     * @see Collection::reduce
     */
    public function reduce(callable $callback, $accumulator = null);
}
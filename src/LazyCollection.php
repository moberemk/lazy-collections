<?php

namespace moberemk\LazyCollection;

use ArrayIterator;
use SplFixedArray;

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
        // Transparently transform filter to reject
        return $this->enqueue('filter', function($value) use ($callback) {
            return !call_user_func($callback, $value);
        });
    }

    /**
     * @see Collection::take
     */
    public function take($limit = 1) {
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
    public function groupBy(callable $callback) {
        throw new NotImplementedException();
    }

    /**
     * @see Collection::find
     */
    public function find(callable $callback) {
        throw new NotImplementedException();
    }

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
    public function every(callable $callback) {
        throw new NotImplementedException();
    }

    /**
     * @see Collection::some
     */
    public function some(callable $callback) {
        throw new NotImplementedException();
    }

    /**
     * @see Collection::reduce
     */
    public function reduce(callable $callback, $accumulator = null) {
        throw new NotImplementedException();
    }

    /**
     * @see Collection::count
     */
    public function count() {
        return count($this->data);
    }

    /**
     * @see Collection::jsonSerialize
     */
    public function jsonSerialize() {
        return $this->getIterator();
    }

    /**
     * @see Collection::execute
     */
    public function execute() {
        // Group queued methods into execution blocks
        $execution_blocks = [];
        $current_block = [];

        // TODO: clean this up, potentially use objects instead of array for potential
        // memory usage gains and better differentiation between action types
        foreach ($this->queue as $value) {
            switch ($value[0]) {
                // Add to the current block
                case 'filter':
                case 'map':
                    $current_block[] = $value;
                    break;

                // End the current block and begin a new one
                case 'sort':
                    $execution_blocks[] = $current_block;
                    $execution_blocks[] = $value[1];
                    $current_block = [];
                    break;

                default:
                    throw new NotImplementedException('Method '.$value[0].' not yet implemented');
            }
        }

        // Set the current block as an execution block
        $execution_blocks[] = $current_block;

        // Iterate over the data running each execution block
        $returned = $this->data;
        foreach ($execution_blocks as $block) {
            // Check if a block or a sort operation
            if(is_array($block)) {
                // Allocate a new array
                // TODO: investigate tradeoffs of SplFixedArray here given known max size
                $new_returned = [];

                // Iterate over the data and create the new returned array
                foreach ($returned as $value) {
                    // Iterate over each method in the execution block
                    // and perform the given action
                    foreach ($block as $action) {
                        $result = call_user_func($action[1], $value);

                        // Perform the required action(if any)
                        switch ($action[0]) {
                            case 'filter':
                                if(!$result) {
                                    // Break out of the current data loop iteration block if filter
                                    // check fails so that it won't perform more actions or append
                                    // the value to the new returned array
                                    continue(3);
                                }
                                break;

                            case 'map':
                                // Assign the current value to the result of the executed operation
                                $value = $result;
                                break;
                        }
                    }

                    $new_returned[] = $value;
                }

                $returned = $new_returned;
            } else {
                // Perform a sort operation in-place on the transformed array
                usort($returned, $block);
            }
        }

        // Return the transformed data
        return $returned;
    }
}
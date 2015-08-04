<?php

namespace LazyCollection;

use ArrayIterator;
use SplFixedArray;

use LazyCollection\Exceptions\NotImplementedException;

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
     * @see Collection::each
     */
    public function each(callable $callback) {
        foreach($this as $key => $value) {
            call_user_func($callback, $value);
        }
        return $this;
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
        $returned = [];

        foreach ($this->execute() as $key => $value) {
            $key = call_user_func($callback, $value);

            if(!isset($returned[$key])) {
                $returned[$key] = [];
            }

            $returned[$key][] = $value;
        }

        foreach ($returned as $key => $value) {
            $returned[$key] = new IteratorWrapper(new ArrayIterator($value));
        }

        return null;
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
        foreach ($this->getIterator() as $key => $value) {
            if(!call_user_func($callback, $value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @see Collection::some
     */
    public function some(callable $callback) {
        foreach ($this->getIterator() as $key => $value) {
            if(call_user_func($callback, $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @see Collection::reduce
     */
    public function reduce(callable $callback, $accumulator = null) {
        foreach ($this->getIterator() as $key => $value) {
            $accumulator = call_user_func($callback, $value, $accumulator);
        }

        return $accumulator;
    }

    /**
     * @see Collection::count
     */
    public function count() {
        return count($this->data);
    }

    /**
     * @see Collection::find
     */
    public function find(callable $callback) {
        $values = $this->filter($callback)->take(1)->execute();

        if(isset($values[0])) {
            return $values[0];
        } else {
            return null;
        }
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

        // Substracting this count here since the iteration count compares against this value
        // inside the loop, and it saves on instructions doing only a single substraction here
        $steps = count($execution_blocks) - 1;

        foreach ($execution_blocks as $iteration => $block) {
            // Check if a block or a sort operation; need to check if callable first even though
            // array is more common as callables can also be arrays because PHP
            if(is_callable($block)) {
                // Perform a sort operation in-place on the transformed array
                usort($returned, $block);
            } else if(is_array($block)) {
                // Allocate a new array
                // TODO: investigate tradeoffs of SplFixedArray here given known max size
                $new_returned = [];
                $count = 0;

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

                    // In the final iteration, break out of the loop early if a take is being performed
                    if($this->limit !== null && $iteration === $steps) {
                        $count++;

                        if($count > $this->limit) {
                            break;
                        }
                    }

                    $new_returned[] = $value;
                }

                $returned = $new_returned;
            }
        }

        // TODO: make this a cleaner, lazier implementation
        if($this->limit !== null && count($returned) > $this->limit) {
            $returned = array_slice($returned, 0, $this->limit);
        }

        // Return the transformed data
        return $returned;
    }

    /**
     * Returns an array representation of the current collection
     * @param  boolean $use_keys If the returned array should use the keys defined by the iterator; true by default
     * @return array An associative array of results
     */
    public function toArray() {
        return $this->execute();
    }
}

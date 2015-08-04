<?php
namespace LazyCollection\Set;

use LazyCollection\IteratorWrapper;
use LazyCollection\Collection;
use LazyCollection\DatabaseIterators\PostgresResultIterator;

class PostgresResultSet extends IteratorWrapper implements Collection {
    const EXPECTED_RESOURCE_TYPE = 'pgsql result';

    public function __construct($resource, $queue = [], $limit = null) {
        if(!is_resource($resource)) {
            // If not given a resource, then cancel out early
            parent::__construct($resource, $queue, $limit);
            return;
        }

        // Convert this to a wrapped iterator
        parent::__construct(new PostgresResultIterator($resource), $queue, $limit);
    }
}

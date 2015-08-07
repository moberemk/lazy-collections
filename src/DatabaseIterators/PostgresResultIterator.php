<?php
namespace LazyCollection\DatabaseIterators;

use Iterator;
use LazyCollection\Exceptions\DatabaseException;

class PostgresResultIterator implements Iterator {
    /**
     * A reference to the query resource in use
     * @var Resource
     */
    protected $resource;

    /**
     * The string type which this class expects to work with
     * @var string
     */
    const EXPECTED_RESOURCE_TYPE = 'pgsql result';

    /**
     * A count of columns for this query
     * @var integer
     */
    protected $fieldCount = 0;

    /**
     * A map of defined fields
     */
    protected $types = [];

    /**
     * The size of this recordset
     * @var integer
     */
    protected $count = 0;

    public function __construct($resource) {
        if(!is_resource($resource) || (is_resource($resource) && get_resource_type($resource) !== self::EXPECTED_RESOURCE_TYPE)) {
            throw new DatabaseException('Invalid resource type passed, expected "'.$this->expectedResource.'" and got "'.get_resource_type($resource).'"');
        }
        $this->resource = $resource;
        $this->count = pg_num_rows($this->resource);

        // Build an array of column types
        $this->fieldCount = pg_num_fields($this->resource);
        for ($i = 0; $i < $this->fieldCount; ++$i) {
            $this->types[$i] = [pg_field_name($this->resource, $i), pg_field_type($this->resource, $i)];
        }
    }

    /**
     * Parses a Postgres row to return a normalized data set
     * @param  array  $row A row returned by the pgsql driver
     * @return Map         A normalized version of that row (proper types used, )
     */
    protected function parseData(array $row) {
        $converted = [];
        for($i = 0; $i < $this->fieldCount; ++$i) {
            $value = $row[$i];
            $field = $this->types[$i][0];
            $type = $this->types[$i][1];

            if($value !== null) {
                switch ($type) {
                    // Integer types
                    case 'int2':
                    case 'int4':
                        // This assumes base 10 integers
                        $value = intval($value);
                        break;

                    case 'float8':
                        $value = floatval($value);
                        break;

                    case 'bpchar':
                        $value = trim($value);
                        break;

                    case 'json':
                        // This converts all JSON objects to be associative arrays
                        $value = json_decode($value, true);
                        break;

                    case 'bool':
                        switch ($value) {
                            // Omitting other possible valid values for simplicity, can
                            // add others when necessary
                            case 'true':
                            case 't':
                                $value = true;
                                break;
                            case 'false':
                            case 'f':
                                $value = false;
                                break;
                            default:
                                $value = null;
                        }
                        break;

                    // Conversion of dates to DateTime objects could be useful
                    // CURRENTLY DISABLED until the the potential side-effects
                    // of doing this for all queries are fully understood. When
                    // encoded to JSON this returns the full date and timezone info
                    /*case 'date':
                        $value = new DateTime($value);
                        break;*/

                    default:
                        $value = $value;
                        break;
                }
            }
            $converted[$field] = $value;
        }
        return $converted;
    }

    /**
     * Gets the current iterable database row
     * @return array An associative array of a row's data
     */
    public function current() {
        $this->current = $this->parseData(pg_fetch_row($this->resource, $this->key));
        return $this->current;
    }

    /**
     * Gets the current iterable database row
     * @return int The key of the current row
     */
    public function key() {
        return $this->key;
    }

    /**
     * Gets the next iterable database row
     * @return array An associative array of a row's data
     */
    public function next() {
        $this->key++;
    }

    /**
     * Gets the previous iterable database row
     * @return array An associative array of a row's data
     */
    public function rewind() {
        $this->key = 0;
    }

    /**
     * Checks if current position is valid
     * @return boolean True if the current position is valid
     */
    public function valid() {
        return $this->key >= 0 && $this->key < $this->count;
    }

    /**
     * Gets the size of the current result set
     * @return integer The number of retrieved rows
     */
    public function count() {
        return $this->count;
    }
}

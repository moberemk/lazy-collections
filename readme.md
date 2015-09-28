# Lazy Collections
![Travis Build Status](https://travis-ci.org/moberemk/lazy-collections.svg)
[![Coverage Status](https://coveralls.io/repos/moberemk/lazy-collections/badge.svg)](https://coveralls.io/r/moberemk/lazy-collections)

## How It Works

### Theory

When dealing with sets of data, most interactions boil down to one of a handful of simple operations: `map`, `filter`, and `reduce`.

- A `map` operation iterates over every element of a collection and generates a new collection whose values are produced by a callback function
- A `filter` operation iterates over every element of a collection and returns a new collection, one not containing any values for which the given callback function returns `false`
- A `reduce` operation iterates over every element of a collection and passes in both the element and an accumulator, reducing the collection to a single value

For instance, given a `map` operation with a callback which doubles the passed value (written as `f(x) = x * 2`) then the data will look like this:

```
[1,2,3] => [2,4,6]
```

If you were to add a second `map` operation, then you'd have a second collection to generate. Reusing that same callback function again would make things look something like this:

```
[1,2,3] => [2,4,6] => [4,8,12]
```

The useful part comes in here: the intermediate array (`[2,4,6]`) can be removed entirely from this execution chain by composing the two callback functions (written as `f(f(x))`), producing something more like this:

```
[1,2,3] => [4,8,12]
```

This has two advantages: first, it reduces the number of times that the data is iterated over, which given a large data set will significantly reduce processing time; and second, it reduces memory time by not needing to generate any intermediate arrays and immediately discarding intermediate values when they are no longer necessary.

This also works for `filter` and `reduce` operations. Given a filter function which accepts only odd integers (`g(x) = x is an odd integer`) then the array transformation would look like this:

```
[1,2,3] => [1,3]
```

If you were to add another step to the process, say the map function `f(x)` from earlier, then you'd end up with iterations such as this

```
[1,2,3] => [2,4,6] => []
```

But again, this can be made more efficient by generating the new value through the `map` function and then immediately checking it with the `filter` function, without needing that intermediate array at all:

```
[1,2,3] => []
```

All of this holds true regardless of the number of operations which are executed provided that **the order of operations is maintained**. This is not always a concern, but for some cases it will make a big difference. By example, if there's a function which subtracts 2 from a given integer (`h(x) = x - 2`) then composing that function with `f(x)` in different orders will produce different final arrays. As an example, `f(h(x))` would produce:

```
[1,2,3] => [-2,0,2]
```

Whereas `h(f(x))` would give this:

```
[1,2,3] => [0,2,4]
```

Essentially, while some functions can be transitive (`f(f(x))` for instance) they cannot be assumed to be.

### Technical Implementation

Collections are essentially just wrappers to existing iterators; what that iterable data is can vary widely (arrays, Iterator objects, database result sets, etc) so long as it works within a PHP `foreach` loop. A basic wrapper can be made just by using the `LazyCollection\IteratorWrapper` interface and passing in a data object as the first parameter; after that it becomes a full Collection object with all relevant methods in place.

An IteratorWrapper collection contains two pieces of data: first, it contains a *reference* to the original iterator source data; second, it contains a queue of actions and callback functions to perform. This keeps the actual collection object lightweight and easy to copy.

All of these actions are deferred until the `execute` method is called, either implicitly or explicitly. `execute` is called implicitly whenever the Collection is iterated over without using a callback method (e.g. passing it in to a `foreach` block) or any time that a method which doesn't support shortcut fusion is called, including the following:

- `reduce`
- `groupBy`
- `indexBy`
- `find`
- `first`
- `toArray`
- `every`
- `some`
- `jsonSerialize`

#### Referential Behavior

Collections are [semi-immutable objects](https://en.wikipedia.org/wiki/Immutable_object). That is to say, any operation which adds to the execution queue (`map`, `each`, `filter`, `reject`, `take`, `sort`) will **create a new wrapper object** which is equivalent to the current wrapper object with one item added to the queue. *None of these methods will modify any internal state on the collection object*.

What this means is that you cannot call one of these methods in place and expect it to work. As a basic demonstration, here's a few lines of example code:


```
$collection = new IteratorWrapper(...); // creates object 1
$collection->map(function);             // creates object 2
$collection->execute();                 // called on object 1

```

When the `execute` is called on the third line the `map` function will have no effect. This can be resolved by reassigning the new object to the relevant variable

```
$collection = new IteratorWrapper(...);     // creates object 1
$collection = $collection->map(function);   // creates object 2
$collection->execute();                     // called on object 2

```

As of the 0.6 release there is now an additional internal state variable: the collection's final state once the execution has completed. What this change means in practice is a change in the following behavior:

```
foreach($collection as $element) {} // Perform all queued operations, iterate over the result
foreach($collection as $element) {} // Perform all queued operations, iterate over the result
```

Both `foreach` loops would iterate over referentially-different, but (theoretically) value-identical, collection objects. The new behavior works as follows:

```
foreach($collection as $element) {} // Perform all queued operations, iterate over the result
foreach($collection as $element) {} // Iterate over the result calculated before the prioer foreach loop
```

This brings with it two major behavioral changes:

- Using `foreach` to iterate over the same collection multiple times will no longer run the full execution chain multiple times
- Once a final, executed value has been calculated, any calls which would normally generate a new collection object will no longer extend the execution chain; instead it will use the executed value as the new base iterable value and clear the queue except for the new enqueued action; essentially the same behavior that is achieved via `$collection = new IteratorWrapper($collection->execute())->map(function)` can now be achieved with code that looks like `$collection->execute(); $collection = $collection->map(function);`

In purely linear usage, this is fine; where this can get trickier is in branching scenarios. See the following code for an example:

```
$collection = new IteratorWrapper(...);
$collection = $collection->map(function1);

// Perform different map operations
$collection_a = $collection->map(function2);
$collection_b = $collection->map(function3);

$collection_a->execute(); // Iterate over the data calling function1, function2
$collection_b->execute(); // Iterate over the data calling function1, function3
```

While theoretically this could be set up such that `$collection_a` and `$collection_b` both rely on the same new array generated by the initial `$collection->map` call, in practice tracking the tree of branching queues which can be generated this way is impractical at best, and so it doesn't happen. This can manifest in subtle ways, particularly once PHP implicit object references get involved in the logic.

To avoid the mental overhead, it is advised to **avoid this kind of branching scenario** as much as possible. If it's *absolutely necessary* to do this, try code that looks more like this instead:

```
$collection = new IteratorWrapper(...);
$collection = $collection->map(function1);
$collection->execute(); // Generate a concrete version of the result of these shared operations

// Perform different map operations, both sharing
$collection_a = $collection->map(function2);
$collection_b = $collection->map(function3);

$collection_a->execute(); // Iterate over the data generated on line 3 calling function2
$collection_b->execute(); // Iterate over the data generated on line 3 calling function3
```

That said, once again this behavior can quickly become mentally unmanageable and so a purely linear use of the chaining behavior is far more desirable; if a collection does need to be separated out into sets and operated on differently, consider a `groupBy` operation and then operating on each group individually.

## IteratorWrapper

Pass in an iterable object to the constructor and it'll wrap it into a LazyCollection object, which provides a number of operations exposed as methods. These operations are enqueued to run lazily, e.g. only when explicitly or implicitly called by the developer. Full writeup on how that works coming soon.

- Integer keyed
- Ordered
- Unique values

## Postgres Result Iterator

A basic class which given a Postgres result set will provide an Iterator object which will lazily parse each row into an associative array.

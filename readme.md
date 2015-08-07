# Lazy Collections
![Travis Build Status](https://travis-ci.org/moberemk/lazy-collections.svg)
[![Coverage Status](https://coveralls.io/repos/moberemk/lazy-collections/badge.svg)](https://coveralls.io/r/moberemk/lazy-collections)

## IteratorWrapper

Pass in an iterable object to the constructor and it'll wrap it into a LazyCollection object, which provides a number of operations exposed as methods. These operations are enqueued to run lazily, e.g. only when explicitly or implicitly called by the developer. Full writeup on how that works coming soon.

- Integer keyed
- Ordered
- Unique values

## Postgres Result Iterator

A basic class which given a Postgres result set will provide an Iterator object which will lazily parse each row into an associative array.

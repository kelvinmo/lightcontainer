# LightContainer

LightContainer is a simple [PSR-11] compliant dependency injection container
that supports autowiring.

[![build](https://github.com/kelvinmo/lightcontainer/workflows/CI/badge.svg)](https://github.com/kelvinmo/lightcontainer/actions?query=workflow%3ACI)

## Requirements

* PHP 7.2 or later

## Installation

You can install via [Composer](http://getcomposer.org/).

```sh
composer require kelvinmo/lightcontainer
```

## Basic Usage

Consider the following set of classes:

```php
class A {}

class B {
    public function __construct(A $a) {}
}

class C {
    public function __construct(B $b) {}
}
```

Normally, if you want to create `C`, you will need to do the following:

```php
$c = new C(new B(new A()));
```

However, with LightContainer you can do this:

```php
$container = new LightContainer\Container();
$c = $container->get(C::class);
```

Note that absolutely no configuration of the container is required.
LightContainer figures out the dependencies based on the type hints
provided by the constructor parameters, and then configures itself
to create the necessary objects.  This is *autowiring*.

## Configuration

### Introduction

You can configure the container through the `set` method.
This method can be called with up to two parameters: a string
[entry identifier], and an optional value. The main ways 
you can use this method is used to configure the container are
set out in this section, with further details can be found
in the [reference](#reference) section below.

### Instantiation options

You can set instantiation options for a particular class by calling the `set`
method with the name of the class.  This returns a *class resolver* object,
which then allows you to specify options.

```php
$container->set(D::class)->shared();
```

Multiple options can be set by chaining up options.

```php
$container->set(D::class)
    ->alias(FooInterface::class, FooInterfaceImpl::class)
    ->shared();
```

## Reference

### Resolvers

At its core, LightContainer stores a mapping between an entry identifier
and a resolver.  A *resolver* is an object that implements
`LightContainer\Resolvers\ResolverInterface`, which the container uses
to resolve to an object or some other value whenever the `get` method
is called.

The main kind of resolver is a *class resolver*, which resolves to an object
by instantiating it from the specified class.  Other key kinds of resolvers
include a *reference resolver*, which looks up another entry in the container,
and a *value resolver*, which simply returns a specified value.

The `set` method takes the entry identifier string and the specified value,
creates and registers a resolver, and then returns it to the user.  The
kind of resolver the method creates depends on the format of
the entry identifier, and the type of value that is specified.  These are
set out in the table below.

| Identifier                   | Value            | Resolver           | Description                                                  |
| ---------------------------- | ---------------- | ------------------ | ------------------------------------------------------------ |
| Name of an existing class    | None             | Class resolver     | Allows you to set [instantiation options](#instantiation-options) for that class |
| Name of an (existing?) class | A string         | Reference resolver | Alias                                                        |
| `*`                          | None             | Class resolver*    | Allows you to set default [instantiation options](#instantiation-options) |
| Any other string             | Any other string | Reference resolver |                                                              |
| Any other string             | A callable       | Factory resolver   |                                                              |
| Any other string             | Any other value  | Value resolver     |                                                              |

The class resolver returned for `*` is a special kind of class resolver.  It
cannot be called to resolve to an actual object.

### Class resolvers

## Licence

BSD 3 clause

[PSR-11]: https://www.php-fig.org/psr/psr-11/
[entry identifier]: https://www.php-fig.org/psr/psr-11/#:~:text=1.1.1-,Entry%20identifiers,-An%20entry%20identifier
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

You can configure the container through the `set` method.  This method can be
called with up to two parameters: a string [entry identifier], and an optional
value. The main ways you can use this method is used to configure the
container are set out in this section, with further details can be found
in the [reference](#reference) section below.

### Instantiation options

You can set instantiation options for a particular class by calling the `set`
method with the name of the class.  This returns a *class resolver* object,
which then allows you to specify options.

```php
$container->set(D::class)->shared();
```

Multiple options can be set by chaining up the methods.

```php
$container->set(D::class)
    ->alias(FooInterface::class, FooInterfaceImpl::class)
    ->shared();
```

Instantiation options are cleared every time you call the `set` method
on the container object.  To set additional options on the same
resolver, use the `getResolver` method to retrieve the existing resolver.

```php
$container->set(D::class)->shared();

// Correct
$container->getResolver(D::class)->alias(FooInterface::class, FooInterfaceImpl::class);

// Incorrect - shared() will disappear
$container->set(D::class)->alias(FooInterface::class, FooInterfaceImpl::class);
```



#### Aliases

Consider the following declarations:

```php
interface FooInterface {}

class FooInterfaceImpl implements FooInterface {}

class D {
    public function __construct(FooInterface $i) {}
}
```

`D` cannot be instantiated by autowiring as `FooInterface` is an interface.
We need to specify which concrete class that implements the interface
we want.  We can do this by using the `alias` method on the resolver.

```php
$container->set(D::class)->alias(FooInterface::class, FooInterfaceImpl::class);

$d = $container->get(D::class);
// This is equivalent to new D(new FooInterfaceImpl())
```

You can set multiple aliases with a single call by passing an array.

```php
$container->set(E::class)->alias([
    FooInterface::class => FooInterfaceImpl::class,
    BarInterface::class => BarInterfaceImpl::class
]);
```

If you want to define an alias applicable for all classes in the container,
consider using a [global alias](#global-aliases).

#### Constructor arguments

Consider the following set of classes:

```php
class F {}

class G {
    public function __construct(F $f, string $host, int $port = 80) {}
}
```

`G` cannot be instantiated by autowiring as we need to, as a minimum,
specify `$host`. We can do this by using the `args` method on the resolver.

```php
$container->set(G::class)->args('example.com');
$container->set(G::class)->args('example.com', 8080);
// Multiple calls also work
$container->set(G::class)
    ->args('example.com')
    ->args(8080);
```

The `args` method can only be used to specify parameters that:

* do not have a type hint; or
* have a type hint for an internal type (e.g. int, string, bool); or
* for PHP 8, have a type hint that is a union type.

Other parameters (i.e. those with type hints for classes or interfaces)
are ignored when processing the `args` method.  To manipulate how
these parameters are treated, use [aliases](#aliases) or
[global aliases](#global-aliases).

```php
class H {
    // PHP 8 is required for this declaration to work
    public function __construct(F $f, int|string $bar, A $a, $baz) {}
}

// This sets $bar to 'one' and $baz to 'two'
$container->set(H::class)->args('one', 'two');
```

There may be times where you need to use something from the container as an
argument.  This may occur if the parameter in the declaration is not type
hinted (and so you can't use `alias`), or if you want to specify a
particular [named instance](#multiple-shared-instances).  In these cases
you will need to wrap the entry identifier with `Container::ref()`.

```php
class I {
    /**
     * @param FooInterface $foo
     */
    public function __construct($foo) {}
}

// See $foo to the FooInterfaceImpl from the container (which may be
// instantiated if required)
$container->set(I::class)->args(LightContainer\Container::ref(FooInterfaceImpl::class));

// Set $foo to named instance @foo from the container
$container->set(I::class)->args(LightContainer\Container::ref('@foo'));
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
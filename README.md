# LightContainer

LightContainer is a simple [PSR-11] compliant dependency injection container
that supports autowiring.

[![Latest Stable Version](https://poser.pugx.org/kelvinmo/lightcontainer/v/stable)](https://packagist.org/packages/kelvinmo/lightcontainer)
[![build](https://github.com/kelvinmo/lightcontainer/workflows/CI/badge.svg)](https://github.com/kelvinmo/lightcontainer/actions?query=workflow%3ACI)

## Table of contents

<!-- toc -->

* [Requirements](#requirements)
* [Installation](#installation)
* [Basic Usage](#basic-usage)
* [Configuration](#configuration)
  * [Introduction](#introduction)
  * [Instantiation options](#instantiation-options)
    * [Aliases](#aliases)
    * [Constructor arguments](#constructor-arguments)
    * [Setter injection](#setter-injection)
    * [Shared instances](#shared-instances)
    * [Options for autowired resolvers](#options-for-autowired-resolvers)
  * [Global aliases](#global-aliases)
  * [Multiple shared instances](#multiple-shared-instances)
  * [Custom instantiation](#custom-instantiation)
  * [Storing arbitrary values](#storing-arbitrary-values)
  * [Loading configurations](#loading-configurations)
* [Reference](#reference)
  * [Resolvers](#resolvers)
  * [Autowiring](#autowiring)
  * [Instantiation options reference](#instantiation-options-reference)
* [Licence](#licence)

<!-- tocstop -->

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

Further details on how autowiring works can be found in the
[reference](#autowiring).

## Configuration

### Introduction

You can configure the container through the `set` method.  This method can be
called with up to two parameters: a string *entry identifier*, and an optional
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

class FooInterfaceSubclass extends FooInterfaceImpl {}

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

Aliases can refer to other aliases.  In the example below, when the container
looks for `FooInterface`, it finds `FooInterfaceImpl` as an alias, but that
in turn references `FooInterfaceSubclass`.  In the end, `FooInterfaceSubclass`
is created.  It can also be seen that aliases can be made for classes as well
as interfaces.

```php
$container->set(D::class)
    ->alias(FooInterface::class, FooInterfaceImpl::class)
    ->alias(FooInterfaceImpl::class, FooInterfaceSubclass::class);

$d = $container->get(D::class);
// This is equivalent to new D(new FooInterfaceSubclass())
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
$container->set(G::class)->args('example.com', 8080);

// Multiple calls also work
$container->set(G::class)
    ->args('example.com')
    ->args(8080);

// Optional parameters can be omitted
$container->set(G::class)->args('example.com');
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

#### Setter injection

In addition to dependency injection via the constructor, LightContainer also
supports injecting dependencies via setter methods.  Consider this declaration:

```php
class J {
    public function setA(A $a) {}
}
```

To get the container to call `setA` to inject `A` whenever `J` is created,
use the `call` method to specify the method to call.  [Autowiring](#autowiring)
works in the same way as for constructor injection.

```php
$container->set(J::class)->call('setA');
```

The `call` method also takes additional arguments, which will be passed
on to the setter method.  The rules for specifying additional arguments are the
same as for [constructors](#constructor-arguments).  In addition,
[aliases](#aliases) and [global aliases](#global-aliases) are also
resolved in the same way as for constructor injection.

```php
class K {
    public function setFoo(FooInterface $f, bool $debug);
}

$container->set(K::class)
    ->alias(FooInterface::class, FooInterfaceImpl::class)
    ->call('setFoo', false);
```

**NOTE.** Aliases apply to the constructor and *all* setter methods.  You
cannot define aliases that only applies to a particular setter method.

#### Modifying resolved instances

In addition to setter injection, LightContainer supports other forms of
modifying the resolved object before passing it back to the caller.

To do this, create an instance of a class that implements
`LightContainer\InstanceModifierInterface`, then pass it to the
container using the `modify` method.

```php
class Modifier implements InstanceModifierInterface {
    public function modify(object $obj, LightContainerInterface $container): object {}
}

class ModifyMe {}

$modifier = new Modifier();
$container->set(ModifyMe::class)->modify($modifier);

// The container will call $modifier->modify() before returning the instance
$modify_me = $container->get(ModifyMe::class);
```

#### Shared instances

There may be times where you want the same instance of a class to
be returned by the container no matter how many times it is resolved.  This
may be the case if the object is meant to be used as a singleton.

To do this, call the `shared` method on the resolver.

```php
$container->set(A::class)->shared();

// $a1 and $a2 are the same instance
$a1 = $container->get(A::class);
$a2 = $container->get(A::class);
```

You can also switch off this behaviour by calling `shared(false)`.
However, this only works if the shared instance has *not* been created
(i.e. if `get` hasn't been called).  Otherwise this will throw an
exception.

#### Options for autowired resolvers

*Autowired resolvers* are created automatically by the container as part of
the autowiring process. As autowired resolvers do not have an explicit entry
in the container, they inherit the instantiation options for the immediate
ancestor class that have an entry in the container.

For example, in the declaration below, the resolver for `N` is autowired
as there is no explicit entry in the container for `N`.  Because `L` is
an ancestor class for `N` and it has an entry in the container, `N`
inherits all the instantiation options from `L`.

```php
class L {}
class M extends L {}
class N extends M {}

$container->set(L::class)->shared();
// $n1 and $n2 are the same instance because
// N inherited 'shared' from L
$n1 = $container->get(N::class);
$n2 = $container->get(N::class);
```

To control this behaviour, call `propagate(false)` on the resolver.
This will stop instantiation options from being propagated to autowired
resolvers created for its subclasses.  For example, in the example
below, `N` is an autowired resolver, but does not inherit the options
from `L` because `propagate` for `L` is set to false.  Therefore
`L` retains the default behaviour of not creating shared instances.

```php
$container->set(L::class)->shared()->propagate(false);
// $n1 and $n2 are the different instances because
// L does not propagate its options to autowired
// subclasses
$n1 = $container->get(N::class);
$n2 = $container->get(N::class);
```

To set instantiation options for *all* autowired resolvers, you can use the
special wildcard resolver `*`.

```php
// Set 'shared' to true for all autowired resolvers
$container->set('*')->shared();
```

### Global aliases

Instead of defining [aliases](#aliases) at the class level, you can define a
*global alias*, which applicable for all classes created by the container.
This can be done by calling the `set` method with the name of the class or
interface to be replaced in the first argument, and the name of the concrete
class as the second argument.

Note that the class or interface does not actually need to exist.
LightContainer only checks whether the first argument only contains characters
that can be used as the fully qualified name of a type (i.e. including the
namespace), and if it does, it treats the entry as a global alias.  Otherwise
it treats the entry as a [named instance](#multiple-shared-instances).

```php
$container->set(FooInterface::class, FooInterfaceImpl::class);
```

If an alias is also defined at the class level, that definition takes
precedence over the global alias.

```php
class FooInterfaceImplGlobal implements FooInterface {}
class FooInterfaceImplForD implements FooInterface {}

$container->set(FooInterface::class, FooInterfaceImplGlobal::class);
$container->set(D::class)->alias(FooInterface::class, FooInterfaceImplForD::class);

// D uses FooInterfaceImplForD instead of FooInterfaceImplGlobal
$container->get(D::class);
```

Unlike aliases defined at the class level, you can set instantiation
options for global aliases.  These are applied to objects instantiated
by referring to the identifier of the global alias instead of the concrete
class. The following instantiation options are supported:

- `alias` (additional [class aliases](#aliases))
- `args` ([constructor arguments](#constructor-arguments))
- `call` ([setters](#setter-injection))
- `shared` ([shared instances](#shared-instances))

Instantiation options specified in the global alias takes precedence over
the options defined for the concrete class.

### Multiple shared instances

The [`shared` instantiation option](#shared-instances) will provide a single
shared instance of a class for the entire container.  However, you may want to
have multiple instances of the same class that are shared across the container.

```php
class O {
    public function __construct(\PDO $db) {}
}

$container->set('@prod_db', \PDO::class)
    ->args('mysql:host=example.com;dbname=prod');
$container->set('@dev_db', \PDO::class)
    ->args('mysql:host=example.com;dbname=dev');
$container->set(O::class)->alias(\PDO::class, '@prod_db');
```

Named instances are simply a special kind of [global aliases](#global-aliases),
so they can be used anywhere where an alias is accepted.  In particular, they
are injected into constructors and setter methods using `alias`, not
`args`.

```php
// Correct
$container->set(O::class)->alias(\PDO::class, '@prod_db');

// Incorrect
$container->set(O::class)->args('@prod_db');
```

Furthermore, because named instances are simply a special kind of global
aliases, you can set instantiation options for them (other than `shared`).

### Custom instantiation

Instead of using LightContainer's default instantiation function
with autowiring, you can specify a custom factory function to create
objects of a particular class.  This is done by calling `set`
method with a callable pointing to the factory function as the second
argument.

The container is passed on as the first parameter to the factory
function.

```php
$container->set(CustomClass::class, function ($container) {
    $a = new CustomClass();
    // Custom code here
    return $a;
});
```

[Instantiation options](#instantiation-options) are not available to
custom factory functions.  [Named instances](#multiple-shared-instances) are
also not available, as sharing is not supported for factory functions.
Calls to named instances will simply result in the factory function being
called, which may result in a different instance of the object being
returned.

### Storing arbitrary values

You can store arbitrary values in the container by calling the `set`
method with a non-string, non-callable value as the second argument.

```php
$container->set('@port', 8080);
$container->set('@config', ['foo' => 'bar']);

// Returns 8080
$container->get('@port');
```

It is recommended that you use an identifier that cannot be confused with a
class name (e.g. by adding an invalid character for a class name, like `@`
in the example above).

**WARNING.** If you want to store `null`, a string or a callable, you will need
to wrap it with using `Container::value()`.  Otherwise LightContainer will
treat it as a [global alias](#global-aliases) or a
[custom instantiation function](#custom-instantiation).

```php
// Correct
$container->set('@host',
LightContainer\Container::value('example.com'));

// Incorrect
$container->set('@host', 'example.com');
```

### Configuring services

In certain situations, concrete classes are used to implement services,
which are defined using interfaces.  Containers are then used to link the
services to their implementations.

```php
interface FooService {}
interface BarService {}

class Baz implements FooService, BarService {}
```

Instead of calling the `set` method for each service separately, you can
call the `populate` method on the concrete class.  This method uses reflection
to determine which services the class implements, and then creates
container entries accordingly.

```php
// This:
$container->populate(Baz::class);

// is equivalent to:
$container->set(FooService::class, Baz::class);
$container->set(BarService::class, Baz::class);
```

### Loading configurations

Instead of configuring the container programmatically, you can also load a
pre-defined configuration directly into the container using the `load` method.
This method takes the container configuration as a plain PHP array, which you
can populate using whichever method you want (e.g. loading from a JSON or
YAML file).

```php
try {
    $config = [
        'FooInterface' => 'FooInterfaceImpl',
        '@host' => [ '#value' => 'example.com' ]
    ];
    $container->load($config);
} catch (LightContainer\Loader\LoaderException $e) {
    
}
```

See the [configuration format] documentation for details on how the
configuration array should be structured.  Note that only a subset of
LightContainer's features are supported by this format.

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

The `set` method takes the string entry identifier and the specified value,
creates and registers a resolver, and then returns it to the user.  The
kind of resolver the method creates depends on the format of
the entry identifier, and the type of value that is specified.  These are
set out in the table below.

| Identifier                                   | Value           | Resolver           | Description                                                  |
| -------------------------------------------- | --------------- | ------------------ | ------------------------------------------------------------ |
| Name of an existing class                    | None            | Class resolver     | Sets [instantiation options](#instantiation-options) for that class |
| A valid type name (whether or not it exists) | A string        | Reference resolver | Sets a [global alias](#global-aliases)                       |
| `*`                                          | None            | Class resolver*    | Sets default [instantiation options](#instantiation-options) for autowired resolvers |
| Any other string                             | A string        | Reference resolver | Creates a [named instance](#multiple-shared-instances)       |
| Any other string                             | A callable      | Factory resolver   | Sets a [custom instantiation function](#custom-instantiation) |
| Any other string                             | Any other value | Value resolver     | Stores an [arbitrary value](#storing-arbitrary-values) in the container |

The class resolver returned for `*` is a special kind of class resolver.  It
cannot be called to resolve to an actual object.

### Autowiring

Autowiring works by using PHP reflection to examine the parameters of the
constructor (or setter method).  If any parameter has a type hint, and the
hinted type is not a built-in literal type, it looks up the container to see if
there is an existing entry with the type as the entry identifier. If an entry
does not exist, but the specified type is a class and the class exists,
the container then creates a class resolver (the *autowired resolver*)
for that type automatically.  The instantiation options for this autowired
resolver are set based on the
[propagation rules](#options-for-autowired-resolvers) described above.

Note that when injecting parameters into the constructor or setter method,
the nullable market (`?`) and default value declarations are initially ignored.
The container will try to create and inject an instance of a particular
type even it is declared nullable or contain a default value.  Only if the
container cannot create an instance will a null or default value be used.

```php
interface ImplementedInterface {}
class InterfaceImplementation implements ImplementedInterface {}

interface UnimplementedInterface {}

class P1 {
    function __construct(?ImplementedInterface $i) {}
}
class P2 {
    function __construct(ImplementedInterface $i = null) {}
}

class Q1 {
    function __construct(?UnimplementedInterface $i) {}
}
class Q2 {
    function __construct(UnimplementedInterface $i = null) {}
}

$container->set(ImplementedInterface::class, InterfaceImplementation::class);

// $p1->i and $p2->i will both contain a InterfaceImplementation,
// as an alias is registered for ImplementedInterface and the aliased class
// exists
$p1 = $container->get(P1::class);
$p2 = $container->get(P2::class);

// $q1->i and $q2->i will both be null, as there is no alias registered for
// UnimplementedInterface
$q1 = $container->get(Q1::class);
$q2 = $container->get(Q2::class);
```

In order to pass a null as an argument to a nullable type, you have to use
`alias` to explicitly declare an alias to null.

```php
$container->set(P1::class)->alias(ImplementedInterface::class, null);
$container->set(P2::class)->alias(ImplementedInterface::class, null);

// $p1->i and $p2->i are now both null
$p1 = $container->get(P1::class);
$p2 = $container->get(P2::class);
```

### Instantiation options reference

The following instantiation options are available for class resolvers.  These
options (apart from `propagate`) are also available for
[global aliases](#global-aliases).

| Option                                          | Default                                                      | Description                                                  |
| ----------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------ |
| [`alias`](#aliases)                             | none                                                         | Aliases for type hinted arguments for the constructor and setter methods |
| [`args`](#constructor-arguments)                | none                                                         | Set other arguments for the constructor                      |
| [`call`](#setter-injection)                     | none                                                         | Call setter methods                                          |
| [`modify`](#modifying-resolved-instances)       | none                                                         | Modify resolved instances                                    |
| [`propagate`](#options-for-autowired-resolvers) | true                                                         | Whether instantiation options will propagate to autowired resolvers for subclasses |
| [`shared`](#shared-instances)                   | false (except for [named instances](#multiple-shared-instances)) | Whether the instance resolved is shared across the container |

## Licence

BSD 3 clause

[PSR-11]: https://www.php-fig.org/psr/psr-11/
[entry identifier]: https://www.php-fig.org/psr/psr-11/#:~:text=1.1.1-,Entry%20identifiers,-An%20entry%20identifier
[configuration format]: docs/configuration_format.md

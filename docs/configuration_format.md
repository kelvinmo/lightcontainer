# LightContainer configuration format

LightContainer's `Container` provides the ability to load a pre-defined
configuration using the `load` method.  This document sets out the expected
format for the configuration array that is passed to this method.

The document assumes you have already read the [README] file and have an
understanding of how LightContainer works and its key features.

## Table of contents

<!-- toc -->

* [Introduction](#introduction)
* [Supported features](#supported-features)
* [Basic format](#basic-format)
* [Configuration](#configuration)
  * [Instantiation options](#instantiation-options)
    * [Aliases](#aliases)
    * [Constructor arguments](#constructor-arguments)
    * [Calling methods](#calling-methods)
    * [Shared instances](#shared-instances)
    * [Propagation to autowired resolvers](#propagation-to-autowired-resolvers)
  * [Global aliases](#global-aliases)
  * [Named instances](#named-instances)
  * [Storing values](#storing-values)
  * [Constants and global variables](#constants-and-global-variables)
* [Reference](#reference)
  * [Configuration array](#configuration-array)
  * [Canonical form](#canonical-form)
  * [Reserved keys](#reserved-keys)

<!-- tocstop -->

## Introduction

Instead of configuring the container programmatically, you can load a
pre-defined configuration directly into the container using the `load` method.
This method takes the container configuration as a plain PHP array, which you
can populate using whichever method you want (e.g. loading from a JSON or
YAML file).

```php
try {
    $config = [
        'FooInterface' => 'FooInterfaceImpl',
        '@host' => [ '_value' => 'example.com' ]
    ];
    $container->load($config);
} catch (LightContainer\Loader\LoaderException $e) {
    
}
```

## Supported features

The configuration array only supports a subset to LightContainer's features.

**Supported**

* ✅ All instantiation options
* ✅ Global aliases
* ✅ Named instances
* ✅ Storing arbitrary values: null, booleans, numbers, string, arrays,
  references to PHP constants or global variables
* ✅ Custom resolvers

**Not supported**

* ❎ Specifying references to callables or objects
* ❎ Specifying modifiers using the `modify` instantiation option
* ❎ Custom instantiation
* ❎ Configuring services using `populate`

## Basic format

The container configuration is expressed as an associative array, with the
entry identifier as the key and the configuration for that entry as the value.
The format of the configuration value is in many cases similar to the value 
parameter in the container's `set` method, although there are differences
to accommodate the limitations of PHP arrays.

The following examples compare the way the container is configured using
PHP code, and the equivalent configuration as an array.

```php
// Example 1: Class resolver with no instantiation options
$container->set('A');
[ 'A' => [] ]

// Example 2: Instantiation options
$container->set('B')->alias('FooInterface', 'FooInterfaceImpl');
[ 'B' => ['alias' => ['FooInterface' => 'FooInterfaceImpl']] ]

$container->set('C')->args('example.com', 8080);
[ 'C' => ['args' => ['example.com', 8080]] ]

$container->set('D')->args(
    LightContainer\Container::ref('FooInterfaceImpl'), true
);
[ 'D' => ['args' => [
    ['_ref' => 'FooInterfaceImpl'],
    true
]] ]

$container->set('E')->call('setA', 50)->call('setB');
[ 'E' => ['call' => [
    ['setA', 50],
    ['setB'] 
]] ]

$container->set('F')->shared();
[ 'F' => ['shared' => true] ]

$container->set('G')->propagate(false);
[ 'G' => ['propagate' => false] ]

// Example 3: Global aliases
$container->set('BazInterface', 'BazInterfaceImpl')->shared();
[ 'BarInterface' => [
    '_ref' => 'BarInterfaceImpl',
    'shared' => 'true'
]]

// Example 4: Global aliases - compact format if no instantiation options
$container->set('BarInterface', 'BarInterfaceImpl');
[ 'BarInterface' => 'BarInterfaceImpl' ]

// Example 5: Named instances
$container->set('@prod_db', \PDO::class)
    ->args('mysql:host=example.com;dbname=prod');
$container->set('@dev_db', \PDO::class)
    ->args('mysql:host=example.com;dbname=dev');
[
    '@prod_db' => ['_ref' => 'PDO', 'args' => ['mysql:host=example.com;dbname=prod']],
    '@dev_db' => ['_ref' => 'PDO', 'args' => ['mysql:host=example.com;dbname=dev']]
]

// Example 6: Storing a value
$container->set('@config', ['foo' => 'bar']);
[ '@config' => ['_value' => ['foo' => 'bar']] ]

$container->set('@host',
LightContainer\Container::value('example.com'));
[ '@config' => ['_value' => 'example.com'] ]

// Example 7: Storing a value - compact format for null, bool
// and numeric types
$container->set('@port', 8080);
[ '@port' => 8080 ]
```

In addition, the configuration array also supports special notation to specify
[PHP constants and global variables](#constants-and-global-variables).  These
are useful when the array is loaded from a non-PHP source (such as JSON or
YAML).

## Configuration

### Instantiation options

Class resolvers are configured by using an array as the configuration value
corresponding to the entry identifier (subject to certain exceptions explained
below).

Instantiation options are specified in the contents of this array.  The following
keys can be used as instantiation options, and have the same function as
the corresponding
[configuration methods](README.md#instantiation-options-reference)
available to class resolvers.

* [`alias`](#aliases)
* [`args`](#constructor-arugments)
* [`call`](#calling-methods)
* [`shared`](#shared-instances)
* [`propagate`](#propagation-to-autowired-resolvers)

#### Aliases

Aliases are specified using the `alias` key.  The value is an associative
array of aliased identifiers and their targets (or `null`), each expressed as a
string.

```php
$container->set('B')->alias('FooInterface', 'FooInterfaceImpl');
[ 'B' => ['alias' => ['FooInterface' => 'FooInterfaceImpl']] ]
```

#### Constructor arguments

Constructor arguments are specified using the `args` key, with the
arguments specified as a sequential array.

```php
$container->set('C')->args('example.com', 8080);
[ 'C' => ['args' => ['example.com', 8080]] ]
```

Strings in this sequential array are treated as literal strings.  If you
want to specify a reference to an entry from the container (which you would
use `Container::ref()` for when configuring the container programmatically),
use an associative array with `_ref` as the key and the entry identifier as
the value.

```php
$container->set('D')->args(
    LightContainer\Container::ref('FooInterfaceImpl'), true
);
[ 'D' => ['args' => [
    ['_ref' => 'FooInterfaceImpl'],
    true
]] ]
```

#### Calling methods

Setter methods which are called after object creation are specified using the
`call` key, with the arguments specified as a sequential array (of arrays).
Each element of this array contains an array, the first value of which is
the name of the method to call, and subsequent values (if any) being the
arguments to be passed onto that method.

```php
$container->set('E')->call('setA', 50)->call('setB');
[ 'E' => ['call' => [
    ['setA', 50],
    ['setB'] 
]] ]
```

The format for specifying additional arguments are the
same as for [constructors](#constructor-arguments).

#### Shared instances

Shared instances can be configured by setting the `shared` key to true.

```php
$container->set('F')->shared();
[ 'F' => ['shared' => true] ]
```

#### Propagation to autowired resolvers

You can stop instantiation options from being propagated to autowired resolvers
by setting the `propagate` key to false.

```php
$container->set('G')->propagate(false);
[ 'G' => ['propagate' => false] ]
```

You can also set the default instantiation options for autowired resolvers
using the `*` entry identifier, in the same way these are specified when the
container is configured programmatically.

```php
$container->set('*')->shared();
[ '*' => ['shared' => true]]
```

### Global aliases

Global aliases can be configured by specifying the name of the target under the
`_ref` reserved key.  Additional
[instantiation options](#instantiation-options) can be specified through other
keys in the same array.

```php
$container->set('BazInterface', 'BazInterfaceImpl')->shared();
[ 'BarInterface' => [
    '_ref' => 'BarInterfaceImpl',
    'shared' => 'true'
]]
```

If the global alias does not require any additional instantiation options,
the expression can be shortened by omitting the `_ref` reserved
key and specifying the target entry identifier directly as a string.

```php
$container->set('FooInterface', 'FooInterfaceImpl');
// These two are the same
[ 'FooInterface' => ['_ref' => 'FooInterfaceImpl'] ]
[ 'FooInterface' => 'FooInterfaceImpl' ]
```

In the same way global aliases are defined programmatically, the entry
identifier must only contains characters that can be used as the fully
qualified name of a type (i.e. including the namespace).  Otherwise it treats
the entry as a [named instance](#named-instances).

### Named instances

Named instances are specified in the same way as
[global aliases](#global-aliases).  The only difference is the entry
identifier must contain at least one character that *cannot* be used as the
fully qualified name of a type (i.e. including the namespace).

### Storing values

Stored values can be configured by specifying the value under the
`_value` reserved key:

```php
$container->set('@config', ['foo' => 'bar']);
[ '@config' => ['_value' => ['foo' => 'bar']] ]
```

If the value to be stored is a `null`, a boolean value or a numeric value,
the expression can be shortened by omitting the `_value` reserved
key and specifying the value directly.

```php
$container->set('@port', 8080);
// These two are the same
[ '@port' => ['_value' => 8080] ]
[ '@port' => 8080 ]
```

### Constants and global variables

If configuration array needs to refer to PHP defined constants
or global variables, these can be specified using the `_const` and `_global`
keys respectively.  These may be useful if the configuration array is
decoded from a non-PHP configuration file (e.g. JSON or YAML)

```php
global $foo;
$container->set('@global_foo', $foo);
[ '@global_foo' => ['_global' => 'foo'] ]

define('MY_DSN', 'mysql:host=example.com;dbname=prod');
$container->set('@db', PDO::class)->args(MY_DSN);
[ '@db' => [
    '_ref' => 'PDO',
    'args' => [
        ['_const' => 'MY_DSN']
    ]
]]
```

The configuration array can then be expressed in JSON as follows:

```json
{
    "@global_foo": { "_global": "foo" },
    "@db": {
        "_ref": "PDO",
        "args": [
            { "_const": "MY_DSN" }
        ]
    }
}
```

## Reference

### Configuration array

Like the container itself, the configuration array is a mapping between a
string entry identifier and something which can be parsed into a resolver.
In particular, a resolver that can be loaded in this way must implement
`LightContainer\Loader\LoadableInterface`, which requires a static function
`createFromLoader` to be declared.

There is a *canonical form* for the configuration array. This
form can be used to configure any resolver that implements
`LoadableInterface`.  However, because the canonical form can be cumbersome to
use, more *compact forms* for the most common resolvers can also be used.

All the examples specified in this document so far use the compact form.
The canonical form is described in the following section.

### Canonical form

The canonical form for a resolver is an associative array containing two
keys: `_type` which contains the simplified class name of the resolver,
and `_args` which contains the arguments to be passed into the
`createFromLoader` static method.

The *simplified class name* of the resolver is the fully qualified class
name, with the following modifications.

- Classes within the `LightContainer\Resolvers` namespace
  do not need to be prefixed by the namespace
- Classes which belongs to the root namespace are required
  to be prefixed by a single backslash (`\`)

Examples:

| Class name                               | Simplified class name |
| ---------------------------------------- | --------------------- |
| `LightContainer\Resolvers\ClassResolver` | `ClassResolver`       |
| `Foo\Bar\BazResolver`                    | `Foo\Bar\BazResolver` |
| `BuzzResolver` (root namespace)          | `\BuzzResolver`       |

The format of the *arguments* is dependent on the resolver.

The following are some examples of the canonical form, compared
to the compact form described earlier.

```php
// Class resolver
// canonical
[ 'A' => [
    '_type' => 'ClassResolver',
    '_args' => ['shared' => true]
]]
// compact
[ 'A' => ['shared' => true]]

// Reference resolver
// canonical
[ 'FooInterface' => [
    '_type' => 'ReferenceResolver',
    '_args' => ['target' => 'FooInterfaceImpl']
]]
// compact
[ 'FooInterface' => 'FooInterfaceImpl']

// Value resolver
// canonical
[ '@int_value' => [
    '_type' => 'ValueResolver',
    '_args' => 1
]]
// compact
[ '@int_value' => 1]
```

### Reserved keys

The following keys are reserved in the configuration array.

| Option    | Description                                                  |
| --------- | ------------------------------------------------------------ |
| `_type`   | Canonical form: class name of resolver                       |
| `_args`   | Canonical form: arguments                                    |
| `_const`  | Reference to constant                                        |
| `_global` | Reference to global variable                                 |
| `_ref`    | Reference to an entry in the container (similar to`LightContainer\Container::ref()`) |
| `_value`  | Literal value (similar to`LightContainer\Container::value()`) |



[README]: README.md

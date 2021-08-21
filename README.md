# LightContainer

LightContainer is a simple [PSR-11] compliant dependency injection container
that supports autowiring.

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

## Advanced Usage

## Reference

## Licence

BSD 3 clause

[PSR-11]: https://www.php-fig.org/psr/psr-11/
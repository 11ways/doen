<h1 align="center">
  <b>elevenways/doen</b>
</h1>
<div align="center">
  <!-- Github Actions -->
  <a href="https://github.com/11ways/doen/actions">
    <img src="https://github.com/11ways/doen/actions/workflows/php.yml/badge.svg" alt="Build Status" />
  </a>

  <!-- Coverage - Codecov -->
  <a href="https://codecov.io/gh/11ways/doen">
    <img src="https://img.shields.io/codecov/c/github/11ways/doen/master.svg" alt="Codecov Coverage report" />
  </a>

  <!-- DM - David -->
  <a href="https://david-dm.org/11ways/doen">
    <img src="https://david-dm.org/11ways/doen/status.svg" alt="Dependency Status" />
  </a>
</div>

<div align="center">
  <!-- Version - npm -->
  <a href="https://packagist.org/packages/elevenways/doen">
    <img src="https://img.shields.io/packagist/php-v/elevenways/doen.svg" alt="Latest version on Packagist" />
  </a>

  <!-- License - MIT -->
  <a href="https://github.com/11ways/doen#license">
    <img src="https://img.shields.io/github/license/11ways/doen.svg" alt="Project license" />
  </a>
</div>
<br>
<div align="center">
  Asynchronously call JavaScript code from within PHP
</div>
<div align="center">
  <sub>
    Coded with ❤️ by <a href="#authors">Eleven Ways</a>.
  </sub>
</div>


## Introduction

Have you ever needed to use the functionality of a node.js package in your PHP project, but don't want to spend hours writing a wrapper? Well now you can.

Thanks to ReactPHP it was quite a simple script to get working.

## Installation

Installation is easy via [Composer](https://getcomposer.org/):

```bash
$ composer require elevenways/doen
```

Or you can add it manually to your `composer.json` file.

## Usage

### Simple `require()` example

```php

// You will need an event loop instance.
// If this looks weird to you, you should lookup ReactPHP
$loop = \React\EventLoop\Factory::create();

// Now we can create a Doen instance
$doen = new \Elevenways\Doen\Doen($loop);

// Lets get our first, simple reference
// $libpath is now an \Elevenways\Doen\Reference instance
$libpath = $doen->evaluateToRef('require("path")');

// Even though the code is happening asynchronously, we can already act upon it
// This will return yet another \Elevenways\Doen\Reference instance
$str = $libpath->join('a', 'b', 'c');

// Now we can get the value
$str->getValue()->then(function ($value) {
    // This will print out a/b/c
    echo $value;
});

// This, again, is part of ReactPHP.
// It starts the event loop and will BLOCK the rest of the code!
$loop->run();
```

## API

### Doen

#### new Doen(\React\EventLoop\LoopInterface $loop, array $options = [])

Create a new Doen instance, which always creates a new Node.js instance too.

By default it'll use the `node` binary, but this can be overridden with the `node_path` option.

#### require(string $name) ⇒ `\Elevenways\Doen\Reference`

Require a node.js module and return a reference to it.

```php
$libpath = $doen->require('path');
```

#### evaluate(string $code) ⇒ `\React\Promise\Promise`

Execute an expression and return its value.

```php
$promise = $doen->evaluate('1 + 1');
```

#### evaluateFunction(string $function, $args = []) ⇒ `\React\Promise\Promise`

Execute a function with the supplied arguments and return its value.

```php
$promise = $doen->evaluate('function(a, b) {return a * b}', [3, 2]);
```

#### evaluateToRef(string $code, $args = null) ⇒ `\Elevenways\Doen\Reference`

Execute an expression or a function and return a reference to its value.

```php
$libpath = $doen->evaluateToRef('require("path")');
```

#### close() ⇒ `void`

Close the node.js process

```php
$doen->close();
```

### Reference

#### getValue(callable $on_fulfilled = null, callable $on_rejected = null) ⇒ `\React\Promise\Promise`

Get the actual value of the reference

```php
$ref = $doen->evaluateToRef('1 + 1');
$ref->getValue(function($result) {
    // Outputs: 2
    echo $result;
});
```

#### then(callable $on_fulfilled = null, callable $on_rejected = null) ⇒ `\React\Promise\Promise`

Execute the callables when this reference resolves.
It will **not** resolve to its value, but to its type!

```php
$ref = $doen->evaluateToRef('1 + 1');
$ref->then(function($type) {
    // Outputs: "number"
    echo $type;
});
```


## Contributing
Contributions are REALLY welcome.
Please check the [contributing guidelines](.github/contributing.md) for more details. Thanks!

## Authors
- **Jelle De Loecker** -  *Follow* me on *Github* ([:octocat:@skerit](https://github.com/skerit)) and on  *Twitter* ([🐦@skeriten](http://twitter.com/intent/user?screen_name=skeriten))

See also the list of [contributors](https://github.com/11ways/doen/contributors) who participated in this project.

## License
This project is licensed under the MIT License - see the [LICENSE](https://github.com/11ways/doen/LICENSE) file for details.

## 0.1.3 (WIP)

* Evaluate methods in browser that `ElementHandle` doesn't mirror

## 0.1.2 (2021-08-11)

* Added `Reference->__get` magic method which will return references to the expected properties

## 0.1.1 (2021-03-22)

* Added `Reference->__monkeyPatch($name, $closure)` method to add methods to a reference on-the-fly
* `Reference->then()` will now resolve to the name of the constructor for objects
* Added `JsFunction` class, so functions can now be serialized when sending as an argument
* Allow passing `Reference` instances as function arguments

## 0.1.0 (2021-03-19)

* Initial release
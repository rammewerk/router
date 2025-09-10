CHANGELOG
=========

0.9.9
---

- Added support for prepended middleware on routes. This is now the default for grouped routes, ensuring group middleware runs before each child route’s middleware.
- Minor code improvements and refactoring.

0.9.8
---

- New lookup/matching algorithm for faster route resolution

0.9.6
---

- Dependency resolver is no longer required, but we still recommend it!
- Added support to disable reflection for added routes, which can improve performance when reflection isn't needed.
  `$router->add(...)->disableReflection();`

0.9.5
---

- **Simplified API** – Removed the ability to override the default method in class-based routes. With attribute support,
  this is now the preferred approach for customizing default methods instead of relying on index.
- **Performance Enhancements** – Refactored core logic for better efficiency and faster route resolution.
- **General Improvements** – Minor optimizations and bug fixes for improved stability.

0.9.4
---

- Improved performance throughout the codebase
- New improved radix tree implementation
- Introduced cached parameter resolver
- Removed reflection from parameter resolver cache to allow for serialization
- Utilizing the radix tree to resolve class methods and attributes
- Caching up reflection instances
- Added support for non-backed enums, through matching on case name
- Added multiple new unit tests, ensuring the code is solid
- Added a BETA version of the adapter "MethodAwareRouter" which allows for validating route based on HTTP method. Please
  note that this is still in BETA and may change in the future. Any feedback is welcome!

0.9.3
---

- Added support for enums
- Increased performance by replacing preg_match() with radix tree
- Optimized the way context and paths are handled, string based instead of array based. Benchmarked everything changes
  to make sure there was improvement.
- Better folder structure
- Added tests and organized tests
- Added a simple benchmark tester to help compare different implementations
- Implemented a RouterConfigurationException class to make it more clear what went wrong

0.9.2
---

- Changed method() to classMethod() to avoid confusion with HTTP methods
- Support for Route attributes

0.9.1
---

- Deferred regex generation until needed

0.9.0
---

* Huge refactoring of the codebase, making it more maintainable and easier to extend.
* Please see the README for more information.

0.6.0
---

* Added a way to require that number of parameters must match the number of parameters in the route. Use
  `$route->autoResolve(false);`

0.5.0
---

* Fixed bug where parameter resolver didn't detect class interfaces as dependency.

0.5.0
---

* Added support for routes having parameters of type int. Router will convert path part (string) to int.
* Will now return the value of the method from the find() method

0.1.0
---
Initial beta release
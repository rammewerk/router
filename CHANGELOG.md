CHANGELOG
=========

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
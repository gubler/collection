# Gubler/Collection

This is a fork of [illuminate/support v5.7](https://github.com/illuminate/support/tree/5.7) to just contain the Collection class and supporting classes.

The only purpose of this was to prevent pollution of the global namespace and to reduce the number of dependencies.

This version only depends on `symfony/var-dumper` (not counting dev dependencies) to support the `dd()` method.

This also converts relevant helper methods to a `Helper` class to remove them from the global namespace.

---

[Documentation](docs/collection.md)


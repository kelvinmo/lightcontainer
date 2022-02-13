# Changelog

## 0.1.4

### Changed

- Updated code and documentation to enable better static analysis. The library
  now passes phpstan level 6
- `AutowireInterface` now extends from `ResolverInterface`

### Fixed

- Treatment of bool values in `ValueResolver`

## 0.1.3

### Fixed

- Fix error when loading with array constructor arguments

### Added

- Add the `set` method to `LightContainerInterface`

## 0.1.2

### Fixed

- Fix error when loading options for autowired resolvers

## 0.1.1

### Changed

- Update `composer.json` to support `psr/container` `^1.1^`

## 0.1.0

- Initial version
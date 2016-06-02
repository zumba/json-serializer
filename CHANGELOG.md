# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Changed
- Throwing exception if cannot encode JSON when serializing
- Throwing exception if cannot decode JSON when unserializing
### Deprecated
- Changed namespace from `Zumba\Util` to `Zumba\JsonSerializer`

## [2.0.1] - 2016-03-17
### Fixed
- Fixing float serialization on i18n locales

## [2.0.0] - 2016-02-26
### Added
- Support PHP closures serialization
### Removed
- Dropped support to PHP 5.3
### Changed
- Documentation improvements
- Support to PHP built-in support to float numbers without decimal points
- Using PSR-2 and PSR-4

## [1.0.1] - 2014-07-05
### Added
- Support to DateTime serialization
- Support to unescaped unicode

## [1.0.1] - 2014-04-12
### Fixed
- Fixed serialization of float numbers without decimal points

## [1.0.0] - 2014-01-06
### Added
- Encode/Decode of scalar, null, array
- Encode/Decode of objects
- Support nested serialization
- Support not declared properties on the original class definition (ie, properties in stdClass)
- Support object recursion

# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [3.1.0] - 2023-07-18
### Added
- Support of UnitEnum and BackedEnum deserialization (PHP > 8.1). Thanks @marcimat
- Support to multiple closure serializers
- Built in closure serializer using opis/closure
### Fixed
- Fixed deprecated with DateTimeImmutable deserialization with PHP 8.2

## [3.0.0] - 2020-07-20
### Fixed
- Fixed DateTime & DateTimeImmutable serialization in PHP 7.4+. Thanks @przemyslaw-bogusz
- Testing for PHP 7.3 and 7.4
### Changed
- Minimum PHP version supported is now 7.0
- Updated PHPUnit version to 6.x
### Removed
- Deprecated namespace `Zumba\Util`

## [2.2.0] - 2018-02-07
### Added
- Allowing to change the undeclared property unserialization mode

## [2.1.0] - 2016-05-22
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

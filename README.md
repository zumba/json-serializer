# Json Serializer for PHP

[![Build Status](https://travis-ci.org/zumba/json-serializer.png)](https://travis-ci.org/zumba/json-serializer)
[![Code Coverage](https://scrutinizer-ci.com/g/zumba/json-serializer/badges/coverage.png?s=56e61922c00f25b9afae3e97af853f3eb68d9c1a)](https://scrutinizer-ci.com/g/zumba/json-serializer/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/zumba/json-serializer/badges/quality-score.png?s=16511f820e0c53cdcbbbc62b5de07d493ded1181)](https://scrutinizer-ci.com/g/zumba/json-serializer/)

This is a library to serialize PHP variables into JSON format. You can even serialize objects.
This library also come with a method to unserialize the JSON formatted, converting the data back
to their original objects.

*Json Serializer requires PHP >= 5.4*

## Example

```php

class MyCustomClass {
	public $isItAwesome = true;
	protected $nice = 'very!';
}

$instance = new MyCustomClass();

$serializer = new Zumba\Util\JsonSerializer();
$json = $serializer->serialize($instance);
// $json will contain the content {"@type":"MyCustomClass","isItAwesome":true,"nice":"very!"}

$restoredInstance = $serializer->unserialize($json);
// $restoredInstance will be an instance of MyCustomClass
```

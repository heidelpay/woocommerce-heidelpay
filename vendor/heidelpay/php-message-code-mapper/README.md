[![Codacy Badge](https://api.codacy.com/project/badge/Coverage/1ffcac3178b54be18ec9816ef2db8e4e)](https://www.codacy.com/app/heidelpay/php-message-code-mapper?utm_source=github.com&utm_medium=referral&utm_content=heidelpay/php-messages-code-mapper&utm_campaign=Badge_Coverage)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/1ffcac3178b54be18ec9816ef2db8e4e)](https://www.codacy.com/app/heidelpay/php-message-code-mapper?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=heidelpay/php-messages-code-mapper&amp;utm_campaign=Badge_Grade)
[![Build Status](https://travis-ci.org/heidelpay/php-message-code-mapper.svg?branch=master)](https://travis-ci.org/heidelpay/php-message-code-mapper)
[![Latest Stable on Packagist](https://poser.pugx.org/heidelpay/php-message-code-mapper/v/stable)](https://packagist.org/packages/heidelpay/php-message-code-mapper)
[![PHP 5.6](https://img.shields.io/badge/php-5.6-blue.svg)](http://www.php.net)
[![PHP 7.0](https://img.shields.io/badge/php-7.0-blue.svg)](http://www.php.net)
[![PHP 7.1](https://img.shields.io/badge/php-7.1-blue.svg)](http://www.php.net)
[![PHP 7.2](https://img.shields.io/badge/php-7.2-blue.svg)](http://www.php.net)

![Logo](http://dev.heidelpay.com/devHeidelpay_400_180.jpg)

## heidelpay message code mapper

This library provides user-friendly output of (error)-messages coming from
the heidelpay API.


***1. Installation***

_Composer_
```
composer require heidelpay/php-message-code-mapper
```

_manual Installation_

Download the latest release from github and unpack it into a folder of your
choice inside your project.


***2. Implementation***

_Composer_
```
require_once 'path/to/autoload.php;
use Heidelpay\MessageCodeMapper\MessageCodeMapper;
```

_manual Installation_
```
require_once 'path/to/php-message-code-mapper/lib/MessageCodeMapper.php';
```

Of course, the path needs to match the path from step 1.


***3. Usage***

Assuming you have received an error code from one of our modules or the
heidelpay PHP API and stored it in a variable called `$errorcode`.
To get a message from that code, create a `MessageCodeMapper` instance:
```
$instance = new \Heidelpay\MessageCodeMapper\MessageCodeMapper('de_DE');
```

The constructor takes two (optional) arguments:

1. The locale (e.g. 'en_US', 'de_DE')
2. The path to the locales path (for example you want to use your own locale files) 
containing the .csv files with the codes and messages.

We provide 'de_DE' and 'en_US' locale files with this package. You can find them in the
_lib/locales_ folder. If you want to use one of these, the path doesn't need to be
provided in the constructor.

By default, 'en_US' is used as the locale.


Now you can return or print out the message by calling the `getMessage()` method:

```return $instance->getMessage($errorcode);```
```echo $instance->getMessage($errorcode);```

Error codes are accepted in either the 'XXX.XXX.XXX' or 'HP-Error-XXX.XXX.XXX' format.

## Support
For any issues or questions please get in touch with our support.

#### Web page
https://dev.heidelpay.com/
 
#### Email
support@heidelpay.com
 
#### Phone
+49 (0)6221/6471-100

#### Twitter
@devHeidelpay
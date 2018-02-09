<?php

namespace Heidelpay\MessageCodeMapper;

use Heidelpay\MessageCodeMapper\Exceptions\MissingLocaleFileException;
use Heidelpay\MessageCodeMapper\Helpers\FileSystem;

/**
 * This class provides a user-friendly printing of heidelpay error-codes.
 *
 * The locale refers to the .csv files in the lib/locales path, which contain
 * the error codes and the appropriate messages.
 * The path is important when own implementations have to be used.
 *
 * @license Use of this software requires acceptance of the License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/php-messages-code-mapper
 *
 * @author Stephano Vogel
 *
 * @package heidelpay
 * @subpackage php-messages-code-mapper
 * @category php-messages-code-mapper
 */
class MessageCodeMapper
{
    /** @var string the default locale to be used */
    const DEFAULT_LOCALE = 'en_US';

    /** @var FileSystem A helper class for file handling. */
    private $fileSystem = null;

    /** @var string The locale (IETF tag is recommended) to be used by the library. */
    private $locale;

    /** @var string The path of the locale file. */
    private $path;

    /** @var array Contains all customer messages. */
    private $messages;

    /**
     * The CustomerMessage constructor, which accepts the locale and
     * an optional different path that may not be the library's
     * path - so that own locale files can be used.
     *
     * @param string $locale (optional) The locale for the language to be used.
     * @param string $path   (optional)
     *
     * @throws MissingLocaleFileException
     */
    public function __construct($locale = 'en_US', $path = null)
    {
        $this->locale = $locale;

        if ($path !== null && is_string($path)) {
            $this->path = $path;
        }

        // if the locale file does not exist, we set the default locale
        if (! file_exists($this->getLocalePath())) {
            $this->locale = self::DEFAULT_LOCALE;
        }

        // if the locale file still does not exist, we better throw an exception
        // instead of just let PHP error_log something.
        if (! file_exists($this->getLocalePath())) {
            throw new MissingLocaleFileException(
                "Locale file {$this->getLocalePath()} does not exist."
            );
        }

        // create an array of all message codes and messages
        // to prevent accessing the file each time.
        $this->setContent();
    }

    /**
     * Returns the locale name.
     *
     * @return string Current locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Returns the full path for the locale file.
     *
     * @return string Full file path of the locale file
     */
    public function getLocalePath()
    {
        return $this->getPath() . '/' . $this->getLocale() . '.csv';
    }

    /**
     * Returns the path for the locale file.
     *
     * @return string Path of the locale file
     */
    public function getPath()
    {
        return $this->path ?: __DIR__ . '/locales';
    }

    /**
     * Returns a message for the given message code.
     *
     * @param string $messagecode The heidelpay message code
     *
     * @return string The customer message that will be displayed
     */
    public function getMessage($messagecode)
    {
        if (preg_match('/^\d{3}\.\d{3}\.\d{3}$/', $messagecode)) {
            $messagecode = 'HPError-' . $messagecode;
        }

        return isset($this->messages[$messagecode])
            ? $this->messages[$messagecode]
            : $this->getDefaultMessage($messagecode);
    }

    /**
     * Returns a default message, if the message code
     * is not specified in the locale file.
     *
     * @param string $messagecode The message code that will be displayed if no default is set.
     *
     * @return string The customer message that will be displayed if the error code is not defined.
     */
    public function getDefaultMessage($messagecode = '000.000.000')
    {
        return isset($this->messages['Default'])
            ? $this->messages['Default']
            : "An unspecific error occured. HPErrorcode: {$messagecode}";
    }

    /**
     * Sets the $_messages array, where the customer messages are stored.
     *
     * @return void
     */
    private function setContent()
    {
        // open the fs to retrieve file information.
        $this->fileSystem = new FileSystem($this->getLocalePath());
        $this->messages = $this->fileSystem->getCsvContent();
    }
}

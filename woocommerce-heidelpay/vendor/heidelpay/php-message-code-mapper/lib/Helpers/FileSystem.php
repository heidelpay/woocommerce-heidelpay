<?php

namespace Heidelpay\MessageCodeMapper\Helpers;

/**
 * This class provides the functionality for reading the locale files.
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
class FileSystem
{
    /** @var resource The file handler for the locale file */
    private $handle;

    /**
     * The FileSystem constructor that creates the file handler.
     *
     * @param string $path The path to the file that will be opened
     */
    public function __construct($path)
    {
        // we just want to read files, so mode 'r' will be fine at all.
        $this->handle = fopen($path, 'r');
    }

    /**
     * Destructs the instance and closes the file handle.
     */
    public function __destruct()
    {
        // no matter what, we want to close the handle as
        // soon as this instance is not needed anymore.
        fclose($this->handle);
    }

    /**
     * Read the csv file and returns all
     * of it's content as an array.
     *
     * @return array The content of the file.
     */
    public function getCsvContent()
    {
        $ret = [];

        // instead of returning an array for each element, we create
        // an array with the error-code as key and the message as value.
        while ($content = fgetcsv($this->handle)) {
            // 0 = HPError-Code, 1 = Message
            if (isset($content[0]) && isset($content[1])) {
                $ret[$content[0]] = $content[1];
            }
        }

        // reset the file pointer, if we want to read the file again.
        rewind($this->handle);

        // return the array.
        return $ret;
    }
}

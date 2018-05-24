<?php

namespace Tests\Unit;

use Heidelpay\MessageCodeMapper\MessageCodeMapper;
use Heidelpay\MessageCodeMapper\Exceptions\MissingLocaleFileException;
use PHPUnit\Framework\TestCase;

/**
 * This class provides unit tests for the CustomerMessage implementation.
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
class MessageCodeMapperTest extends TestCase
{
    /**
     * Unit test for the correct locale after object initialization.
     *
     * @test
     */
    public function displayCorrectLocale()
    {
        $message = new MessageCodeMapper('de_DE');
        $this->assertEquals('de_DE', $message->getLocale());
    }

    /**
     * Unit test that checks if the output of getMessage will be the same with
     * the default locale, nevertheless 'HPError-' is part of the parameter
     * string or not.
     *
     * @test
     */
    public function displayCorrectMessageOutputEn()
    {
        // init instance with defaults (en_US locale, default path)
        $message = new MessageCodeMapper();

        // expect the correct message when the error number is provided.
        $this->assertEquals('Card expired', $message->getMessage('100.100.303'));

        // expect the correct message when 'HPError' is part of the parameter string
        // instead of only the Error number.
        $this->assertEquals('Card expired', $message->getMessage('HPError-100.100.303'));
    }

    /**
     * Unit test that checks if the output of getMessage will be the same with
     * the de_DE locale, nevertheless 'HPError-' is part of the parameter
     * string or not.
     *
     * @test
     */
    public function displayCorrectMessageOutputDe()
    {
        // init instance with defaults (en_US locale, default path)
        $message = new MessageCodeMapper('de_DE');

        // expect the correct message when the error number is provided.
        $this->assertEquals('Die Karte ist abgelaufen', $message->getMessage('100.100.303'));

        // expect the correct message when 'HPError' is part of the parameter string
        // instead of only the Error number.
        $this->assertEquals('Die Karte ist abgelaufen', $message->getMessage('HPError-100.100.303'));
    }

    /**
     * Unit test that checks if the default message will be printed if the
     * provided message code does not exist, but the 'Default' value
     * is defined in the en_US locale file.
     *
     * @test
     */
    public function displayDefaultMessageOutputEn()
    {
        // initialize the class with defaults (en_US locale, library path).
        $message = new MessageCodeMapper();

        // we expect the default message, because error 987.654.321 might not exist
        // in the default locale file en_US.csv.
        $this->assertEquals(
            'An unexpected error occurred. Please contact us to get further information.',
            $message->getMessage('987.654.321')
        );
    }

    /**
     * Unit test that checks if the default message will be printed if the
     * provided message code does not exist, but the 'Default' value
     * is defined in the de_DE locale file.
     *
     * @test
     */
    public function displayDefaultMessageOutputDe()
    {
        // initialize the class with defaults (en_US locale, library path).
        $message = new MessageCodeMapper('de_DE');

        // we expect the default message, because error 987.654.321 might not exist
        // in the default locale file en_US.csv.
        $this->assertEquals(
            'Es ist ein Fehler aufgetreten. Bitte kontaktieren Sie uns f&uuml;r weitere Informationen.',
            $message->getMessage('987.654.321')
        );
    }

    /**
     * Unit test that checks if an exception is thrown when the locale file is
     * not present (e.g. because the selected language does not exist).
     *
     * @test
     */
    public function throwMissingLocaleFileException()
    {
        $this->expectException(MissingLocaleFileException::class);

        $message = new MessageCodeMapper('ab_CD', 'invalid/path');
        $this->assertEquals('en_US', $message->getLocale());
        echo $message->getMessage('HPError-100.100.101');
    }
}

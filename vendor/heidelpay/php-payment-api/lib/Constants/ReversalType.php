<?php

namespace Heidelpay\PhpPaymentApi\Constants;

/**
 * Class for reversal type constants
 *
 * @license Use of this software requires acceptance of the License Agreement. See LICENSE file.
 * @copyright Copyright © 2017-present heidelpay GmbH. All rights reserved.
 *
 * @link http://dev.heidelpay.com/php-payment-api
 *
 * @author David Owusu <development@heidelpay.com>
 *
 * @package heidelpay\php-payment-api\constants
 */
class ReversalType
{
    const RT_CANCEL = 'CANCEL';
    const RT_RETURN = 'RETURN';
    const RT_CREDIT = 'CREDIT';
}

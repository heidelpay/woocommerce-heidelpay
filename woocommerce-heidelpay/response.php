<?php
/*
* response
*/

if(isset($_POST) && !empty($_POST)) {


    $proc_Result = !empty($_POST['PROCESSING_RESULT']) ? htmlspecialchars($_POST['PROCESSING_RESULT']) : '';
    $proc_Return = !empty($_POST['PROCESSING_RETURN']) ? htmlspecialchars($_POST['PROCESSING_RETURN']) : '';
    $proc_ReturnCode = !empty($_POST['PROCESSING_RETURN_CODE']) ? htmlspecialchars($_POST['PROCESSING_RETURN_CODE']) : '';

    $proc_StatusCode = !empty($_POST['PROCESSING_STATUS_CODE']) ? htmlspecialchars($_POST['PROCESSING_STATUS_CODE']) : '';

    $crit_UserId = !empty($_POST['CRITERION_USER_ID']) ? htmlspecialchars($_POST['CRITERION_USER_ID']) : '';
    $crit_DbOnRg = !empty($_POST['CRITERION_DBONRG']) ? htmlspecialchars($_POST['CRITERION_DBONRG']) : '';
    $crit_Secret = !empty($_POST['CRITERION_SECRET']) ? htmlspecialchars($_POST['CRITERION_SECRET']) : '';
    $crit_SessionID = !empty($_POST['CRITERION_SESSIONID']) ? htmlspecialchars($_POST['CRITERION_SESSIONID']) : '';

    $pay_Code = !empty($_POST['PAYMENT_CODE']) ? htmlspecialchars($_POST['PAYMENT_CODE']) : '';
    $ident_TransId = !empty($_POST['IDENTIFICATION_TRANSACTIONID']) ? htmlspecialchars($_POST['IDENTIFICATION_TRANSACTIONID']) : '';
    $ident_Uid = !empty($_POST['IDENTIFICATION_UNIQUEID']) ? htmlspecialchars($_POST['IDENTIFICATION_UNIQUEID']) : '';
    $ident_Sid = !empty($_POST['IDENTIFICATION_SHORTID']) ? htmlspecialchars($_POST['IDENTIFICATION_SHORTID']) : '';
    $ident_CredId = !empty($_POST['IDENTIFICATION_CREDITOR_ID']) ? htmlspecialchars($_POST['IDENTIFICATION_CREDITOR_ID']) : '';
    $acc_ExpMon = !empty($_POST['ACCOUNT_EXPIRY_MONTH']) ? htmlspecialchars((int)$_POST['ACCOUNT_EXPIRY_MONTH']) : '';
    $acc_ExpYear = !empty($_POST['ACCOUNT_EXPIRY_YEAR']) ? htmlspecialchars((int)$_POST['ACCOUNT_EXPIRY_YEAR']) : '';
    $acc_Brand = !empty($_POST['ACCOUNT_BRAND']) ? htmlspecialchars($_POST['ACCOUNT_BRAND']) : '';
    $acc_Holder = !empty($_POST['ACCOUNT_HOLDER']) ? htmlspecialchars($_POST['ACCOUNT_HOLDER']) : '';
    $acc_Iban = !empty($_POST['ACCOUNT_IBAN']) ? htmlspecialchars($_POST['ACCOUNT_IBAN']) : '';
    $acc_Bic = !empty($_POST['ACCOUNT_BIC']) ? htmlspecialchars($_POST['ACCOUNT_BIC']) : '';
    $acc_Numb = !empty($_POST['ACCOUNT_NUMBER']) ? htmlspecialchars($_POST['ACCOUNT_NUMBER']) : '';
    $acc_Bank = !empty($_POST['ACCOUNT_BANK']) ? htmlspecialchars($_POST['ACCOUNT_BANK']) : '';
    $acc_Ident = !empty($_POST['ACCOUNT_IDENTIFICATION']) ? htmlspecialchars($_POST['ACCOUNT_IDENTIFICATION']) : '';
    $cnt_Mail = !empty($_POST['CONTACT_EMAIL']) ? htmlspecialchars($_POST['CONTACT_EMAIL']) : '';
    $trans_Chan = !empty($_POST['TRANSACTION_CHANNEL']) ? htmlspecialchars($_POST['TRANSACTION_CHANNEL']) : '';

    $var_Pay = !empty($_POST['payment']) ? htmlspecialchars($_POST['payment']) : '';
    $var_Conditions = !empty($_POST['conditions']) ? htmlspecialchars($_POST['conditions']) : '';
    $var_Withdrawal = !empty($_POST['withdrawal']) ? htmlspecialchars($_POST['withdrawal']) : '';
    $var_Comments = !empty($_POST['comments']) ? htmlspecialchars($_POST['comments']) : '';
    $var_sepa = !empty($_POST['hpdd_sepa']) ? htmlspecialchars($_POST['hpdd_sepa']) : '';


    $orgHash = createSecretHash($ident_TransId);

    if ($crit_Secret != $orgHash) {
        log("\n\tHash verification error, suspecting manipulation:
            \n\tIP: " . $_SERVER['REMOTE_ADDR'] .
            "\n\tHash: " . $orgHash .
            "\n\tResponseHash: " . $crit_Secret
        );

        // redirect to error page
        print $base . 'checkout_payment.php?payment_error=ERROR';

        exit;
    }

    saveRes($_POST);

    if ($proc_Result == 'ACK') {
        $payType = strtolower(substr($pay_Code, 0, 2));
        $transType = strtolower(substr($pay_Code, 3, 2));

        if (($transType == 'db') || ($transType == 'pa') || ((($payType == 'ot') || ($payType == 'pc')) && ($transType == 'rc'))) {

            // set order state: debit or reservation
            $status = '';

            //$hgw->setOrderStatus($order_id, $status);
            //$hgw->addHistoryComment($order_id, $comment, $status);

            print $base . 'checkout_process.php';
        } else {
            if ($crit_DbOnRg) {
                print $base . 'ext/heidelpay/heidelpayGW_gateway.php';
            } else {
                $_SESSION['conditions'] = $var_Conditions;
                $_SESSION['withdrawal'] = $var_Withdrawal;
                $_SESSION['payment'] = $var_Pay;
                $_SESSION['comments'] = $var_Comments;

                print $base . 'checkout_confirmation.php';
            }
        }
    } else {
        print $base . 'checkout_payment.php?payment_error=' . $_SESSION['payment'];
    }
} else
    //exit on rouge access
    exit;

/**
 * function to generate a hash
 * @param string $orderId
 * @return string $hash
 */
function createSecretHash($orderId){

    $getConf = $this->getConf;
    $hash = hash('sha512', $orderId.$getConf['secret']);

    return $hash;
}

/*
	 * save transaction response to db
	 */
public function saveRes($paramsRaw) {
    foreach ($paramsRaw as $key => $value) {
        $params[str_replace('_', '.', $key)] = $value;
    }

    if (!empty($params['IDENTIFICATION.UNIQUEID'])) {

        foreach ($params as $key => $value) {
            $params[$key] = $value;
        }
        // to-do: TRANSACTION_SOURCE = shop oder xml
        $serial = json_encode($params);
        $payType = substr($params['PAYMENT.CODE'], 0, 2);
        $transType = substr($params['PAYMENT.CODE'], 3, 2);

        $db = StaticGXCoreLoader::getDatabaseQueryBuilder();
        //search if an entry in hp-transaction-table exists
        $sql = 'SELECT `id` FROM `heidelpayGW_transactions` WHERE `IDENTIFICATION_UNIQUEID`= ?;';
        $query = $db->query($sql, array($params['IDENTIFICATION.UNIQUEID']));
        $result = $query->row_array();

        if (!empty($result)) {
            $id = $result['id'];
        }

        // check if DB-Entry already exists
        if ($id > 0) {

            if ($params['TRANSACTION.SOURCE'] == 'PUSH') {
                $sql = "UPDATE `heidelpayGW_transactions` SET
							PROCESSING_RESULT		= ?,
							PROCESSING_RETURN_CODE	= ?,
							PROCESSING_STATUS_CODE	= ?,
							TRANSACTION_SOURCE		= ?,
							IDENTIFICATION_REFERENCEID=?,
							jsonresponse			= ?,
							created					= NOW()
							WHERE `id`				= ?";

                $query = $db->query($sql, array(
                    $params['PROCESSING.RESULT'],
                    $params['PROCESSING.RETURN.CODE'],
                    $params['PROCESSING.STATUS.CODE'],
                    $params['TRANSACTION.SOURCE'],
                    $params['IDENTIFICATION.REFERENCEID'],
                    $serial,
                    $id,
                ));

                $affRows = $db->affected_rows();
                if ($affRows <= 0) {
                    /* Schreibe Logeintrag */;
                    $this->log(__FILE__, "
							\n\tSQL-Error while saving in heidelpay_transactions:
							\n\tError: " . $query->error()
                    );
                }
            } else {
                $sql = "UPDATE `heidelpayGW_transactions` SET
							meth						= ?,
							type						= ?,
							IDENTIFICATION_UNIQUEID		= ?,
							IDENTIFICATION_SHORTID		= ?,
							IDENTIFICATION_TRANSACTIONID= ?,
							IDENTIFICATION_REFERENCEID	= ?,
							PROCESSING_RESULT			= ?,
							PROCESSING_RETURN_CODE		= ?,
							PROCESSING_STATUS_CODE		= ?,
							TRANSACTION_SOURCE			= ?,
							TRANSACTION_CHANNEL			= ?,
							jsonresponse				= ?,
							created						= NOW()
							WHERE `id`					= ?";

                $query = $db->query($sql, array(
                    $payType,
                    $transType,
                    $params['IDENTIFICATION.UNIQUEID'],
                    $params['IDENTIFICATION.SHORTID'],
                    $params['IDENTIFICATION.TRANSACTIONID'],
                    $params['IDENTIFICATION.REFERENCEID'],
                    $params['PROCESSING.RESULT'],
                    $params['PROCESSING.RETURN.CODE'],
                    $params['PROCESSING.STATUS.CODE'],
                    $params['TRANSACTION.SOURCE'],
                    $params['TRANSACTION.CHANNEL'],
                    $serial,
                    $id
                ));

                $affRows = $db->affected_rows();
                if ($affRows <= 0) {
                    /* Schreibe Logeintrag */;
                    $this->log(__FILE__, "
							\n\tSQL-Error while saving in heidelpay_transactions:
							\n\tError: " . $query->error()
                    );
                }

            }
        } else {
            $sql = "
				INSERT INTO `heidelpayGW_transactions` (
						meth, type,	IDENTIFICATION_UNIQUEID, IDENTIFICATION_SHORTID, IDENTIFICATION_TRANSACTIONID, IDENTIFICATION_REFERENCEID,
						PROCESSING_RESULT, PROCESSING_RETURN_CODE, PROCESSING_STATUS_CODE, TRANSACTION_SOURCE, TRANSACTION_CHANNEL,	jsonresponse,
						created)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

            $params['IDENTIFICATION.REFERENCEID'] = empty($params['IDENTIFICATION.REFERENCEID']) ? '' : $params['IDENTIFICATION.REFERENCEID'];
            $params['TRANSACTION.SOURCE'] = empty($params['TRANSACTION.SOURCE']) ? 'RESPONSE' : $params['TRANSACTION.SOURCE'];

            $query = $db->query($sql, array(
                $payType, $transType, $params['IDENTIFICATION.UNIQUEID'], $params['IDENTIFICATION.SHORTID'], $params['IDENTIFICATION.TRANSACTIONID'], $params['IDENTIFICATION.REFERENCEID'],
                $params['PROCESSING.RESULT'], $params['PROCESSING.RETURN.CODE'], $params['PROCESSING.STATUS.CODE'], $params['TRANSACTION.SOURCE'], $params['TRANSACTION.CHANNEL'], $serial,
            ));

            $affRows = $db->affected_rows();

            if ($affRows <= 0) {
                /* Schreibe Logeintrag */;
                $this->log(__FILE__, "
							\n\tSQL-Error while saving in heidelpay_transactions:
							\n\tError: " . $query->error()
                );
            }
        }
    }

/**
 * logging
 */
public function log( $message ) {
    if ( empty( self::$log ) ) {
        self::$log = new WC_Logger();
    }

    self::$log->add( 'heidelpay-woocommerce', $message );
}
?>
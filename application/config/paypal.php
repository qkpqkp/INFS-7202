<?php
/** set your paypal credential **/

$config['client_id'] = 'AcxFYK27J9hXOXWS4oYlf0re-o9TO3zI-FVkt_4LH6JiwRVlSUu0UOk4k7uTDxoPYtJNsetSvPqqnEGP';
$config['secret'] = 'ENwcs52g3xkGhdQSXUzbp2v75xfhZKapMlEcpvRkQaOtxfw3Q0oGoRymXh0HPG8uoFAasXEh5WJp0kMN';

/**
 * SDK configuration
 */
/**
 * Available option 'sandbox' or 'live'
 */
$config['settings'] = array(

    'mode' => 'sandbox',
    /**
     * Specify the max request time in seconds
     */
    'http.ConnectionTimeOut' => 1000,
    /**
     * Whether want to log to a file
     */
    'log.LogEnabled' => true,
    /**
     * Specify the file that want to write on
     */
    'log.FileName' => 'application/logs/paypal.log',
    /**
     * Available option 'FINE', 'INFO', 'WARN' or 'ERROR'
     *
     * Logging is most verbose in the 'FINE' level and decreases as you
     * proceed towards ERROR
     */
    'log.LogLevel' => 'FINE'
);
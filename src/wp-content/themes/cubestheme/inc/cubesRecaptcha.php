<?php

/**
 * Cubestheme_Recaptcha Class
 *
 * @since    2.0.0
 * @package  cubestheme
 */
if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('Cubestheme_Recaptcha')) :
    class Cubestheme_Recaptcha_Response
    {
        public $success;
        public $errorCodes;
    }
endif;
if (! class_exists('Cubestheme_Recaptcha')) :
    $reCaptchaSecret = "6LcKFL8sAAAAADCnE6DmNyE5N1ZpNCdyMckNvdXz";
    $reCaptchaSiteKey = "6LcKFL8sAAAAABTgbTp8N3dkTvHAilyB_qG13Q3a";
    /**
     * The main Cubestheme_Recaptcha class
     */
    class Cubestheme_Recaptcha
    {
        /**
         * Params
         */
        private static $_signupUrl = "https://www.google.com/recaptcha/admin";
        private static $_siteVerifyUrl = "https://www.google.com/recaptcha/api/siteverify?";
        private $_secret;
        private static $_version = "php_1.0";
        /**
         * Setup class.
         *
         * @since 1.0
         */
        public function __construct($secret = "6LcKFL8sAAAAADCnE6DmNyE5N1ZpNCdyMckNvdXz")
        {
            if ($secret == null || $secret == "") {
                die("To use reCAPTCHA you must get an API key from <a href='"
                    . self::$_signupUrl . "'>" . self::$_signupUrl . "</a>");
            }
            $this->_secret = $secret;
        }
        /**
         * Encodes the given data into a query string format.
         *
         * @param array $data array of string elements to be encoded.
         *
         * @return string - encoded request.
         */
        private function _encodeQS($data)
        {
            $req = "";
            foreach ($data as $key => $value) {
                $req .= $key . '=' . urlencode(stripslashes($value)) . '&';
            }
            // Cut the last '&'
            $req = substr($req, 0, strlen($req) - 1);
            return $req;
        }
        /**
         * Submits an HTTP GET to a reCAPTCHA server.
         *
         * @param string $path url path to recaptcha server.
         * @param array  $data array of parameters to be sent.
         *
         * @return array response
         */
        private function _submitHTTPGet($path, $data)
        {
            $req = $this->_encodeQS($data);
            $response = file_get_contents($path . $req);
            return $response;
        }
        /**
         * Calls the reCAPTCHA siteverify API to verify whether the user passes
         * CAPTCHA test.
         *
         * @param string $remoteIp   IP address of end user.
         * @param string $response   response string from recaptcha verification.
         *
         * @return ReCaptchaResponse
         */
        public function verifyResponse($remoteIp, $response)
        {
            // Discard empty solution submissions
            if ($response == null || strlen($response) == 0) {
                $recaptchaResponse = new Cubestheme_Recaptcha_Response();
                $recaptchaResponse->success = false;
                $recaptchaResponse->errorCodes = 'missing-input';
                return $recaptchaResponse;
            }
            $getResponse = $this->_submitHttpGet(
                self::$_siteVerifyUrl,
                array(
                    'secret' => $this->_secret,
                    'remoteip' => $remoteIp,
                    'v' => self::$_version,
                    'response' => $response
                )
            );
            $answers = json_decode($getResponse, true);
            $recaptchaResponse = new Cubestheme_Recaptcha_Response();
            if (trim($answers['success']) == true) {
                $recaptchaResponse->success = true;
            } else {
                $recaptchaResponse->success = false;
                $recaptchaResponse->errorCodes = (isset($answers['errorCodes']) ? $answers['errorCodes'] : "error");
            }
            return $recaptchaResponse;
        }
    }
endif;

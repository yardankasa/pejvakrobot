<?php
/**
 * توابع پرداخت Zibal
 * این فایل از constants تعریف شده در config.php استفاده می‌کند
 * 
 * توجه: ZIBAL_MERCHANT_KEY در config.php از .env خوانده می‌شود
 */

// اطمینان از بارگذاری config.php
if (!defined('ZIBAL_MERCHANT_KEY')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * connects to zibal's rest api
 * @param $path
 * @param $parameters
 * @return stdClass
 */
function postToZibal($path, $parameters)
{
    $url = 'https://gateway.zibal.ir/v1/'.$path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // timeout 15 ثانیه
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // timeout اتصال 10 ثانیه
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || !empty($curl_error)) {
        error_log("خطا در ارتباط با Zibal API: " . $curl_error);
        $error_response = new stdClass();
        $error_response->result = -1;
        $error_response->message = "خطا در ارتباط با درگاه پرداخت: " . $curl_error;
        return $error_response;
    }
    
    if ($http_code != 200) {
        error_log("خطا در پاسخ Zibal API - HTTP Code: " . $http_code);
        $error_response = new stdClass();
        $error_response->result = -1;
        $error_response->message = "خطا در ارتباط با درگاه پرداخت (کد: $http_code)";
        return $error_response;
    }
    
    $decoded = json_decode($response);
    if ($decoded === null) {
        error_log("خطا در decode پاسخ Zibal: " . json_last_error_msg());
        $error_response = new stdClass();
        $error_response->result = -1;
        $error_response->message = "خطا در پردازش پاسخ درگاه پرداخت";
        return $error_response;
    }
    
    return $decoded;
}

/**
 * returns a string message based on result parameter from curl response
 * @param $code
 * @return String
 */
function resultCodes($code)
{
    switch ($code) 
    {
        case 100:
            return "با موفقیت تایید شد";
        
        case 102:
            return "merchant یافت نشد";

        case 103:
            return "merchant غیرفعال";

        case 104:
            return "merchant نامعتبر";

        case 201:
            return "قبلا تایید شده";
        
        case 105:
            return "amount بایستی بزرگتر از 1,000 ریال باشد";

        case 106:
            return "callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)";

        case 113:
            return "amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.";

        case 201:
            return "قبلا تایید شده";
        
        case 202:
            return "سفارش پرداخت نشده یا ناموفق بوده است";

        case 203:
            return "trackId نامعتبر می‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
}

/**
 * returns a string message based on status parameter from $_GET
 * @param $code
 * @return String
 */
function statusCodes($code)
{
    switch ($code) 
    {
        case -1:
            return "در انتظار پردخت";
        
        case -2:
            return "خطای داخلی";

        case 1:
            return "پرداخت شده - تاییدشده";

        case 2:
            return "پرداخت شده - تاییدنشده";

        case 3:
            return "لغوشده توسط کاربر";
        
        case 4:
            return "‌شماره کارت نامعتبر می‌باشد";

        case 5:
            return "‌موجودی حساب کافی نمی‌باشد";

        case 6:
            return "رمز واردشده اشتباه می‌باشد";

        case 7:
            return "‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد";
        
        case 8:
            return "‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 9:
            return "مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 10:
            return "‌صادرکننده‌ی کارت نامعتبر می‌باشد";
        
        case 11:
            return "خطای سوییچ";

        case 12:
            return "کارت قابل دسترسی نمی‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
}

?>

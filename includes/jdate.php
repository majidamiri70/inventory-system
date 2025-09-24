<?php
// includes/jdate.php - سیستم تاریخ شمسی
class JDate {
    
    // تبدیل تاریخ میلادی به شمسی
    public static function gregorianToJalali($gy, $gm, $gd) {
        $g_d_m = array(0,31,59,90,120,151,181,212,243,273,304,334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm-1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        return array($jy, $jm, $jd);
    }
    
    // تبدیل تاریخ شمسی به میلادی
    public static function jalaliToGregorian($jy, $jm, $jd) {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (((int)($jy / 33)) * 8) + ((int)((($jy % 33) + 3) / 4)) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * ((int)($days / 146097));
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * ((int)(--$days / 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        foreach(array(0,31,(($gy % 4 == 0 and $gy % 100 != 0) or ($gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31) as $gm => $v) {
            if ($gd <= $v) break;
            $gd -= $v;
        }
        return array($gy, $gm, $gd);
    }
    
    // فرمت‌دهی تاریخ شمسی
    public static function jdate($format, $timestamp = null, $timezone = null) {
        if ($timestamp === null) $timestamp = time();
        if ($timezone !== null) date_default_timezone_set($timezone);
        
        // ایجاد آرایه ماه‌های شمسی
        $jmonths = array(
            'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        );
        
        // تبدیل به تاریخ شمسی
        $date = date('Y-m-d H:i:s', $timestamp);
        list($gY, $gM, $gD) = explode('-', date('Y-m-d', $timestamp));
        list($jY, $jM, $jD) = self::gregorianToJalali($gY, $gM, $gD);
        
        // فرمت‌دهی
        $format = str_replace(
            array('Y', 'y', 'm', 'n', 'M', 'F', 'd', 'j'),
            array(
                $jY,                    // Y سال چهار رقمی
                substr($jY, -2),        // y سال دو رقمی
                sprintf('%02d', $jM),   // m ماه دو رقمی
                $jM,                    // n ماه بدون صفر
                $jmonths[$jM-1],        // M نام کوتاه ماه
                $jmonths[$jM-1],        // F نام کامل ماه
                sprintf('%02d', $jD),   // d روز دو رقمی
                $jD                     // j روز بدون صفر
            ),
            $format
        );
        
        return $format;
    }
    
    // دریافت تاریخ شمسی امروز
    public static function now($format = 'Y/m/d') {
        return self::jdate($format);
    }
    
    // تبدیل تاریخ شمسی به میلادی برای دیتابیس
    public static function jalalitoGregorianForDB($jdate) {
        $jdate = str_replace('/', '-', $jdate);
        list($jY, $jM, $jD) = explode('-', $jdate);
        list($gY, $gM, $gD) = self::jalaliToGregorian($jY, $jM, $jD);
        return sprintf('%04d-%02d-%02d', $gY, $gM, $gD);
    }
    
    // تبدیل تاریخ میلادی به شمسی برای نمایش
    public static function gregorianToJalaliForDisplay($gdate) {
        list($gY, $gM, $gD) = explode('-', $gdate);
        list($jY, $jM, $jD) = self::gregorianToJalali($gY, $gM, $gD);
        return sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
    }
}

// تابع global برای استفاده آسان
function jdate($format, $timestamp = null) {
    return JDate::jdate($format, $timestamp);
}

function now($format = 'Y/m/d') {
    return JDate::now($format);
}
?>
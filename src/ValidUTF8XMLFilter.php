<?php

namespace Bolt\Extension\Bolt\Importwxr;

/**
 * Class ValidUTF8XMLFilter
 * @FIX: Char 0x0 out of allowed range simplexml_load_file issue
 * @see: http://stackoverflow.com/questions/3466035/how-to-skip-invalid-characters-in-xml-file-using-php
 */
class ValidUTF8XMLFilter extends \php_user_filter
{
    protected static $pattern = '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u';

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = preg_replace(self::$pattern, '', $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}
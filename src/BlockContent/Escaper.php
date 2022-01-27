<?php
namespace Sanity\BlockContent;

/**
 * Code kindly borrowed from Twig (http://twig.sensiolabs.org/)
 * Which is licensed under the BSD-3-Clause license.
 * Copyright (c) 2009-2017 by the Twig Team.
 */

class Escaper
{
    public static function escape($string, $charset = 'utf-8')
    {
        // see http://php.net/htmlspecialchars

        // Using a static variable to avoid initializing the array
        // each time the function is called. Moving the declaration on the
        // top of the function slow downs other escaping strategies.
        static $htmlspecialcharsCharsets;

        if (null === $htmlspecialcharsCharsets) {
            if (defined('HHVM_VERSION')) {
                $htmlspecialcharsCharsets = ['utf-8' => true, 'UTF-8' => true];
            } else {
                $htmlspecialcharsCharsets = [
                    'ISO-8859-1' => true, 'ISO8859-1' => true,
                    'ISO-8859-15' => true, 'ISO8859-15' => true,
                    'utf-8' => true, 'UTF-8' => true,
                    'CP866' => true, 'IBM866' => true, '866' => true,
                    'CP1251' => true, 'WINDOWS-1251' => true, 'WIN-1251' => true,
                    '1251' => true,
                    'CP1252' => true, 'WINDOWS-1252' => true, '1252' => true,
                    'KOI8-R' => true, 'KOI8-RU' => true, 'KOI8R' => true,
                    'BIG5' => true, '950' => true,
                    'GB2312' => true, '936' => true,
                    'BIG5-HKSCS' => true,
                    'SHIFT_JIS' => true, 'SJIS' => true, '932' => true,
                    'EUC-JP' => true, 'EUCJP' => true,
                    'ISO8859-5' => true, 'ISO-8859-5' => true, 'MACROMAN' => true,
                ];
            }
        }

        if (isset($htmlspecialcharsCharsets[$charset])) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
        }

        if (isset($htmlspecialcharsCharsets[strtoupper($charset)])) {
            // cache the lowercase variant for future iterations
            $htmlspecialcharsCharsets[$charset] = true;

            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
        }

        $string = iconv($charset, 'UTF-8', $string);
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return iconv('UTF-8', $charset, $string);
    }
}

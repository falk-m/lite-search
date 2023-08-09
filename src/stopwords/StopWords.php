<?php

namespace falkm\search\stopwords;

use Exception;

class StopWords
{
    public static function getByLanguage($language)
    {
        $file = __DIR__ . "/{$language}.php";
        if (!file_exists($file)) {
            throw new Exception("language not supported");
        }

        return require($file);
    }
}

<?php

namespace falkm\search\filter;

class HtmlFilter implements Filter
{
    public function filter($word)
    {
        return strip_tags(str_replace('<', ' <', $word));
    }
}

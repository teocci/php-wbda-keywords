<?php

class UrlHelper
{
    public static function buildUrlWithParams($baseUrl, $params)
    {
        $url = $baseUrl;

        if (!empty($params)) {
            $url .= '?';

            $queryStringParts = [];
            foreach ($params as $key => $value) {
                $queryStringParts[] = urlencode($key) . '=' . urlencode($value);
            }

            $url .= implode('&', $queryStringParts);
        }

        return $url;
    }
}

<?php

class CURLHelper
{
    public static function addCurlHandle($mh, &$handles, $url, $page): void
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $handles[$page] = ['ch' => $ch, 'page' => $page];

        curl_multi_add_handle($mh, $ch);
    }

    public static function executeMultiHandle($mh, &$handles, &$responses): void
    {
        $active = null;

        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) == -1) usleep(1);

            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        foreach ($handles as $data) {
            $ch = $data['ch'];
            $page = $data['page'];

            $chResponse = curl_multi_getcontent($ch);
            $responses[$page] = json_decode($chResponse, true);

            curl_multi_remove_handle($mh, $ch);
        }

        // Clear handles array after processing
        $handles = [];
    }
}
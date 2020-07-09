<?php
namespace Dna\Payment\Model;

class HTTPRequester
{
    /**
     * @description Make HTTP-GET call
     * @param       $url
     * @param array $headers
     * @param array $options
     * @return      HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPGet($url, array $headers, array $options)
    {
        $request = \Requests::get($url, $headers, $options);

        return [
            "status" => $request->status_code,
            "response" => json_decode($request->body, true)
        ];
    }

    /**
     * @description Make HTTP-POST call
     * @param       $url
     * @param array $headers
     * @param array $options
     * @return array HTTP-Response body or an empty string if the request fails or is empty
     */
    public static function HTTPPost($url, array $headers, array $options)
    {
        $request = \Requests::post($url, $headers, $options);

        return [
            "status" => $request->status_code,
            "response" => json_decode($request->body, true)
        ];
    }

    /**
     * @description Make HTTP-PUT call
     * @param       $url
     * @param array $headers
     * @param array $options
     * @return array HTTP-Response body or an empty string if the request fails or is empty
     * @throws \Exception
     */
    public static function HTTPPut($url, array $headers, array $options)
    {
        $request = \Requests::put($url, $headers, $options);

        return [
            "status" => $request->status_code,
            "response" => json_decode($request->body, true)
        ];
    }

    /**
     * @param    $url
     * @param array $headers
     * @param array $options
     * @return array HTTP-Response body or an empty string if the request fails or is empty
     * @category Make HTTP-DELETE call
     */
    public static function HTTPDelete($url, array $headers, array $options)
    {
        $request = \Requests::delete($url, $headers, $options);

        return [
            "status" => $request->status_code,
            "response" => json_decode($request->body, true)
        ];
    }
}

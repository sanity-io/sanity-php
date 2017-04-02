<?php
namespace Sanity;

class ParameterSerializer
{
    public static function serialize($params = [])
    {
        $serialized = [];
        foreach ($params as $key => $value) {
            $serialized['$' . $key] = json_encode($value);
        }
        return $serialized;
    }
}

<?php

if (!function_exists('input')) {
    function input(?string $key = null, $default = null)
    {
        try {
            $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

            $jsonData = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonData = [];
            }

            $allData = array_merge(
                $request->query->all(),
                $request->request->all(),
                $jsonData
            );

            if ($key === null) {
                return $allData;
            }

            return $allData[$key] ?? $default;
        } catch (\Exception $e) {
            return $key === null ? [] : $default;
        }
    }
}

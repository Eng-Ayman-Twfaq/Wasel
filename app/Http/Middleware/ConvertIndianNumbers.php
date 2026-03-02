<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConvertIndianNumbers
{
    public function handle(Request $request, Closure $next)
    {
        $data = $this->convertNumbers($request->all());

        $request->merge($data);

        return $next($request);
    }

    private function convertNumbers($data)
    {
        $indian  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $arabic = ['0','1','2','3','4','5','6','7','8','9'];

        foreach ($data as $key => $value) {

            if (is_array($value)) {
                $data[$key] = $this->convertNumbers($value);
            }

            if (is_string($value) && preg_match('/[٠-٩]/u', $value)) {
                $data[$key] = str_replace($indian, $arabic, $value);
            }
        }

        return $data;
    }
}
<?php

namespace App\Http\Middleware;

use Closure;

class ForceUtf8
{
    public function handle($request, Closure $next)
    {
        $data = $request->all();

        array_walk_recursive($data, function (&$v) {
            if (is_string($v)) {
                $enc = mb_detect_encoding($v, ['UTF-8','SJIS-win','CP932','EUC-JP','ISO-2022-JP','JIS','ASCII'], true) ?: 'UTF-8';
                if ($enc !== 'UTF-8') {
                    $v = mb_convert_encoding($v, 'UTF-8', $enc);
                }
                // 不正なバイト列を除去
                $v = iconv('UTF-8', 'UTF-8//IGNORE', $v);
            }
        });

        $request->merge($data);
        return $next($request);
    }
}

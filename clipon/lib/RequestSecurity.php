<?php

class RequestSecurity {
    public static function isSecure(?Request $request = null): bool {
        $request = $request ?? new Request();

        $https = strtolower((string)$request->server('HTTPS', ''));
        $forwardedProto = strtolower((string)$request->server('HTTP_X_FORWARDED_PROTO', ''));
        $forwardedSsl = strtolower((string)$request->server('HTTP_X_FORWARDED_SSL', ''));
        $serverPort = (string)$request->server('SERVER_PORT', '');

        return ($https !== '' && $https !== 'off')
            || $serverPort === '443'
            || $forwardedProto === 'https'
            || $forwardedSsl === 'on';
    }
}

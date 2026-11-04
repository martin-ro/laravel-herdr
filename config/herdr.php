<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Socket path
    |--------------------------------------------------------------------------
    |
    | An explicit path to the Herdr control socket. When set, this takes
    | precedence over the session name and the default location. Mirrors the
    | HERDR_SOCKET_PATH environment variable that Herdr itself respects.
    |
    | On Windows this may be a named pipe, e.g. \\.\pipe\herdr.
    |
    */

    'socket_path' => env('HERDR_SOCKET_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    |
    | The named Herdr session to talk to. Resolves to
    | "<base_path>/sessions/<session>/herdr.sock". Ignored when an explicit
    | socket_path is configured.
    |
    */

    'session' => env('HERDR_SESSION'),

    /*
    |--------------------------------------------------------------------------
    | Config base path
    |--------------------------------------------------------------------------
    |
    | Directory that holds Herdr's runtime sockets. Defaults to
    | "$XDG_CONFIG_HOME/herdr" (or "~/.config/herdr") when left null.
    |
    */

    'base_path' => env('HERDR_CONFIG_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    |
    | "connect_timeout" bounds the initial socket connection. "read_timeout"
    | bounds how long a single read may block; null means block indefinitely,
    | which is what you usually want when waiting on long-running commands or
    | streaming events.
    |
    */

    'connect_timeout' => (float) env('HERDR_CONNECT_TIMEOUT', 5.0),

    'read_timeout' => env('HERDR_READ_TIMEOUT') !== null
        ? (float) env('HERDR_READ_TIMEOUT')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Request id prefix
    |--------------------------------------------------------------------------
    |
    | Each request gets a unique id of the form "<prefix>_<n>". Only useful for
    | debugging when inspecting raw socket traffic.
    |
    */

    'id_prefix' => env('HERDR_ID_PREFIX', 'req'),

    /*
    |--------------------------------------------------------------------------
    | herdr-core socket
    |--------------------------------------------------------------------------
    |
    | herdr-core (`hc`) runs the terminals of the Herdr desktop app; use it
    | through Herdr::core(). Left null, the socket resolves like core does:
    | "$XDG_RUNTIME_DIR/herdr-core/<session>.sock", then
    | "/run/user/<uid>/herdr-core/<session>.sock", then
    | "~/.local/state/herdr-core/<session>/<session>.sock". HC_SOCKET and
    | HC_SESSION are the variables core itself respects.
    |
    */

    'core_socket_path' => env('HC_SOCKET'),

    'core_session' => env('HC_SESSION', 'default'),

];

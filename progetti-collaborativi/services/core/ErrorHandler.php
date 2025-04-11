<?php

class ErrorHandler
{
    public function register()
    {
        # Abilita la segnalazione di tutti gli errori, inclusi E_NOTICE
        error_reporting(E_ALL);

        # (Opzionale) Attiva(1)/Disattiva(0) la visualizzazione a schermo
        ini_set('display_errors', '0');
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->log('PHP Error', $errstr, $errfile, $errline, $trace);
        return true;
    }

    public function handleException($exception)
    {
        $this->log(
            'Uncaught Exception',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace()
        );
    }

    public function handleShutdown()
    {
        $error = error_get_last();
        if (
            $error && in_array($error['type'], 
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]
        )) {
            $this->log(
                'Fatal Error', $error['message'], 
                $error['file'], $error['line'], []
            );
        }
    }

    private function log($type, $message, $file, $line, $trace)
    {
        $log = [
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => array_map(function ($t) {
                return isset($t['file']) 
                    ? "{$t['file']}:{$t['line']}" 
                    : '[internal function]';
            }, $trace)
        ];

        Risposta::set('messaggio', "DEBUG: " . $log['message']);
        Risposta::set('dati', $log);
        Risposta::jsonDaInviare();
    }
}

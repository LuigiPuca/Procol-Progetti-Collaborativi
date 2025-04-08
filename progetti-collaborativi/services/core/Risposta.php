<?php
/**
 * In questo file vi è la classe risposta, utile a 
 */
class Risposta {
    private static array $daInviare = [
        'isUtenteConnesso' => false,
        'isSessioneScaduta' => false,
        'isSessioneRecente' => false,
        'messaggio' => '',
        'dati' => []
    ];

    public static function set(string $chiave, $valore): void {
        self::$daInviare[$chiave] = $valore;
    }

    public static function get(string $chiave) {
        return self::$daInviare[$chiave] ?? null;
    }

    public static function all(): array {
        return self::$daInviare;
    }

    public static function unisciCon(array $extra): void {
        self::$daInviare = array_merge(self::$daInviare, $extra);
    }

    public static function jsonDaInviare(int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(self::$daInviare);
        exit;
    }

    public static function redirectPage(string $tipo = "err", string $add) {
        # add fa da stringa di supporto
        match($tipo) {
            'errLogin' => (function() use ($add){
                $a = base64_encode( '' . $add . 
                    '<br><br><a href="./login.html">Torna indietro</a>');
                header("Location: ../../errore.html?msg=" . urlencode($a));
            })(),
            'okLogin' => (function() use ($add){
                if ($add === 'admin') {
                    header('Location: ../../dashboard.html');
                } else {
                    header("Location: ../../home.html");
                }              
            })()
        };
        exit;
    }
}

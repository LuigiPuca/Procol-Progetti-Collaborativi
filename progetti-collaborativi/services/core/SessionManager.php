<?php
/**
 * La classe SessionManager contiente tutte le funzioni valide alla gestione 
 * della sessione dell'utente, e delle sue informazioni. 
 */


class SessionManager {
    public static function isSessioneValida(): bool {
        # verifica se la sessione esiste e non scaduta
        return isset($_SESSION['uuid_utente'])
            && isset($_SESSION['timeout']) 
            && time() < $_SESSION['timeout'];
    }

    public static function isSessioneRecente(): bool {
        # verifica se la connessione è stata stabilita da massimo 5 secondi
        return isset($_SESSION['timein']) 
            && (time() - $_SESSION['timein']) < 5;
    }

    public static function isSessioneScaduta(): bool {
        # verifica se la connessione era presente ma scaduta
        return isset($_SESSION['uuid_utente'])
            && isset($_SESSION['timeout'])
            && time() > $_SESSION['timeout'];
    }

    # verifica se la sessione è valida, e se definito anche se recente
    public static function verificaSessione(bool $hasRecente = false): array {
        # se la sessione é scaduta
        if (self::isSessioneScaduta()) {
            self::distruggiSessione();
            Risposta::set('isSessioneScaduta', true);
            return [];
        }

        #se la sessione non è valida
    
        if (!self::isSessioneValida()) {
            return [];
        }

        $risposta = [
            'uuid_utente' => $_SESSION['uuid_utente'],
            'chi' => $_SESSION['chi'] ?? null
        ];

        Risposta::set('isUtenteConnesso', self::isSessioneValida());
        if ($hasRecente) {
            Risposta::set('isSessioneRecente', self::isSessioneRecente());
        }
    
        return $risposta;
    }
    

    public static function distruggiSessione(): void {
        # da chiamare nel momento voglio distruggere la sessione
        session_unset();
        session_destroy();
    }

    # da chiamare quando si vuol creare una nuova sessione
    public static function impostaSessione(
        array $utente, 
        int $durata_sessione, 
        int $durata_cookie
    ): void {
        $_SESSION['uuid_utente'] = $utente['uuid'];
        $_SESSION['chi'] = [
            'nome' => $utente['nome'],
            'cognome' => $utente['cognome'],
            'suffisso' => $utente['genere'] === 'maschio' ? 'o' : 'a'
        ];
        # Salva durata sessione e cookie di sessione
        $_SESSION['durata_sessione'] = $durata_sessione;
        $_SESSION['durata_cookie'] = $durata_cookie;
        
        /**
         * Poichè questo non basta per cambiare davvero le durate di cookie e
         * sessione, bisogna fare un backup della sessione, distruggerla, 
         * ricrearla e salvare quando la sessione deve finire (timeout) e 
         * quando è iniziata (timein). Per prove più veloci si può sostituire 
         * $durata_sessione con una quantita di secondi piccola come 20.
         */
        $backup_sessione = $_SESSION;
        session_destroy(); #distruzione della sessione
        ini_set('session.gc_maxlifetime', $durata_sessione); //imposta sessione
        session_set_cookie_params($durata_cookie, '/'); //imposta cookie
        session_start(); //riavvio della sessione
        $_SESSION = $backup_sessione; //riprende stato precedente sessione
        $_SESSION['timeout'] = time() + $durata_sessione; 
        $_SESSION['timein'] = time(); //utile per check su sessione recente
    }

    public static function getUUIDUtente() {
        return $_SESSION['uuid_utente'] ?? null;
    }

    public static function getChi() {
        return $_SESSION['chi'] ?? null;
    }
}

?>
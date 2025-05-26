<?php
function checkDatiInviati(...$args) {
        $error = 
            "Errore: Si &egrave; verificato un problema nell'invio " . 
            "dei dati necessari per il completamento dall'azione!";
        foreach ($args as $arg) {
            if (!$arg) {
                throw new Exception($error);
            }
        };
}


# utilizza splat operator per verificare campi multipli
function checkEmpty(...$args) : bool {
    foreach($args as $arg) {
        if(empty($arg)) return true;
    }
    return false;
}

function checkName(...$names): bool {
    $pattern = "/
        ^                       # Inizio della stringa
        [a-zA-ZÀ-ÿ]             # 1° carattere: una lettera (anche accentata)
        (?:                     # Inizio gruppo non catturante
            [a-zA-ZÀ-ÿ’\s]{0,48}# [0,48] lettere, apostrofi tipografici o spazi
            [a-zA-ZÀ-ÿ]         # n° carattere: una lettera (anche accentata)
        )?                      # Il gruppo è opzionale
        $                       # Fine della stringa
        /xu"; //x = spazi; u = UTF-8;
    foreach($names as $name) {
        if(!is_string($name)) {
            throw new InvalidArgumentException;
        }
        if(!preg_match($pattern, $name)) return false;
    }
    return true;
}

function checkEmail(...$emails): bool {
    $pattern = "/
        ^[a-zA-Z0-9]+           # Inizio stringa
        ([._-][a-zA-Z0-9]+)*    # Eventuali segmenti aggiuntivi
        @                       # Separatore dominio
        [a-zA-Z0-9]+            # Primi caratteri del dominio
        ([._-][a-zA-Z0-9]+)*    # Eventuali segmenti aggiuntivi
        \.                      # Punto separatore tra dominio e estensione
        [a-zA-Z]{2,4}           # Estensione dominio, tra 2 e 4 lettere
        $
    /x";
    foreach($emails as $email) {
        if(!is_string($email)) {
            Risposta::set("inc", $email);
            throw new InvalidArgumentException;
        }
        if(!preg_match($pattern, $email)) return false;
    }
    return true;
   
}

function checkPasswordLen(string $pwd) : bool {
    return (mb_strlen($pwd, 'UTF-8') > 7);
}

function checkPassword(...$psws): bool {
    $pattern = '/
        ^                                   # Inizio della stringa
        (?=.*[a-z])                         # Almeno una lettera minuscola
        (?=.*[A-Z])                         # Almeno una lettera maiuscola
        (?=.*\d)                            # Almeno una cifra
        (?=.*[^\da-zA-Z])                   # Almeno un carattere speciale (non alfanumerico)
        .{8,20}                             # Lunghezza totale: da 8 a 20 caratteri
        $                                   # Fine della stringa
    /x';
    foreach($psws as $psw) {
        if(!is_string($psw)) {
            throw new InvalidArgumentException;
        }
        if(!preg_match($pattern, $psw)) return false;
    }
    return true;
}

function checkTitles(
    int $min_char = 1, int $max_char = 20, bool $use_default_symbols = false,
    string $extra_symbols = "", ...$titoli
): bool {
    if ($min_char > $max_char || $min_char < 1) {
        throw new InvalidArgumentException;
    }
    $diff = $max_char - $min_char;

    $default_symbols = 
        '\$£€¥&@#' .
        'ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñ' .
        'ÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿ' .
        '\’\\\\\.,:;!?\%\-\''; // barra rovesciata, punto, virgola, ecc.

    # sceglie quali simboli includere
    $all_symbols = $use_default_symbols 
        ? $default_symbols . $extra_symbols 
        : $extra_symbols;

    # per sicurezza, escapa ciò che non é stato escapato
    $escaped = preg_quote($all_symbols, '/');
    $pattern = "/
        ^                                 # Inizio della stringa
        [a-zA-Z0-9]{1}                    # 1° char alfanumerico (no accenti)
        [a-zA-Z0-9\s$escaped]{0,$diff}    # Char alfanumerici o spazi opzionali
        $                                 # Fine della stringa
    /xu";                                 // con supporto unicode 
    foreach ($titoli as $titolo) {
        if (!is_string($titolo)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $titolo)) return false;
    }
    return true;
}

function checkHexColor(...$colori) {
    $pattern = "/                         
        ^                                 # Inizio della stringa
        \#                                # Cancelletto
        [0-9a-fA-F]{8}                    # 8 caratteri hex
        $                                 # Fine della stringa
    /x";
    foreach ($colori as $colore) {
        if (!is_string($colore)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $colore)) { return false; }
    }
    return true;
}

function checkUUIDs(...$uuids) {
    $pattern = "/                         
        ^                                 # Inizio della stringa
        [0-9a-fA-F]{32}                   # 32 caratteri hex
        $                                 # Fine della stringa
    /x";
    foreach ($uuids as $uuid) {
        if (!is_string($uuid)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $uuid)) { return false; }
    }
    return true;
}

function checkDateTime(...$date) {
    # decide se voglio il pattern flessibile o meno
    $flexibile = false; 

    # controlla l'ultima variabile se é un booleano
    if (is_bool(end($date))) {
        $flexibile = array_pop($date); // estrae l'ultimo elemento
    }

    # pattern rigido: YYYY-MM-DD HH:MM:SS
    $pattern = "/
        ^                                       # Inizio della stringa
        \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}     # YYYY-MM-DD HH:MM:SS
        $                                       $ Fine della stringa
    /x";

    # Pattern flessibile: accetta anche formati parziali
    $flexiblePattern = "/
        ^\d{4}-\d{2}-\d{2}?                     # Data obbligatoria
        (?:\s\d{2})?                            # Ora opzionale
        (?::\d{2})?                             # Minuti opzionali
        (?::\d{2})?                             # Secondi opzionali
        $                                       # Fine della stringa
    /x";

    $pattern = !$flexibile ? $pattern : $flexiblePattern; 

    foreach ($date as $data) {
        if (!is_string($data)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $data)) { return false; }
    }
    return true;
}

function checkSiglaTeam(...$sigle) {
    $pattern = "/^[A-Z0-9]{1,3}$/";
    foreach ($sigle as $sigla) {
        if (!is_string($sigla)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $sigla)) { return false; }
    }
    return true;
}

function checkElNames(int $min_char = 1, int $max_char = 20, ...$nomi) {
    $min = ($min_char > 0) ? ($min_char - 1) : 0; 
    $max = ($max_char > $min_char) ? ($max_char - 2) : $min_char + 1 ;
    $range= "{" . $min . "," . $max . "}";
    $pattern = "/
    ^
        [a-zA-ZÀ-ÿ]                   # primo carattere anche accentato
        (?:                           # inizio gruppo opzionale
            [a-zA-ZÀ-ÿ\'\s]$range     # fino a 18 caratteri, spazi o apostrofi
            [a-zA-ZÀ-ÿ]               # ultimo carattere anche accentato
        )?                            # fine gruppo opzionale
    $                                 # fine della stringa
    /x";
    foreach ($nomi as $nome) {
        if (!is_string($nome)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $nome)) { return false; }
    }
    return true;
}

function checkCharRange(int $min_char = 0, int $max_char = 255, ...$strings) {
    $range= "{" . $min_char . "," . $max_char . "}";
    $pattern = "/^.$range$/";
    foreach ($strings as $string) {
        if (!is_string($string)) {
            throw new InvalidArgumentException;
        }
        if (!preg_match($pattern, $string)) { return false; }
    }
    return true;
}
<?php

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
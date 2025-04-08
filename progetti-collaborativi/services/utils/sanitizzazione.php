<?php

function checkEmail(string $email): string {
    $email = trim($email);
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
    return preg_match($pattern, $email) ? $email : "";
}

function checkPassword(string $pwd) : string {
    return (mb_strlen($pwd, 'UTF-8') > 7) ? $pwd : "";
}
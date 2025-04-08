<?php

require_once __DIR__ . '/../avvio.php';

# Si inviano direttamente i dati dopo le operazioni preliminari
Risposta::unisciCon($altri_dati);
Risposta::jsonDaInviare();

?>
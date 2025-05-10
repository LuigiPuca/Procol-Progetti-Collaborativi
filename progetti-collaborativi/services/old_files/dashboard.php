<?php 
require_once('db_connection.php');
session_start();

# Configuriamo MySQLi per segnalare automaticamente gli errori come eccezioni PHP
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


try {
    if (isset($_SESSION['uuid_utente'])) {
        #salvo in una variabile l'uuid_utente 
        $uuid_utente = $_SESSION['uuid_utente'];
        # Verifico che l'utente collegato sia un admin
        $query = "SELECT COUNT(*) as is_admin FROM utenti WHERE `uuid` = UNHEX(?) and ruolo = 'admin'";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $uuid_utente);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($is_admin);
        $stmt->fetch();
        $stmt->close();
        if (!$is_admin) {
            //header('Location: ../portal.html');
            $sm = "Accesso negato: Stai per essere reindirizzato";
        } else {
            $sm = "Accesso consentito";
            # vogliamo leggere (SELECT) il numero totale di utenti iscritti
            $query = "SELECT COUNT(*) AS numero_utenti FROM utenti";
            $stmt = $mysqli->query($query);
            # estrapoliamo il risultato e lo convertiamo in un array associativo
            $risultato = $stmt->fetch_assoc();
            $utentiIscritti = $risultato['numero_utenti'];
            # salvo il risultato nell'array
            $info[0] = $utentiIscritti;

            # vogliamo ora leggere il numero di utenti che hanno come ultimo accesso (WHERE) nelle ultime 24 ore
            # prima voglio calcolare a quale corrisponde la data di 24 ore fa, sottraendo 24 alla data corrente NOW() 
            $query = "SELECT COUNT(*) AS numero_utenti FROM utenti WHERE ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $stmt = $mysqli->query($query);
            $risultato = $stmt->fetch_assoc();
            $ultimamenteConnessi = $risultato['numero_utenti'];
            $info[1] = $ultimamenteConnessi; //salvo nell'array 

            # poi leggiamo il numero di team creati
            $query = "SELECT COUNT(*) AS numero_team FROM team";
            $stmt = $mysqli->query($query);
            $risultato = $stmt->fetch_assoc();
            $numeroTeam = $risultato['numero_team'];
            $info[2] = $numeroTeam; //salvo nell'array

            # per leggere invece il numero totale di commenti fatti 
            $query = "SELECT COUNT(*) AS numero_commenti FROM commenti";
            $stmt = $mysqli->query($query);
            $risultato = $stmt->fetch_assoc();
            $numeroCommenti = $risultato['numero_commenti'];
            $info[3] = $numeroCommenti;

            # creiamo un array per memorizzare i giorni della settimana 
            $giorni_settimana = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];

            # ottenendo il numero del giorno della settimana di oggi... 
            $oggi = date('N');
            # ... possiamo ordinare l'array prima dichiarato dal giorno della settimana più lontano a quello più vicino (oggi)
            $giorni_ordinati = [];
            $indice_giorno_piu_lontano = ($oggi + 1) % 7;
            for ($i = 0; $i < 7; $i++) {
                $giorno = $giorni_settimana[($indice_giorno_piu_lontano + $i) % 7];
                $giorni_ordinati[] = $giorno;
            }
            $info[4] = $giorni_ordinati;

            # ora vogliamo ottenere per ogni giorno dell'ultima settimana il numero di...
            # ... 1) Numero dei Commenti
            $commenti_weekly = [0, 0, 0, 0, 0, 0, 0];
            $query = "SELECT DAYOFWEEK(inviato) AS giorno, COUNT(*) AS conteggio FROM commenti WHERE inviato >= CURDATE() - INTERVAL 7 DAY GROUP BY DAYOFWEEK(inviato) ORDER BY giorno";
            $stmt = $mysqli->query($query);

            # Riempio l'array $commenti_weekly con i valori ottenuti dalla query
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $indice = ($row['giorno'] + 5 - $oggi) % 7;
                    $commenti_weekly[$indice] = $row['conteggio'];
                }
            }
            $info[5] = $commenti_weekly;

            //... 2) Schede Completate
            $schede_completate_weekly = [0, 0, 0, 0, 0, 0, 0];
            // per vedere come é stata creata la vista vedere il file IstruzioniUsate.txt e cercare per la parola chiave vista
            $query = "SELECT DAYOFWEEK(spostamento) AS giorno, COUNT(*) AS conteggio FROM vista_schede_completate WHERE spostamento >= CURDATE() - INTERVAL 7 DAY GROUP BY DAYOFWEEK(spostamento) ORDER BY giorno";
            $stmt = $mysqli->query($query);

            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $indice = ($row['giorno'] + 5 - $oggi) % 7;
                    $schede_completate_weekly[$indice] = $row['conteggio'];
                }
            }
            $info[6] = $schede_completate_weekly;

            //... 3) Schede in Ritardo
            $schede_in_ritardo_weekly = [0, 0, 0, 0, 0, 0, 0];
            // per vedere come é stata creata la vista vedere il file IstruzioniUsate.txt e cercare per la parola chiave vista
            $query = "SELECT DAYOFWEEK(spostamento) AS giorno, COUNT(*) AS conteggio FROM vista_schede_in_ritardo WHERE spostamento >= CURDATE() - INTERVAL 7 DAY GROUP BY DAYOFWEEK(spostamento) ORDER BY giorno";
            $stmt = $mysqli->query($query);

            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $indice = ($row['giorno'] + 5 - $oggi) % 7;
                    $schede_in_ritardo_weekly[$indice] = $row['conteggio'];
                }
            }
            $info[7] = $schede_in_ritardo_weekly;

            # ora vogliamo visualizzare il numero di progetti fino ad ora creati...
            $query = "SELECT COUNT(*) AS numero_progetti FROM progetti";
            $stmt = $mysqli->query($query);
            $risultato = $stmt->fetch_assoc();
            $numeroProgetti = $risultato['numero_progetti'];
            $info[8] = $numeroProgetti; //salvo nell'array

            #...il numero di progetti con team not null...
            $query = "SELECT COUNT(*) AS numero_progetti_con_team FROM progetti WHERE team_responsabile IS NOT NULL";
            $stmt = $mysqli->query($query);
            $risultato = $stmt->fetch_assoc();
            $numeroProgettiConTeam = $risultato['numero_progetti_con_team'];
            $info[9] = $numeroProgettiConTeam; //salvo nell'array

            #... e quali siano tutti i progetti
            $dati = [];
            $query = "SELECT * FROM `vista_progetti_team_utenti` ORDER BY id_progetto ASC";
            $stmt = $mysqli->query($query);
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    # devo sanificare i dati per evitare che campi come quella della descrizione dove possono essere salvati anche caratteri html non mi crei problemi nella pagina html
                    foreach ($row as $key => $value) {
                        $value = strip_tags($value); // rimuovo i tag html 
                        $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // converte caratteri speciali in entità html
                    }
                    $dati[] = $row;
                }
            }
            $info[10] = $dati; //salvo nell'array

            #... ricavo i team assegnabili al progetto e li ordino per numero di progetti
            $dati = [];
            $query = "SELECT CONCAT(sigla, ' | ', nome, ' [', numero_progetti, ']') AS team_da_assegnare FROM team ORDER BY numero_progetti; ";
            $stmt = $mysqli->query($query);
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $dati[] = $row;
                }
            }
            $info[11] = $dati; //salvo nell'array

            #... per visualizzare tutti i team fino ad ora creati 
            $dati = [];
            $query = "SELECT * FROM `vista_team_utenti` ORDER BY sigla_team ASC";
            $stmt = $mysqli->query($query);
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $dati[] = $row;
                }
            }
            $info[12] = $dati; //salvo nell'array

            #... per visualizzare tutti gli utenti assegnabili ad un team
            if($_SERVER["REQUEST_METHOD"] == "POST" && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
                $jsonData = file_get_contents('php://input');
                $_POST = json_decode($jsonData, true);
                $dati = [];
                $alreadyIn = ($_POST['recupera_team'] === "N/A") ? "" : $_POST['recupera_team'];
                $stmt = $mysqli->prepare("SELECT CONCAT(cognome, ' ', nome) AS anagrafica, email, ruolo FROM utenti WHERE (team IS NULL OR team = ?) ORDER BY cognome ASC, nome ASC, email ASC;");
                $stmt->bind_param("s", $alreadyIn);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($anagrafica, $email, $ruolo);
                while($stmt->fetch()) {
                    $ruolo = ($ruolo === "admin") ? "{A}" : (($ruolo === "capo_team") ? "{C}" : "{U}");
                    $anagrafica = "$ruolo $anagrafica";
                    $row = ['anagrafica' => $anagrafica, 'email' => $email];
                    $dati[] = $row;  //salvo nell'array
                }
                $info[13] = $dati;
            }

            #... Possiamo anche vedere alcune categorie di attività. La prima è quella della attività completate
            $dati = [];
            $query = "SELECT HEX(uuid_scheda) AS uuid_scheda, id_progetto, titolo, stato, spostamento FROM `vista_schede_completate` ORDER BY spostamento ASC";
            $stmt = $mysqli->query($query);
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $dati[] = $row;
                }
            }
            $info[14] = $dati; //salvo nell'array
            
            #... La seconda è invece quella delle attività in ritardo
            $dati = [];
            $query = "SELECT HEX(uuid_scheda) AS uuid_scheda, id_progetto, titolo, stato, spostamento FROM `vista_schede_in_ritardo` ORDER BY spostamento ASC";
            $stmt = $mysqli->query($query);
            if ($stmt->num_rows > 0) {
                while ($row = $stmt->fetch_assoc()) {
                    $dati[] = $row;
                }
            }
            $info[15] = $dati; //salvo nell'array


        }

        
    } else {
        $sm = "Accesso negato: Stai per essere reindirizzato";
    }

} catch (Exception $e) {
    // Gestisco l'eccezione, registrando l'errore in una variabile per messaggi di sistema.
    $sm = "Errore: " . $e->getMessage();
}
$is_admin = isset($is_admin) ? $is_admin : 0;
$info = isset($info) ? $info : [0, 0, 0, 0, ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'], array_fill(0,7,0), array_fill(0,7,0), array_fill(0,7,0)];
echo json_encode(['messaggio' => $sm, 'isAdmin' => $is_admin, 'info' => $info]);
exit();
?>



<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function elaboraTeam($mysqli, $email, $chi, $team, $ruolo){
    try {
        $dati = [];
        $msg = '';
        $suffisso = $chi['suffisso'];
        # Operazioni specifiche per la sezione "home"
        $msg = "Benvenut$suffisso nella home page!";
        #vediamo i progetti a cui partecipa il team...
        $query = "SELECT p.id_progetto AS id, p.progetto AS nome_progetto FROM progetti p JOIN utenti u ON p.team_responsabile = u.team WHERE u.email = '$email'";
        $stmt = $mysqli->query($query);
        if ($stmt->num_rows > 0) {
            while ($row = $stmt->fetch_assoc()) {
                # devo sanificare i dati per evitare che campi come quella della descrizione dove possono essere salvati anche caratteri html non mi crei problemi nella pagina html
                foreach ($row as $key => $value) {
                    $value = strip_tags($value); // rimuovo i tag html 
                    $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // converte caratteri speciali in entità html
                }
                $dati['progetti'][] = $row;
            }
        }
        #... la sigla e il nome del team...
        $dati['sigla'] = '';
        $dati['team'] = '';
        $query = "SELECT t.sigla AS sigla, t.nome AS team FROM team t JOIN utenti u ON t.sigla = u.team WHERE u.email = '$email'";
        $stmt = $mysqli->query($query);
        if ($stmt && $risultato = $stmt->fetch_assoc()) {
            $dati['sigla'] = $risultato['sigla'];
            $dati['team'] = $risultato['team'];
        }
        $sigla = $dati['sigla'];

        #...poi gli utenti appartenenti al team...
        $query = "SELECT u.cognome AS cognome, u.nome AS nome, u.genere AS genere, u.email AS email, CASE WHEN u.email = t.responsabile THEN TRUE ELSE FALSE END AS isLeader FROM utenti u INNER JOIN team t ON u.team = t.sigla WHERE u.team = '$sigla' ORDER BY isLeader ASC, u.cognome, u.nome;";
        $stmt = $mysqli->query($query);
        $numero_membri = 0;
        if ($stmt->num_rows > 0) {
            while ($row = $stmt->fetch_assoc()) {
                # devo sanificare i dati per evitare che campi come quella della descrizione dove possono essere salvati anche caratteri html non mi crei problemi nella pagina html
                foreach ($row as $key => $value) {
                    $value = strip_tags($value); // rimuovo i tag html 
                    $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // converte caratteri speciali in entità html
                }
                
                $dati['membri'][] = $row;
            }
        }
        $stmt->close();

        #... ed infine un resoconto sulle attività che riguardano le schede create dall'utente o ad esso assegnate (o di tutti i membri se si é capoteam) nelle varie board del team
        $query_obtain = "SELECT DISTINCT HEX(r.uuid_report), r.`timestamp`, r.attore, r.descrizione, r.link, r.utente, r.team, r.categoria, s.titolo, r.attore_era, r.bersaglio_era, c.colore_hex, i.incaricato, p.progetto FROM report r LEFT JOIN progetti p ON p.id_progetto = r.progetto LEFT JOIN stati c ON r.progetto = c.id_progetto AND c.stato = r.categoria LEFT JOIN schede s ON s.uuid_scheda = r.scheda LEFT JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda WHERE r.team = ?";
        if ($ruolo === "utente") $query_obtain .= " AND (r.utente = ? OR i.incaricato = ?) "; // ci stiamo riferendo all'utente che subisce l'azione che in questo caso dobbiamo essere noi
        $query_obtain .= " AND r.descrizione IN ('Creazione Scheda', 'Archiviazione Scheda', 'Eliminazione Scheda', 'Cambiamento Stato', 'Aggiunta Descrizione Scheda', 'Modifica Descrizione Scheda', 'Creazione Commento', 'Modifica Commento', 'Risposta Commento', 'Eliminazione Commento', 'Revocazione Scheda', 'Assegnazione Scheda', 'Riassegnazione Scheda', 'Creazione Categoria', 'Eliminazione Categoria', 'Oscuramento Categoria', 'Visualizzazione Categoria')";
        $query_obtain .= " ORDER BY r.timestamp DESC LIMIT 50";
        $stmt_obtain = $mysqli->prepare($query_obtain);
        ($ruolo === "utente") ? $stmt_obtain->bind_param("sss", $team, $email, $email) : $stmt_obtain->bind_param("s", $team);
        $stmt_obtain->execute();
        $stmt_obtain->store_result();
        $stmt_obtain->bind_result($uuid_report, $timestamp, $attore, $descrizione, $link, $bersaglio, $team_responsabile, $stato, $titolo_scheda, $attore_era, $bersaglio_era, $colore_hex, $incaricato, $progetto);
        while ($stmt_obtain->fetch()) {
            $isBersaglioMe = ($bersaglio === $email) ? 1 : 0;
            $isAttoreMe = ($attore === $email) ? 1 : 0;
            $isIncaricatoMe = ($incaricato === $email) ? 1 : 0;
            $dati['reports'][] = [
                "uuid_report" => strtolower($uuid_report),
                "timestamp" => $timestamp,
                "attore" => $attore,
                "descrizione" => $descrizione,
                "link" => $link,
                "bersaglio" => $bersaglio,
                "team_responsabile" => $team_responsabile,
                "stato" => $stato,
                "titolo_scheda" => $titolo_scheda,
                "attore_era" => $attore_era,
                "bersaglio_era" => $bersaglio_era,
                "colore_hex" => $colore_hex,
                "incaricato" => $incaricato,
                "isBersaglioMe" => $isBersaglioMe,
                "isAttoreMe" => $isAttoreMe,
                "isIncaricatoMe" => $isIncaricatoMe,
                "progetto" => $progetto,
            ];
        }
        $stmt_obtain->close();
    } catch (Exception $e) {
        $msg = "Errore: " . $e->getMessage();
    } finally {
        return [
            'msg' => $msg,
            'dati' => $dati
        ];  
    }
}
?>
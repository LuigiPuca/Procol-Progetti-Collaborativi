<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function elaboraBoard($mysqli, $email, $chi, $progetto, $ruolo, $team){
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
        #vogliamo titolo, descrizione e scadenza del progetto
        $query = ($ruolo === "admin") ? "SELECT DISTINCT progetto AS nome_progetto, descrizione AS descrizione_progetto, scadenza AS scadenza_progetto FROM progetti WHERE id_progetto = '$progetto'" : "SELECT DISTINCT p.progetto AS nome_progetto, p.descrizione AS descrizione_progetto, scadenza AS scadenza_progetto FROM progetti p JOIN utenti u ON p.team_responsabile = u.team WHERE u.email = '$email' AND p.id_progetto = '$progetto'";
        $stmt = $mysqli->query($query);
        if ($stmt->num_rows === 1) {
            $row = $stmt->fetch_assoc();
            $dati['progetto']['nome_progetto'] = $row['nome_progetto'];
            $dati['progetto']['descrizione_progetto'] = $row['descrizione_progetto'];
            $scadenza_progetto = $row['scadenza_progetto'];
            $scadenza_progetto = new DateTime($scadenza_progetto);
            $scadenza_progetto = $scadenza_progetto->format('d/m/Y H:i:s');
            $dati['progetto']['scadenza_progetto'] = $scadenza_progetto;
        } else {
            throw new Exception("Progetto inesistente o inaccessibile");
        }
        $stmt->free();
        #verifichiamo se ho accesso al progetto ottenuto tramite get
        if ($ruolo !== 'admin') {
            $query_accesso = "SELECT COUNT(*) AS isAccessoConsentito FROM utenti u JOIN progetti p ON u.team = p.team_responsabile WHERE u.email = ? AND id_progetto = ?";
            $stmt_accesso = $mysqli->prepare($query_accesso);
            $stmt_accesso->bind_param("si", $email, $progetto);
            $stmt_accesso->execute();
            $stmt_accesso->bind_result($isAccessoConsentito);
            $stmt_accesso->fetch();
            $stmt_accesso->close();
        } else {
            $isAccessoConsentito = 1;
        }
        

        if ($isAccessoConsentito === 1) {
            $dati['id_progetto'] = $progetto;
            $query_stati = "SELECT id_progetto, stato, colore_hex, ordine_stati, visibile FROM stati WHERE id_progetto = ?";
            if ($ruolo !== 'admin' && $ruolo !== 'capo_team') $query_stati .= " AND visibile = 1";
            $query_stati .= " ORDER BY ordine_stati DESC";
            $stmt_stati = $mysqli->prepare($query_stati);
            $stmt_stati->bind_param("i", $progetto);
            $stmt_stati->execute();
            $stmt_stati->store_result();
            $stmt_stati->bind_result($id_progetto, $stato, $colore_hex, $ordine_stati, $isVisibile);
            $dati['stati'] = [];
            
            while ($stmt_stati->fetch()) {
                $stato_corrente = [
                    'id_progetto' => $id_progetto,
                    'stato' => $stato,
                    'colore_hex' => $colore_hex,
                    'ordine_stati' => $ordine_stati,
                    'isVisibile' => $isVisibile,
                    'schede' => [] //Usiamo questo array per allegare le schede
                ];
              
                # Otteniamo le schede associate a questo stato
                $query_schede = "SELECT HEX(uuid_scheda), titolo, descrizione, ordine_schede FROM schede WHERE id_progetto = ? AND stato = ? ORDER BY ordine_schede DESC";
                $stmt_schede = $mysqli->prepare($query_schede);
                $stmt_schede->bind_param("is", $progetto, $stato);
                $stmt_schede->execute();
                $stmt_schede->store_result();
                $stmt_schede->bind_result($uuid_scheda, $titolo, $descrizione, $ordine_schede);
                while ($stmt_schede->fetch()) {
                    $stato_corrente['schede'][] = ['uuid_scheda' => $uuid_scheda, 'titolo' => $titolo, 'descrizione' => $descrizione, 'ordine_schede' => $ordine_schede];
                }        
                
                $dati['stati'][] = $stato_corrente;
        
                $stmt_schede->close();
            } 
            
            #... ed infine un resoconto sulle attività che riguardano le schede create dall'utente o ad esso assegnate (o di tutti i membri se si é capoteam) nella board
            $query_obtain = "SELECT DISTINCT HEX(r.uuid_report), r.`timestamp`, r.attore, r.descrizione, r.link, r.utente, r.team, r.categoria, s.titolo, r.attore_era, r.bersaglio_era, c.colore_hex, i.incaricato FROM report r LEFT JOIN stati c ON r.progetto = c.id_progetto AND c.stato = r.categoria LEFT JOIN schede s ON s.uuid_scheda = r.scheda LEFT JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda WHERE r.team = ? AND r.progetto = ?";
            if ($ruolo === "utente") $query_obtain .= " AND (r.utente = ? OR i.incaricato = ?) "; // ci stiamo riferendo all'utente che subisce l'azione che in questo caso dobbiamo essere noi
            $query_obtain .= " AND r.descrizione IN ('Creazione Scheda', 'Archiviazione Scheda', 'Eliminazione Scheda', 'Cambiamento Stato', 'Aggiunta Descrizione Scheda', 'Modifica Descrizione Scheda', 'Creazione Commento', 'Modifica Commento', 'Risposta Commento', 'Eliminazione Commento', 'Revocazione Scheda', 'Assegnazione Scheda', 'Riassegnazione Scheda', 'Creazione Categoria', 'Eliminazione Categoria', 'Oscuramento Categoria', 'Visualizzazione Categoria')";
            $query_obtain .= " ORDER BY r.timestamp DESC LIMIT 50";
            $stmt_obtain = $mysqli->prepare($query_obtain);
            ($ruolo === "utente") ? $stmt_obtain->bind_param("siss", $team, $progetto, $email, $email) : $stmt_obtain->bind_param("si", $team, $progetto);
            $stmt_obtain->execute();
            $stmt_obtain->store_result();
            $stmt_obtain->bind_result($uuid_report, $timestamp, $attore, $descrizione, $link, $bersaglio, $team_responsabile, $stato, $titolo_scheda, $attore_era, $bersaglio_era, $colore_hex, $incaricato);
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
                ];
            }
            $stmt_obtain->close();

        } else {
            throw new Exception("Il progetto è inaccessibile o inesistente");
        }
        
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
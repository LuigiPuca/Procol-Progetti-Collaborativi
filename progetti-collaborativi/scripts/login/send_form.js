// Vediamo chi sono i due form per registrazione ed accesso
const formAccesso = document.querySelector('.form-login.--login form');
const formRegistrazione = document.querySelector('.form-login.--registrazione form');
// E i due bottoni che servono per abilitare il submit
const signInValidation = formAccesso.querySelector('.--accedi');
const signUpValidation = formRegistrazione.querySelector('.--registrati');
// e i due bottoni di submit 

// Quando si preme il bottone "Accedi" nel form di accesso
 signInValidation.addEventListener('click', checkValidazione.bind(this,formAccesso)); 
// Quando si prema il bottone "Registrazione" nel form di registrazione
 signUpValidation.addEventListener('click', checkValidazione.bind(this,formRegistrazione));
function checkValidazione(form, event) {
    event.preventDefault();
    const errori = [];
    // Controlliamo se i campi non sono vuoti
    let msg = "Ricorda di rispettare le regole dei seguenti campi:";
    const elementi = form.querySelectorAll('input, .scelta');
    elementi.forEach(elemento => {
        if (elemento.name === "nome" || elemento.name === "cognome") {
            if (!elemento.validity.valid || !/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,48}[a-zA-ZÀ-ÿ])?$/.test(elemento.value)) {
                errori.push(`-Il ${elemento.name} deve essere almeno di 3 caratteri e non può superarne i 50. Inoltre, sono permessi solo lettere, ma anche spazi ed apostrofi eccetto che all'inizio o alla fine.`);
            }
        } else if (elemento.name === "email") {
            if (!elemento.validity.valid || !/[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/.test(elemento.value)) {
                errori.push(`-La email deve rispettare un formato valido.`);
            }
        } else if (elemento.name === "password") {
            if (!elemento.validity.valid || !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,20}$/.test(elemento.value)) {
                errori.push(`-La password deve contenere almeno 8 caratteri, una lettera maiuscola, una minuscola, un numero e un carattere speciale. Non può superarne 32`);
            }
        } else if (elemento.matches('.scelta')) {
            const btnRadioSelezionato = elemento.querySelector("input[type=radio]:checked");
            if (!btnRadioSelezionato || (btnRadioSelezionato.value !== "maschio" && btnRadioSelezionato.value !== "femmina")) {
                errori.push(`-Bisogna selezionare il genere.`);
            }
        } else if (elemento.name === "checkTC") {
            if (!elemento.checked) {
                errori.push(`-Devi accettare termini e condizioni per poterti registrare.`);
            }
        }
    });
    if (errori.length !== 0) {
        Notifica.appari({messaggioNotifica: msg + "<br>"+ errori.join("<br>"), tipoNotifica: 'attenzione-notifica',});
        return;
    } else {
        // Se non ci sono errori, si può inviare il form
        console.log("Invio il form di accesso/registrazione");
        form.submit();
    }
}
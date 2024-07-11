// dettagli sulla curva di bazier e su come creare canvas qui https://www.w3schools.com/Tags/canvas_beziercurveto.asp


var data = [65, 59, 80, 81, 56, 55, 40, 0, 0 , 0, 0, 12];
var labels = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

var canvas = document.getElementById('myChart'); //seleziono quale oggetto voglio come mio canvas
var ctx = canvas.getContext('2d'); // Vogliamo disegnare un canvas 2d

// Definisco dimensioni del grafico
var width = canvas.width;
var height = canvas.height;

var margin = 40; // margine tra contenitore e contenuto
var innerWidth = width - (2 * margin);
var innerHeight = height - (2 * margin);

var maxValue = Math.max(...data);

var numPoints = data.length;
var stepX = innerWidth / (numPoints - 1);
var stepY = innerHeight / maxValue;

ctx.beginPath();
ctx.moveTo(margin, height - margin - (data[0] * stepY));

var gradient = ctx.createLinearGradient(margin, height - margin, margin, 0);
gradient.addColorStop(1, 'rgba(255, 99, 132, 0.5)');
gradient.addColorStop(0, 'rgba(255, 99, 132, 0)');
ctx.fillStyle = gradient;

for (var i = 1; i < numPoints; i++) {
    var x = margin + (i * stepX);
    var y = height - margin - (data[i] * stepY);
    var prevX = margin + ((i-1) * stepX);
    var prevY = height - margin - (data[i-1] * stepY);
    var nextX = margin + ((i+1) * stepX);
    var nextY = height - margin - (data[i+1] * stepY);
    var cpX1 = prevX + (x - prevX) / 2;
    var cpY1 = prevY
    var cpX2 = x - (nextX - prevX) / 2;
    var cpY2 = y;
    // ctx.lineTo(x, y);
    ctx.bezierCurveTo(cpX1, cpY1, cpX2, cpY2, x, y);
}

ctx.lineWidth = 2;
ctx.strokeStyle = 'rgba(255, 99, 132, 0.6)';
ctx.stroke();

ctx.lineTo(width - margin, height - margin);
ctx.lineTo(margin, height - margin);
ctx.closePath();
ctx.fill();

ctx.font = '900 12px "nome", sans-serif, arial';
ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
ctx.textAlign = 'center';
ctx.textBaseline = 'bottom';

for (var i = 0; i < numPoints; i++) {
    var x = margin + (i * stepX);
    var y = height - margin - (data[i] * stepY);
    var prevX = margin + ((i-1) * stepX);
    var prevY = height - margin - (data[i-1] * stepY);
    var nextX = margin + ((i+1) * stepX);
    var nextY = height - margin - (data[i+1] * stepY);
    var cpX1 = prevX + (x - prevX) / 2;
    var cpY1 = prevY
    var cpX2 = x - (nextX - prevX) / 2;
    var cpY2 = y;
    ctx.fillText(data[i], x, y);
}

ctx.font = '900 12px "nome", sans-serif, arial';
ctx.fillStyle = 'rgba(255, 255, 255, 0.7)';
ctx.textAlign = 'center';
ctx.textBaseline = 'top';

for (var i = 0; i < numPoints; i++) {
    var x = margin + (i * stepX);
    ctx.fillText(labels[i], x, height - margin + 5)
}

for (var i = 1; i < numPoints; i++) {
    var x = margin + (i * stepX);
}

////

const grafico = {
    idContenitore: '',
    margin: 50,
    asseX : ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
    asseY : [65, 59, 80, 81, 56, 55, 40, 0, 0 , 0, 0, 12],
    fontWeight: 900,
    fontSize: "12px",
    fontFamily: 'lucid-sans-serif',
    datiColore : 'rgba(255, 255, 255, 0.7)',
    curvaColore : 'rgba(255, 99, 132, 0.6)',
    gradienteStop1 : 'rgba(255, 99, 132, 0.5)',
    gradienteStop2 : 'rgba(255, 99, 132, 0)',

    inizializza(idContenitore, margin = 50) {
        this.idContenitore = idContenitore;
        this.margin = margin;
    },

    impostaDati(asseX = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'], asseY = [65, 59, 80, 81, 56, 55, 40, 0, 0 , 0, 0, 12]) {
        this.asseX = asseX;
        this.asseY = asseY;
    },

    impostaTesto(fontWeight = 900, fontSize = "12px", fontFamily = 'lucid-sans-serif', datiColore = 'rgba(255, 255, 255, 0.7)') {
        this.fontWeight = fontWeight;
        this.fontSize = fontSize;
        this.fontFamily = fontFamily;
        this.datiColore = datiColore;
        
    },

    impostaCurva(curvaColore = 'rgba(255, 99, 132, 0.6)', gradienteStop1 = 'rgba(255, 99, 132, 0.5)', gradienteStop2 = 'rgba(255, 99, 132, 0)'){
        this.curvaColore = curvaColore;
        this.gradienteStop1 = gradienteStop1;
        this.gradienteStop2 = gradienteStop2;
    },

    disegna() {
        // inizializzo e assegno il contenitore vero e proprio
        const contenitore = document.getElementById(this.idContenitore);
        // se il contenitore specificato non é valido diamo un messaggio di errore
        if(!contenitore) {
            console.error('Il contenitore specificato non esiste');
            return;
        }
        // inizializzo e assegno il canvas vero e proprio
        var canvas = document.createElement('canvas'); //seleziono quale oggetto voglio come mio canvas
        var ctx = canvas.getContext('2d'); // Vogliamo disegnare un canvas 2d
        // stabilisco che le dimensioni del canva dipendono dal contenitore selezionato...
        canvas.height = contenitore.offsetHeight;
        canvas.width = contenitore.offsetWidth; 
        //...e assegno a due variabili width e height questi valori
        var width = canvas.width;
        var height = canvas.height;
        // console.log(canvas.width, canvas.height)
        // Il grafico ovviamente sarà piú piccolo del canvas di un certo margine, e quindi avrà le seguenti dimensioni
        var innerWidth = width - (2 * this.margin);
        var innerHeight = height - (2 * this.margin);
        // Definisco il valore massimo dei dati...
        var valoreMax = Math.max(...this.asseY);
        // ... e il numero dei punti
        var numPunti = this.asseY.length;
        // Definisco le risoluzioni orizzontali e verticali
        var risX = innerWidth / (numPunti - 1);
        var risY = innerHeight / valoreMax;
        
        // Inizio a tracciare un nuovo percorso di disegno...
        ctx.beginPath();
        // ... a partire dal margine sinistro per le X e da quello superiore per le Y, 
        ctx.moveTo(this.margin, height - this.margin - (this.asseY[0] * risY));

        // inizio a disegnare l'area sottesa alla curva con un gradiente. 
        var gradiente = ctx.createLinearGradient(this.margin, height - this.margin, this.margin, 0);
        // e selezioni i colori di stop del grandiente
        gradiente.addColorStop(1, this.gradienteStop1); //un qualcosa come rgba(255, 99, 132, 0.5)
        gradiente.addColorStop(0, this.gradienteStop2); // un qualcosa rgba(255, 99, 132, 0)
        ctx.fillStyle = gradiente;

        
        for (var i = 1; i < numPunti; i++) {
            // andiamo a definire i livelli in base ad un numero intero di quanti/campioni di risoluzione
            var x = this.margin + (i * risX); 
            var y = height - this.margin - (this.asseY[i] * risY); // voglio un grafico normalizzato rispetto al valore massimo.
            // voglio una curva meno poligonale possibile per questo mi segno sia il livello precedente che quello successivo
            var prevX = this.margin + ((i-1) * risX);
            var prevY = height - this.margin - (this.asseY[i-1] * risY);
            var nextX = this.margin + ((i+1) * risX);
            var nextY = height - this.margin - (this.asseY[i+1] * risY);
            // decido quindi utilizzare una curva bezier cubica per evitare cuspidi, punti angolosi e flessi a tangente orizzontale,
            // e rendere piú dolce la transizione da un livello all'altro, attraverso dei flessi a tangente verticale
            var cpX1 = prevX + (x - prevX) / 2; //la prima coppia definisce il punto di controllo che stabilisce la direzione e la concavità iniziale della curva
            var cpY1 = prevY
            var cpX2 = x - (nextX - prevX) / 2; //la seconda coppia definisce il punto di controllo che stabilisce quelle finali
            var cpY2 = y;
            ctx.bezierCurveTo(cpX1, cpY1, cpX2, cpY2, x, y); //x e y costituiscono il punto di arrivo finale
        }
        // evidenzio la curva del grafico
        ctx.lineWidth = 2;
        ctx.strokeStyle = this.curvaColore; //esempio rgba(255, 99, 132, 0.6)
        ctx.stroke();

        //... e poi chiudo il percorso di disegno (stabilisco il contorno della mia area sottesa dalla curva )
        ctx.lineTo(width - this.margin, height - this.margin);
        ctx.lineTo(this.margin, height - this.margin);
        ctx.closePath();
        ctx.fill(); //riempio l'interno del mio percorso chiuso (la parte non evidenziata del contorno scompare)
     
        // voglio inserire i valori critici
        ctx.font = `${this.fontWeight} ${this.fontSize} "${this.fontFamily}", sans-serif, arial`;
        ctx.fillStyle = this.datiColore;
        ctx.textAlign = 'center'; 
        ctx.textBaseline = 'bottom'; 

        // per inserirli in corrispondenza dei valori evidenziati sull'asseX
        for (var i = 0; i < numPunti; i++) {
            var x = this.margin + (i * risX);
            var y = height - this.margin - (this.asseY[i] * risY);
            var prevX = this.margin + ((i-1) * risX);
            var prevY = height - this.margin - (this.asseY[i-1] * risY);
            var nextX = this.margin + ((i+1) * risX);
            var nextY = height - this.margin - (this.asseY[i+1] * risY);
            var cpX1 = prevX + (x - prevX) / 2;
            var cpY1 = prevY
            var cpX2 = x - (nextX - prevX) / 2;
            var cpY2 = y;
            ctx.fillText(this.asseY[i], x, y);
        }

        // voglio inserire i punti dei valori critici con la loro etichetta corrispondente
        ctx.font = `${this.fontWeight} ${this.fontSize} "${this.fontFamily}", sans-serif, arial`;
        ctx.fillStyle = this.datiColore; //esempio rgba(255, 255, 255, 0.7)
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        for (var i = 0; i < numPunti; i++) {
            var x = this.margin + (i * risX);
            ctx.fillText(this.asseX[i], x, height - this.margin + 5)
        }

        for (var i = 1; i < numPunti; i++) {
            var x = this.margin + (i * risX);
        }
        contenitore.appendChild(canvas);
    }
}

grafico.inizializza("contenitore");
var valori = [75, 59, 80, 81, 0, 55, 40];
var giorni = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
grafico.impostaDati(giorni, valori);
grafico.impostaTesto(500);
grafico.impostaCurva("#0ff","#f00","#0f0");
grafico.disegna();

grafico.inizializza("mena", 50);
var pulizie = [7, 5, 3, 1, 9, 0, 0, 0, 4, 3, 5, 20];
grafico.impostaDati(undefined, pulizie);
grafico.impostaTesto(700, "12px", "times", "pink");
grafico.impostaCurva("#5500ff", "#ffaa00ff", "#ffaa0000");
grafico.disegna();

//[65, 59, 80, 81, 56, 55, 40, 0, 0, 0, 0, 12];
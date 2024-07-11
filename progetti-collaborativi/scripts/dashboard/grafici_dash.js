function disegnaGrafici() {
    var canvasNC = document.querySelector("#nc" + " canvas");
    var canvasSF = document.querySelector("#sf" + " canvas");
    var canvasSR = document.querySelector("#sr" + " canvas");
    if (!canvasNC) {
        graficoNC();
    }
    if (!canvasSF) {
        graficoSF();
    }
    if (!canvasSR) {
        graficoSR();
    }    
    // window.addEventListener("resize", () => {
    //     if (canvasNC) {
    //         canvasNC.remove();
    //         graficoNC();
    //     }
    //     if (canvasSF) {
    //         canvasSF.remove();
    //         graficoSF();
    //     }
    //     if (canvasSR) {
    //         canvasSR.remove();
    //         graficoSR();
    //     }
    // });
}

function graficoNC() {
    grafico.inizializza("nc", 20);
    grafico.impostaDati(giorniOrdinati, valoriW1);
    grafico.impostaTesto(900, "12px", 'lucid-sans-serif', 'rgba(255, 255, 255, 0.7)');
    grafico.impostaCurva();
    grafico.disegna();
}

function graficoSF() {
    grafico.inizializza("sf", 20);
    grafico.impostaDati(giorniOrdinati, valoriW2);
    grafico.impostaTesto(900, "12px", 'lucid-sans-serif', 'rgba(255, 255, 255, 0.7)');
    grafico.impostaCurva('#00ff00','#00ff0077','#00ff0000');
    grafico.disegna();
}

function graficoSR() {
    grafico.inizializza("sr", 20);
    grafico.impostaDati(giorniOrdinati, valoriW3);
    grafico.impostaTesto(900, "12px", 'lucid-sans-serif', 'rgba(255, 255, 255, 0.7)');
    grafico.impostaCurva('#ff0000','#ff000077','#ff000000');
    grafico.disegna();
}

